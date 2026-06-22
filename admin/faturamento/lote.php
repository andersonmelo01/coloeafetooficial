<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');
validate_csrf();

$pedidoIds = array_values(array_unique(array_map('intval', $_POST['pedido_ids'] ?? [])));
if (!$pedidoIds) {
    flash('warning', 'Selecione ao menos um pedido para emitir em lote.');
    redirect('admin/faturamento/index.php');
}

$ok = 0;
$blocked = 0;

foreach ($pedidoIds as $pedidoId) {
    if ($pedidoId <= 0) {
        continue;
    }

    $validacao = nfe_validate_order($pedidoId);
    if (!$validacao['ok']) {
        $blocked++;
        nfe_register_event($pedidoId, 'lote_nfe', 'bloqueado', $validacao['message']);
        continue;
    }

    if (!fiscal_enabled()) {
        $blocked++;
        nfe_register_event($pedidoId, 'lote_nfe', 'ignorado', 'Fiscal desabilitado. Venda segue sem emissao de NF-e.');
        continue;
    }

    nfe_ensure_note($pedidoId);
    nfe_register_event($pedidoId, 'lote_nfe', 'pendente', 'Pedido incluido no lote de preparacao da NF-e.');
    $ok++;
}

if ($ok > 0) {
    flash('success', $ok . ' pedido(s) preparado(s) para emissao de NF-e em lote.');
}

if ($blocked > 0) {
    flash('warning', $blocked . ' pedido(s) ficaram bloqueados ou ignorados. Abra o detalhe do pedido para ver o motivo.');
}

redirect('admin/faturamento/index.php');
