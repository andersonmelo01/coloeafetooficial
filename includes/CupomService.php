<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/EmailService.php';

function pdv_fiscal_mode(): bool
{
    return fiscal_enabled();
}

function pdv_cupom_label(): string
{
    return pdv_fiscal_mode() ? 'Cupom fiscal — NFC-e' : 'Cupom não fiscal';
}

function pdv_ensure_cupom_no(int $vendaId): string
{
    $venda = db_one("SELECT id, cupom_no FROM vendas WHERE id = :id", ['id' => $vendaId]);
    if (!$venda) {
        return '';
    }

    if (!empty($venda['cupom_no'])) {
        return (string) $venda['cupom_no'];
    }

    $no = 'CC-' . date('Ymd') . '-' . str_pad((string) $vendaId, 6, '0', STR_PAD_LEFT);
    db()->prepare("UPDATE vendas SET cupom_no = :cupom_no WHERE id = :id")->execute([
        'cupom_no' => $no,
        'id' => $vendaId,
    ]);

    return $no;
}

function pdv_nfce_dv(string $chave43): string
{
    $soma = 0;
    $mult = 2;
    for ($i = strlen($chave43) - 1; $i >= 0; $i--) {
        $soma += ((int) $chave43[$i]) * $mult;
        $mult = $mult === 9 ? 2 : $mult + 1;
    }

    $resto = $soma % 11;
    return (string) ($resto > 1 ? (11 - $resto) : 0);
}

function pdv_ensure_nfce_chave(int $vendaId): string
{
    $venda = db_one("SELECT id, nfce_chave FROM vendas WHERE id = :id", ['id' => $vendaId]);
    if (!$venda) {
        return '';
    }

    if (!empty($venda['nfce_chave'])) {
        return (string) $venda['nfce_chave'];
    }

    $ufMap = [
        'AC' => 12, 'AL' => 27, 'AM' => 13, 'AP' => 16, 'BA' => 29, 'CE' => 23, 'DF' => 53, 'ES' => 32,
        'GO' => 52, 'MA' => 21, 'MG' => 31, 'MS' => 50, 'MT' => 51, 'PA' => 15, 'PB' => 25, 'PE' => 26,
        'PI' => 22, 'PR' => 41, 'RJ' => 33, 'RN' => 24, 'RO' => 11, 'RR' => 14, 'RS' => 43, 'SC' => 42,
        'SE' => 28, 'SP' => 35, 'TO' => 17,
    ];

    $uf = strtoupper((string) app_config('fiscal.uf', 'RJ'));
    $cUF = str_pad((string) ($ufMap[$uf] ?? 33), 2, '0', STR_PAD_LEFT);
    $cnpj = preg_replace('/\D/', '', (string) app_config('fiscal.cnpj', ''));
    $cnpj = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
    $aamm = date('ym');
    $mod = '65';
    $serie = str_pad((string) app_config('fiscal.serie_nfe', '1'), 3, '0', STR_PAD_LEFT);
    $numero = str_pad((string) app_config('fiscal.proximo_numero_nfe', '1'), 9, '0', STR_PAD_LEFT);
    $tpEmis = '9';
    $cNF = substr(bin2hex(random_bytes(8)), 0, 8);

    $chave43 = $cUF . $aamm . $cnpj . $mod . $serie . $numero . $tpEmis . $cNF;
    $dv = pdv_nfce_dv($chave43);
    $chave = $chave43 . $dv;

    db()->prepare("UPDATE vendas SET nfce_chave = :nfce_chave WHERE id = :id")->execute([
        'nfce_chave' => $chave,
        'id' => $vendaId,
    ]);

    try {
        db()->prepare(
            "INSERT INTO notas_fiscais (pedido_id, numero, serie, chave_acesso, status, emitida_em)
             VALUES (NULL, :numero, :serie, :chave, 'pendente', NOW())"
        )->execute([
            'numero' => $numero,
            'serie' => $serie,
            'chave' => $chave,
        ]);
    } catch (Throwable $e) {
        // Falha ao registrar nota não bloqueia impressão do cupom.
    }

    return $chave;
}

function pdv_cupom_html(int $vendaId, bool $showPrint = false): string
{
    $venda = db_one(
        "SELECT v.*, vendedor.nome AS vendedor, cliente.nome AS cliente
         FROM vendas v
         LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
         LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
         WHERE v.id = :id",
        ['id' => $vendaId]
    );
    if (!$venda) {
        return '<p class="text-danger">Venda não encontrada.</p>';
    }

    $cupomNo = pdv_ensure_cupom_no($vendaId);
    $isFiscal = pdv_fiscal_mode();
    $nfceChave = '';
    if ($isFiscal) {
        $nfceChave = pdv_ensure_nfce_chave($vendaId);
    }

    $itens = db_all("SELECT * FROM venda_itens WHERE venda_id = :id ORDER BY id", ['id' => $vendaId]);
    $pagamentos = db_all("SELECT * FROM venda_pagamentos WHERE venda_id = :id ORDER BY id", ['id' => $vendaId]);
    $parcelasAPrazo = db_all("SELECT * FROM venda_parcelas WHERE venda_id = :id AND status = 'pendente' ORDER BY parcela", ['id' => $vendaId]);
    $saldoAPrazo = round(array_sum(array_column($parcelasAPrazo, 'valor')), 2);
    $vendaPix = db_one("SELECT pix_copiaecola, pix_qrcode FROM vendas WHERE id = :id", ['id' => $vendaId]);

    $subtotal = (float) $venda['subtotal'];
    $desconto = (float) $venda['desconto'];
    $total = (float) $venda['total'];
    $descontoPct = $subtotal > 0 ? round(($desconto / $subtotal) * 100, 1) : 0.0;

    $out = '<div class="cupom">';
    if ($showPrint) {
        $out .= '<div class="d-print-none mb-3">';
        $out .= '<button type="button" class="btn btn-brand" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir cupom</button> ';
        $out .= '<a class="btn btn-outline-secondary" href="javascript:history.back()">Voltar</a>';
        $out .= '</div>';
    }
    $out .= '<div class="cupom-paper">';

    $out .= '<div class="cupom-header">';
    $out .= '<div class="cupom-brand">';
    $out .= '<img src="' . e(base_url('img/logo.jpeg')) . '" alt="Colo & Afeto">';
    $out .= '<strong>Colo & Afeto</strong>';
    $out .= '</div>';
    if ($isFiscal) {
        $out .= '<div class="cupom-fiscal-badge">CUPOM FISCAL — NFC-e</div>';
        $out .= '<div class="cupom-meta small">Emissão em modo offline / contingência</div>';
        if ((string) app_config('fiscal.cnpj', '') !== '') {
            $out .= '<div class="cupom-meta">CNPJ: ' . e(app_config('fiscal.cnpj')) . '</div>';
        }
        if ((string) app_config('fiscal.razao_social', '') !== '') {
            $out .= '<div class="cupom-meta">' . e(app_config('fiscal.razao_social')) . '</div>';
        }
        $emitente = trim((string) app_config('fiscal.razao_social', '')) . ' — ' . strtoupper((string) app_config('fiscal.uf', 'RJ'));
        $out .= '<div class="cupom-meta">Emitente: ' . e($emitente) . '</div>';
    } else {
        $out .= '<div class="cupom-fiscal-badge cupom-nao-fiscal">CUPOM NÃO FISCAL</div>';
    }
    $out .= '</div>';

    $out .= '<div class="cupom-body">';
    $out .= '<div class="cupom-title">' . e($cupomNo) . '</div>';
    $out .= '<div class="cupom-meta">' . e($venda['finalizada_em'] ?? $venda['criado_em']) . '</div>';
    if ((string) ($venda['cliente'] ?? '') !== '') {
        $out .= '<div class="cupom-meta">Cliente: ' . e($venda['cliente']) . '</div>';
    }
    $out .= '<div class="cupom-meta">Atendente: ' . e($venda['vendedor']) . '</div>';

    $out .= '<div class="cupom-table mt-3 mb-3">';
    $out .= '<div class="cupom-section-title">Itens</div>';
    $out .= '<table class="table table-sm table-borderless align-middle cupom-tbl mb-0">';
    $out .= '<thead><tr><th>Item</th><th class="text-center">Qtd</th><th class="text-end">Valor</th></tr></thead>';
    $out .= '<tbody>';
    foreach ($itens as $item) {
        $out .= '<tr>';
        $out .= '<td>' . e($item['nome_produto']) . '</td>';
        $out .= '<td class="text-center">' . (int) $item['quantidade'] . '</td>';
        $out .= '<td class="text-end">' . money_br((float) $item['total']) . '</td>';
        $out .= '</tr>';
    }
    $out .= '</tbody></table></div>';

    $out .= '<div class="cupom-totals">';
    $out .= '<div class="cupom-total-line"><span>Subtotal</span><span>' . money_br($subtotal) . '</span></div>';
    if ($desconto > 0) {
        $out .= '<div class="cupom-total-line text-danger"><span>Desconto (−' . number_format($descontoPct, 1, ',', '.') . '%)</span><span>−' . money_br($desconto) . '</span></div>';
    }
    $out .= '<div class="cupom-total-line cupom-total-final"><span>TOTAL</span><span>' . money_br($total) . '</span></div>';
    $out .= '</div>';

    if ($pagamentos) {
        $out .= '<div class="cupom-payments mt-3 mb-2">';
        $out .= '<div class="cupom-section-title">Pagamentos registrados</div>';
        foreach ($pagamentos as $pg) {
            $out .= '<div class="cupom-pay-line d-flex justify-content-between align-items-center">';
            $out .= '<span>' . e(pdv_payment_label($pg['metodo'])) . '</span>';
            $out .= '<span class="d-flex align-items-center gap-2">';
            if ($pg['status'] === 'pendente') {
                $out .= '<small class="cupom-status text-warning">pendente</small>';
            }
            $out .= '<span>' . money_br((float) $pg['valor']) . '</span>';
            $out .= '</span>';
            $out .= '</div>';
        }
        $out .= '</div>';
    }

    if ($saldoAPrazo > 0) {
        $out .= '<div class="cupom-parcelas mt-2 mb-3">';
        $out .= '<div class="cupom-section-title">A receber — parcelas pendentes</div>';
        $out .= '<table class="table table-sm table-borderless cupom-tbl mb-0">';
        $out .= '<tbody>';
        foreach ($parcelasAPrazo as $par) {
            $out .= '<tr>';
            $out .= '<td>' . (int) $par['parcela'] . 'ª parcela</td>';
            $out .= '<td>' . date('d/m/Y', strtotime($par['vencimento'])) . '</td>';
            $out .= '<td class="text-end">' . money_br((float) $par['valor']) . '</td>';
            $out .= '</tr>';
        }
        $out .= '<tr class="cupom-pend-total"><td colspan="2">Saldo a receber</td><td class="text-end">' . money_br($saldoAPrazo) . '</td></tr>';
        $out .= '</tbody></table></div>';
    }

    if (!empty($vendaPix['pix_copiaecola'])) {
        $out .= '<div class="cupom-pix mt-3 mb-3">';
        $out .= '<div class="fw-semibold mb-1">Pix copia e cola</div>';
        $out .= '<div class="pix-copiaecola">' . e($vendaPix['pix_copiaecola']) . '</div>';
        if (!empty($vendaPix['pix_qrcode'])) {
            $out .= '<div class="mt-2 text-center">';
            $out .= '<img src="data:image/png;base64,' . e($vendaPix['pix_qrcode']) . '" alt="QR Code Pix" style="max-width:180px;height:auto;">';
            $out .= '</div>';
        }
        $out .= '</div>';
    }

    if ($isFiscal && $nfceChave !== '') {
        $out .= '<div class="cupom-chave mt-3 mb-2">';
        $out .= '<div class="cupom-meta">Chave de acesso NFC-e</div>';
        $out .= '<div class="cupom-meta fw-semibold" style="word-break:break-all;">' . e($nfceChave) . '</div>';
        $out .= '<div class="cupom-meta">Consulta: <a href="https://www.sefaz.rs.gov.br/Consulta/ConsultaNFCe.aspx" target="_blank" rel="noopener">Portal SEFAZ</a></div>';
        $out .= '</div>';
    }

    $out .= '<div class="cupom-footer mt-4 text-center">';
    $out .= '<hr>';
    $out .= '<p>Obrigado pela preferência!</p>';
    $out .= '<p class="small text-secondary">Colo & Afeto · Atendimento materno-infantil</p>';
    if ($isFiscal) {
        $out .= '<p class="small text-secondary">Este documento não substitui a NF-e (mod 55) quando aplicável.</p>';
    } else {
        $out .= '<p class="small text-secondary">Documento simplificado sem validade fiscal.</p>';
    }
    $out .= '</div>';

    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

function pdv_cupom_send_email(int $vendaId, string $toEmail, string $toName = ''): array
{
    $venda = db_one("SELECT id, numero FROM vendas WHERE id = :id", ['id' => $vendaId]);
    if (!$venda) {
        return ['ok' => false, 'message' => 'Venda não encontrada.'];
    }

    if (trim($toEmail) === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'E-mail do destinatário inválido.'];
    }

    pdv_ensure_cupom_no($vendaId);
    $label = pdv_cupom_label();
    $subject = $label . ' ' . (string) $venda['numero'] . ' — Colo & Afeto';
    $body = '<div style="font-family:\'Segoe UI\',Arial,sans-serif;max-width:420px;margin:0 auto;color:#222;">';
    $body .= '<div style="text-align:center;margin-bottom:18px;"><img src="' . e(base_url('img/logo.jpeg')) . '" alt="Colo & Afeto" style="height:40px;"></div>';
    $body .= pdv_cupom_html($vendaId, false);
    $body .= '</div>';

    $ok = email_send(
        (int) $vendaId,
        $toEmail,
        $toName !== '' ? $toName : 'Cliente',
        $subject,
        $body,
        strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body))
    );

    return ['ok' => $ok, 'message' => $ok ? 'E-mail enviado com sucesso.' : 'Falha ao enviar e-mail.'];
}
