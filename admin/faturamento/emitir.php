<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? 0);
$pedido = db_one("SELECT * FROM pedidos WHERE id = :id", ['id' => $id]);
if (!$pedido) {
    flash('danger', 'Pedido nao encontrado.');
    redirect('admin/faturamento/index.php');
}

$validacao = nfe_validate_order($id);
if (!$validacao['ok']) {
    nfe_register_event($id, 'preparacao_nfe', 'bloqueado', $validacao['message']);
    flash('warning', $validacao['message']);
    redirect('admin/pedidos/detalhe.php?id=' . $id);
}

if (!fiscal_enabled()) {
    nfe_register_event($id, 'preparacao_nfe', 'ignorado', 'Fiscal desabilitado. Venda segue sem emissao de NF-e.');
    flash('success', 'Fiscal desabilitado. Venda liberada sem emissao de NF-e.');
    redirect('admin/pedidos/detalhe.php?id=' . $id);
}

$notaId = nfe_ensure_note($id);

nfe_register_event($id, 'preparacao_nfe', 'pendente', 'Pedido preparado para montagem/envio pela SPED-NFe.');
flash('success', 'Pedido preparado para emissao. Complete a chamada real da SPED-NFe no servico fiscal.');
redirect('admin/faturamento/nota.php?id=' . $notaId);
