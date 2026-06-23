<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

function save_certificate_upload(string $field, string $prefix): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Falha ao enviar certificado: ' . $field);
        return null;
    }

    $originalName = (string) ($_FILES[$field]['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['pfx', 'p12', 'pem', 'crt', 'key'];

    if (!in_array($extension, $allowed, true)) {
        flash('danger', 'Formato de certificado inválido para ' . $field . '. Use pfx, p12, pem, crt ou key.');
        return null;
    }

    $directory = dirname(__DIR__, 2) . '/storage/certificados';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $destination)) {
        flash('danger', 'Não foi possível salvar certificado: ' . $field);
        return null;
    }

    return $destination;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $groups = [
        'loja.vendas_habilitadas' => 'loja',
        'loja.mensagem_catalogo' => 'loja',
        'fiscal.habilitado' => 'fiscal',
        'fiscal.ambiente' => 'fiscal',
        'fiscal.uf' => 'fiscal',
        'fiscal.cnpj' => 'fiscal',
        'fiscal.razao_social' => 'fiscal',
        'fiscal.inscricao_estadual' => 'fiscal',
        'fiscal.inscricao_municipal' => 'fiscal',
        'fiscal.cnae' => 'fiscal',
        'fiscal.crt' => 'fiscal',
        'fiscal.serie_nfe' => 'fiscal',
        'fiscal.proximo_numero_nfe' => 'fiscal',
        'fiscal.codigo_municipio' => 'fiscal',
        'fiscal.endereco_logradouro' => 'fiscal',
        'fiscal.endereco_numero' => 'fiscal',
        'fiscal.endereco_bairro' => 'fiscal',
        'fiscal.endereco_municipio' => 'fiscal',
        'fiscal.endereco_cep' => 'fiscal',
        'fiscal.certificado_pfx' => 'fiscal',
        'fiscal.certificado_senha' => 'fiscal',
        'fiscal.reforma_tributaria_habilitada' => 'fiscal',
        'efi.habilitado' => 'pagamento',
        'efi.pix_habilitado' => 'pagamento',
        'efi.cartao_habilitado' => 'pagamento',
        'efi.ambiente' => 'pagamento',
        'efi.client_id' => 'pagamento',
        'efi.client_secret' => 'pagamento',
        'efi.payee_code' => 'pagamento',
        'efi.pix_chave' => 'pagamento',
        'efi.certificado' => 'pagamento',
        'efi.webhook_token' => 'pagamento',
        'efi.webhook_url' => 'pagamento',
        'email.habilitado' => 'email',
        'email.smtp_host' => 'email',
        'email.smtp_port' => 'email',
        'email.smtp_usuario' => 'email',
        'email.smtp_senha' => 'email',
        'email.smtp_secure' => 'email',
        'email.remetente_email' => 'email',
        'email.remetente_nome' => 'email',
    ];

    $_POST['loja_vendas_habilitadas'] = isset($_POST['loja_vendas_habilitadas']) ? '1' : '0';
    $_POST['fiscal_habilitado'] = isset($_POST['fiscal_habilitado']) ? '1' : '0';
    $_POST['fiscal_reforma_tributaria_habilitada'] = isset($_POST['fiscal_reforma_tributaria_habilitada']) ? '1' : '0';
    $_POST['efi_habilitado'] = isset($_POST['efi_habilitado']) ? '1' : '0';
    $_POST['efi_pix_habilitado'] = isset($_POST['efi_pix_habilitado']) ? '1' : '0';
    $_POST['efi_cartao_habilitado'] = isset($_POST['efi_cartao_habilitado']) ? '1' : '0';
    $_POST['email_habilitado'] = isset($_POST['email_habilitado']) ? '1' : '0';

    $fiscalUpload = save_certificate_upload('fiscal_certificado_upload', 'fiscal-a1');
    if ($fiscalUpload) {
        $_POST['fiscal_certificado_pfx'] = $fiscalUpload;
    }

    $efiUpload = save_certificate_upload('efi_certificado_upload', 'efi');
    if ($efiUpload) {
        $_POST['efi_certificado'] = $efiUpload;
    }

    $map = [
        'loja.vendas_habilitadas' => 'loja_vendas_habilitadas',
        'loja.mensagem_catalogo' => 'loja_mensagem_catalogo',
        'fiscal.habilitado' => 'fiscal_habilitado',
        'fiscal.ambiente' => 'fiscal_ambiente',
        'fiscal.uf' => 'fiscal_uf',
        'fiscal.cnpj' => 'fiscal_cnpj',
        'fiscal.razao_social' => 'fiscal_razao_social',
        'fiscal.inscricao_estadual' => 'fiscal_inscricao_estadual',
        'fiscal.inscricao_municipal' => 'fiscal_inscricao_municipal',
        'fiscal.cnae' => 'fiscal_cnae',
        'fiscal.crt' => 'fiscal_crt',
        'fiscal.serie_nfe' => 'fiscal_serie_nfe',
        'fiscal.proximo_numero_nfe' => 'fiscal_proximo_numero_nfe',
        'fiscal.codigo_municipio' => 'fiscal_codigo_municipio',
        'fiscal.endereco_logradouro' => 'fiscal_endereco_logradouro',
        'fiscal.endereco_numero' => 'fiscal_endereco_numero',
        'fiscal.endereco_bairro' => 'fiscal_endereco_bairro',
        'fiscal.endereco_municipio' => 'fiscal_endereco_municipio',
        'fiscal.endereco_cep' => 'fiscal_endereco_cep',
        'fiscal.certificado_pfx' => 'fiscal_certificado_pfx',
        'fiscal.certificado_senha' => 'fiscal_certificado_senha',
        'fiscal.reforma_tributaria_habilitada' => 'fiscal_reforma_tributaria_habilitada',
        'efi.habilitado' => 'efi_habilitado',
        'efi.pix_habilitado' => 'efi_pix_habilitado',
        'efi.cartao_habilitado' => 'efi_cartao_habilitado',
        'efi.ambiente' => 'efi_ambiente',
        'efi.client_id' => 'efi_client_id',
        'efi.client_secret' => 'efi_client_secret',
        'efi.payee_code' => 'efi_payee_code',
        'efi.pix_chave' => 'efi_pix_chave',
        'efi.certificado' => 'efi_certificado',
        'efi.webhook_token' => 'efi_webhook_token',
        'efi.webhook_url' => 'efi_webhook_url',
        'email.habilitado' => 'email_habilitado',
        'email.smtp_host' => 'email_smtp_host',
        'email.smtp_port' => 'email_smtp_port',
        'email.smtp_usuario' => 'email_smtp_usuario',
        'email.smtp_senha' => 'email_smtp_senha',
        'email.smtp_secure' => 'email_smtp_secure',
        'email.remetente_email' => 'email_remetente_email',
        'email.remetente_nome' => 'email_remetente_nome',
    ];

    foreach ($map as $key => $field) {
        set_app_config($key, trim((string) ($_POST[$field] ?? '')), $groups[$key]);
    }

    flash('success', 'Configurações atualizadas.');
    redirect('admin/configuracoes/index.php');
}

$pageTitle = 'Configurações';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4">
        <h1 class="h3 section-title">Configurações do sistema</h1>
        <form method="post" enctype="multipart/form-data" class="row g-4">
            <?= csrf_field() ?>
            <div class="col-12"><h2 class="h5">Loja virtual</h2><p class="text-secondary small">Controle se a loja realiza vendas online ou funciona apenas como catálogo para visualização dos produtos.</p></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="loja_vendas_habilitadas" <?= loja_vendas_enabled() ? 'checked' : '' ?>> Habilitar vendas na loja</label></div>
            <div class="col-md-8"><label class="form-label">Mensagem quando vendas estiverem desabilitadas</label><textarea class="form-control" name="loja_mensagem_catalogo" rows="3"><?= e(loja_catalog_message()) ?></textarea></div>

            <div class="col-12 border-top pt-4"><h2 class="h5">Fiscal / SPED-NFe</h2><p class="text-secondary small">Quando desabilitado, o gestor pode confirmar vendas sem controle fiscal.</p></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="fiscal_habilitado" <?= fiscal_enabled() ? 'checked' : '' ?>> Habilitar fiscal/NF-e</label></div>
            <div class="col-md-4"><label class="form-label">Ambiente</label><select class="form-select" name="fiscal_ambiente"><option value="homologacao" <?= app_config('fiscal.ambiente') === 'homologacao' ? 'selected' : '' ?>>Homologação</option><option value="producao" <?= app_config('fiscal.ambiente') === 'producao' ? 'selected' : '' ?>>Produção</option></select></div>
            <div class="col-md-4"><label class="form-label">UF</label><input class="form-control" name="fiscal_uf" value="<?= e(app_config('fiscal.uf', 'RJ')) ?>" maxlength="2"></div>
            <div class="col-md-6"><label class="form-label">CNPJ emitente</label><input class="form-control" name="fiscal_cnpj" data-mask="cnpj" value="<?= e(app_config('fiscal.cnpj', '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Razão social</label><input class="form-control" name="fiscal_razao_social" value="<?= e(app_config('fiscal.razao_social', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Inscrição estadual</label><input class="form-control" name="fiscal_inscricao_estadual" value="<?= e(app_config('fiscal.inscricao_estadual', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Inscrição municipal</label><input class="form-control" name="fiscal_inscricao_municipal" value="<?= e(app_config('fiscal.inscricao_municipal', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">CNAE</label><input class="form-control" name="fiscal_cnae" value="<?= e(app_config('fiscal.cnae', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">CRT</label><select class="form-select" name="fiscal_crt"><option value="1" <?= app_config('fiscal.crt') === '1' ? 'selected' : '' ?>>Simples Nacional</option><option value="2" <?= app_config('fiscal.crt') === '2' ? 'selected' : '' ?>>Simples excesso sublimite</option><option value="3" <?= app_config('fiscal.crt') === '3' ? 'selected' : '' ?>>Regime normal</option></select></div>
            <div class="col-md-4"><label class="form-label">Série NF-e</label><input class="form-control" name="fiscal_serie_nfe" type="number" value="<?= e(app_config('fiscal.serie_nfe', '1')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Próximo número NF-e</label><input class="form-control" name="fiscal_proximo_numero_nfe" type="number" value="<?= e(app_config('fiscal.proximo_numero_nfe', '1')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Código município IBGE</label><input class="form-control" name="fiscal_codigo_municipio" value="<?= e(app_config('fiscal.codigo_municipio', '')) ?>"></div>
            <div class="col-md-5"><label class="form-label">Logradouro emitente</label><input class="form-control" name="fiscal_endereco_logradouro" value="<?= e(app_config('fiscal.endereco_logradouro', '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Número</label><input class="form-control" name="fiscal_endereco_numero" value="<?= e(app_config('fiscal.endereco_numero', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" name="fiscal_endereco_bairro" value="<?= e(app_config('fiscal.endereco_bairro', '')) ?>"></div>
            <div class="col-md-5"><label class="form-label">Município</label><input class="form-control" name="fiscal_endereco_municipio" value="<?= e(app_config('fiscal.endereco_municipio', '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">CEP</label><input class="form-control" name="fiscal_endereco_cep" data-mask="cep" value="<?= e(app_config('fiscal.endereco_cep', '')) ?>"></div>
            <div class="col-md-8"><label class="form-label">Upload certificado A1/PFX</label><input class="form-control" name="fiscal_certificado_upload" type="file" accept=".pfx,.p12,.pem,.crt,.key"><div class="form-text">Atual: <?= e(app_config('fiscal.certificado_pfx', 'nenhum')) ?></div></div>
            <div class="col-md-8"><label class="form-label">Caminho certificado PFX A1 manual</label><input class="form-control" name="fiscal_certificado_pfx" value="<?= e(app_config('fiscal.certificado_pfx', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Senha certificado</label><input class="form-control" name="fiscal_certificado_senha" type="password" value="<?= e(app_config('fiscal.certificado_senha', '')) ?>"></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="fiscal_reforma_tributaria_habilitada" <?= fiscal_reforma_enabled() ? 'checked' : '' ?>> Validar campos da Reforma Tributária do Consumo (IBS/CBS/IS) no cadastro fiscal dos itens</label><div class="form-text">Use conforme cronograma de obrigatoriedade e orientação contábil para o regime da empresa.</div></div>

            <div class="col-12 border-top pt-4"><h2 class="h5">Pagamento / Efi Bank</h2><p class="text-secondary small">Preparado para QR Code Pix e cartão de crédito via SDK/API Efi.</p></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="efi_habilitado" <?= efi_enabled() ? 'checked' : '' ?>> Habilitar Efi Bank</label></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="efi_pix_habilitado" <?= efi_pix_enabled() ? 'checked' : '' ?>> Habilitar Pix QR Code</label></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="efi_cartao_habilitado" <?= efi_card_enabled() ? 'checked' : '' ?>> Habilitar cartão de crédito</label></div>
            <div class="col-md-4"><label class="form-label">Ambiente</label><select class="form-select" name="efi_ambiente"><option value="sandbox" <?= app_config('efi.ambiente') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option><option value="producao" <?= app_config('efi.ambiente') === 'producao' ? 'selected' : '' ?>>Produção</option></select></div>
            <div class="col-md-4"><label class="form-label">Chave Pix</label><input class="form-control" name="efi_pix_chave" value="<?= e(app_config('efi.pix_chave', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Identificador de conta/payee_code</label><input class="form-control" name="efi_payee_code" value="<?= e(app_config('efi.payee_code', '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Client ID</label><input class="form-control" name="efi_client_id" value="<?= e(app_config('efi.client_id', '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Client Secret</label><input class="form-control" name="efi_client_secret" type="password" value="<?= e(app_config('efi.client_secret', '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Upload certificado Efi</label><input class="form-control" name="efi_certificado_upload" type="file" accept=".pfx,.p12,.pem,.crt,.key"><div class="form-text">Atual: <?= e(app_config('efi.certificado', 'nenhum')) ?></div></div>
            <div class="col-md-6"><label class="form-label">Caminho certificado Efi manual</label><input class="form-control" name="efi_certificado" value="<?= e(app_config('efi.certificado', '')) ?>"></div>
            <div class="col-md-8"><label class="form-label">URL de webhook Efi</label><input class="form-control" name="efi_webhook_url" value="<?= e(app_config('efi.webhook_url', base_url('webhooks/efi.php'))) ?>"></div>
            <div class="col-md-4"><label class="form-label">Token secreto webhook</label><input class="form-control" name="efi_webhook_token" value="<?= e(app_config('efi.webhook_token', '')) ?>"></div>

            <div class="col-12 border-top pt-4"><h2 class="h5">E-mail / PHPMailer</h2><p class="text-secondary small">Usado para confirmação de pedido, mudanças de status e avisos de entrega.</p></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="email_habilitado" <?= app_config('email.habilitado', '0') === '1' ? 'checked' : '' ?>> Habilitar envio de e-mails</label></div>
            <div class="col-md-4"><label class="form-label">Remetente e-mail</label><input class="form-control" name="email_remetente_email" type="email" value="<?= e(app_config('email.remetente_email', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Remetente nome</label><input class="form-control" name="email_remetente_nome" value="<?= e(app_config('email.remetente_nome', 'Colo e Afeto')) ?>"></div>
            <div class="col-md-4"><label class="form-label">SMTP host</label><input class="form-control" name="email_smtp_host" value="<?= e(app_config('email.smtp_host', '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Porta</label><input class="form-control" name="email_smtp_port" type="number" value="<?= e(app_config('email.smtp_port', '587')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Segurança</label><select class="form-select" name="email_smtp_secure"><option value="tls" <?= app_config('email.smtp_secure', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= app_config('email.smtp_secure', 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option><option value="none" <?= app_config('email.smtp_secure', 'tls') === 'none' ? 'selected' : '' ?>>Nenhuma</option></select></div>
            <div class="col-md-4"><label class="form-label">SMTP usuário</label><input class="form-control" name="email_smtp_usuario" value="<?= e(app_config('email.smtp_usuario', '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">SMTP senha</label><input class="form-control" name="email_smtp_senha" type="password" value="<?= e(app_config('email.smtp_senha', '')) ?>"></div>
            <div class="col-12"><button class="btn btn-brand">Salvar configurações</button></div>
        </form>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
