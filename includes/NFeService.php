<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function nfe_sped_available(): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    return class_exists('NFePHP\\NFe\\Tools') || class_exists('NFePHP\\NFe\\Make');
}

function nfe_validate_order(int $pedidoId): array
{
    $pedido = db_one(
        "SELECT p.*, u.nome, u.email, cp.documento, cp.tipo_pessoa, cp.inscricao_estadual
         FROM pedidos p
         JOIN usuarios u ON u.id = p.usuario_id
         LEFT JOIN clientes_perfis cp ON cp.usuario_id = u.id
         WHERE p.id = :id",
        ['id' => $pedidoId]
    );

    if (!$pedido) {
        return ['ok' => false, 'message' => 'Pedido não encontrado.'];
    }

    if (!fiscal_enabled()) {
        return ['ok' => true, 'message' => 'Fiscal desabilitado. Pedido liberado para venda sem NF-e.'];
    }

    $requiredConfig = [
        'fiscal.uf' => 'UF do emitente',
        'fiscal.cnpj' => 'CNPJ do emitente',
        'fiscal.razao_social' => 'Razão social',
        'fiscal.inscricao_estadual' => 'Inscrição estadual',
        'fiscal.crt' => 'CRT/regime tributário',
        'fiscal.serie_nfe' => 'Série da NF-e',
        'fiscal.proximo_numero_nfe' => 'Próximo número da NF-e',
        'fiscal.codigo_municipio' => 'Código IBGE do município',
        'fiscal.endereco_logradouro' => 'Logradouro do emitente',
        'fiscal.endereco_numero' => 'Número do endereço do emitente',
        'fiscal.endereco_bairro' => 'Bairro do emitente',
        'fiscal.endereco_municipio' => 'Município do emitente',
        'fiscal.endereco_cep' => 'CEP do emitente',
        'fiscal.certificado_pfx' => 'Certificado A1/PFX',
        'fiscal.certificado_senha' => 'Senha do certificado',
    ];

    foreach ($requiredConfig as $key => $label) {
        if (trim((string) app_config($key, '')) === '') {
            return ['ok' => false, 'message' => 'Configuração fiscal pendente: ' . $label . '.'];
        }
    }

    $certificate = trim((string) app_config('fiscal.certificado_pfx', ''));
    if ($certificate !== '' && !is_file($certificate)) {
        return ['ok' => false, 'message' => 'Certificado A1/PFX não encontrado no caminho configurado.'];
    }

    if (empty($pedido['documento'])) {
        return ['ok' => false, 'message' => 'Cliente sem CPF/CNPJ para emissão fiscal.'];
    }

    $items = db_all(
        "SELECT pi.nome_produto, p.*
         FROM pedido_itens pi
         LEFT JOIN produtos p ON p.id = pi.produto_id
         WHERE pi.pedido_id = :pedido_id",
        ['pedido_id' => $pedidoId]
    );

    foreach ($items as $item) {
        $issues = produto_fiscal_issues($item);
        if ($issues['errors']) {
            return [
                'ok' => false,
                'message' => 'Produto com cadastro fiscal pendente: ' . $item['nome_produto'] . ' - ' . implode('; ', $issues['errors']) . '.',
            ];
        }
    }

    if (!nfe_sped_available()) {
        return ['ok' => false, 'message' => 'Biblioteca SPED-NFe não instalada. Execute composer install.'];
    }

    return ['ok' => true, 'message' => 'Pedido validado para emissão de NF-e.'];
}

function produto_fiscal_issues(array $produto): array
{
    $errors = [];
    $warnings = [];
    $crt = trim((string) app_config('fiscal.crt', '1'));

    $ncm = preg_replace('/\D+/', '', (string) ($produto['ncm'] ?? ''));
    if (!preg_match('/^\d{8}$/', $ncm)) {
        $errors[] = 'NCM deve conter 8 digitos';
    }

    $cfop = preg_replace('/\D+/', '', (string) ($produto['cfop'] ?? ''));
    if (!preg_match('/^\d{4}$/', $cfop)) {
        $errors[] = 'CFOP deve conter 4 digitos';
    }

    if (trim((string) ($produto['unidade'] ?? '')) === '') {
        $errors[] = 'Unidade comercial obrigatoria';
    }

    if ((float) ($produto['preco'] ?? 0) <= 0) {
        $errors[] = 'Preco de venda deve ser maior que zero';
    }

    if (trim((string) ($produto['origem_mercadoria'] ?? '')) === '') {
        $errors[] = 'Origem da mercadoria obrigatoria';
    }

    if ($crt === '1') {
        if (trim((string) ($produto['csosn'] ?? '')) === '') {
            $errors[] = 'CSOSN obrigatorio para Simples Nacional';
        }
    } elseif (trim((string) ($produto['cst_icms'] ?? '')) === '') {
        $errors[] = 'CST ICMS obrigatorio para regime normal';
    }

    if (trim((string) ($produto['cst_pis'] ?? '')) === '') {
        $warnings[] = 'Revisar CST PIS conforme regime fiscal';
    }

    if (trim((string) ($produto['cst_cofins'] ?? '')) === '') {
        $warnings[] = 'Revisar CST COFINS conforme regime fiscal';
    }

    if (trim((string) ($produto['cest'] ?? '')) === '') {
        $warnings[] = 'Informar CEST quando o NCM estiver sujeito a substituicao tributaria';
    }

    $rtcFields = [
        'cst_ibs_cbs' => 'CST IBS/CBS',
        'cclass_trib_ibs_cbs' => 'Classificacao tributaria IBS/CBS',
    ];
    foreach ($rtcFields as $field => $label) {
        if (trim((string) ($produto[$field] ?? '')) === '') {
            if (fiscal_reforma_enabled()) {
                $errors[] = $label . ' obrigatorio para Reforma Tributaria';
            } else {
                $warnings[] = 'Revisar ' . $label . ' para Reforma Tributaria';
            }
        }
    }

    if ((float) ($produto['aliquota_ibs_uf'] ?? 0) === 0.0 && (float) ($produto['aliquota_ibs_municipal'] ?? 0) === 0.0) {
        $warnings[] = 'Revisar aliquotas IBS UF/Municipal conforme cronograma RTC';
    }

    if ((float) ($produto['aliquota_cbs'] ?? 0) === 0.0) {
        $warnings[] = 'Revisar aliquota CBS conforme cronograma RTC';
    }

    $hasSelectiveTax = trim((string) ($produto['cst_is'] ?? '')) !== ''
        || trim((string) ($produto['cclass_trib_is'] ?? '')) !== ''
        || (float) ($produto['aliquota_is'] ?? 0) > 0;
    if ($hasSelectiveTax && (trim((string) ($produto['cst_is'] ?? '')) === '' || trim((string) ($produto['cclass_trib_is'] ?? '')) === '')) {
        $warnings[] = 'Produto com IS parcial: revisar CST IS e classificacao tributaria IS';
    }

    return ['errors' => $errors, 'warnings' => $warnings];
}

function nfe_ensure_note(int $pedidoId): int
{
    $nota = db_one("SELECT id FROM notas_fiscais WHERE pedido_id = :pedido_id LIMIT 1", ['pedido_id' => $pedidoId]);
    if ($nota) {
        return (int) $nota['id'];
    }

    db()->prepare(
        "INSERT INTO notas_fiscais (pedido_id, serie, status)
         VALUES (:pedido_id, :serie, 'pendente')"
    )->execute([
        'pedido_id' => $pedidoId,
        'serie' => app_config('fiscal.serie_nfe', '1'),
    ]);

    return (int) db()->lastInsertId();
}

function nfe_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/nfe';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function nfe_xml_absolute_path(?string $relativePath): ?string
{
    if (!$relativePath) {
        return null;
    }

    $base = realpath(dirname(__DIR__));
    $file = realpath(dirname(__DIR__) . '/' . ltrim($relativePath, '/\\'));

    if (!$base || !$file || strpos($file, $base) !== 0 || !is_file($file)) {
        return null;
    }

    return $file;
}

function nfe_register_event(int $pedidoId, string $type, string $status, string $message, array $payload = []): void
{
    $stmt = db()->prepare(
        "INSERT INTO faturamento_eventos (pedido_id, tipo, status, mensagem, payload)
         VALUES (:pedido_id, :tipo, :status, :mensagem, :payload)"
    );
    $stmt->execute([
        'pedido_id' => $pedidoId,
        'tipo' => $type,
        'status' => $status,
        'mensagem' => $message,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}
