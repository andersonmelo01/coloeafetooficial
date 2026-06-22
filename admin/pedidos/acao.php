<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_once dirname(__DIR__, 2) . '/includes/EmailService.php';
require_login('admin');
validate_csrf();

$id = (int) ($_POST['id'] ?? 0);
$acao = (string) ($_POST['acao'] ?? '');
$validacao = nfe_validate_order($id);
$statusOptions = ['novo', 'aguardando_pagamento', 'pago', 'separacao', 'enviado', 'entregue', 'cancelado'];

if ($acao === 'alterar_status') {
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, $statusOptions, true)) {
        flash('danger', 'Status invalido.');
        redirect('admin/pedidos/detalhe.php?id=' . $id);
    }

    db()->prepare("UPDATE pedidos SET status = :status, atualizado_em = NOW() WHERE id = :id")->execute([
        'id' => $id,
        'status' => $status,
    ]);
    nfe_register_event($id, 'alteracao_status', $status, 'Status do pedido alterado pelo gestor para acompanhamento do cliente.');
    pedido_send_status_email($id, $status);
    flash('success', 'Status do pedido atualizado.');
    redirect('admin/pedidos/detalhe.php?id=' . $id);
}

if ($acao === 'validar_fiscal') {
    db()->prepare("UPDATE pedidos SET fiscal_status = :status, fiscal_mensagem = :mensagem, revisado_em = NOW() WHERE id = :id")->execute([
        'id' => $id,
        'status' => $validacao['ok'] ? (fiscal_enabled() ? 'validado' : 'ignorado') : 'pendente',
        'mensagem' => $validacao['message'],
    ]);
    nfe_register_event($id, 'validacao_fiscal', $validacao['ok'] ? 'ok' : 'pendente', $validacao['message']);
    flash($validacao['ok'] ? 'success' : 'warning', $validacao['message']);
    redirect('admin/pedidos/detalhe.php?id=' . $id);
}

if ($acao === 'confirmar') {
    if (!$validacao['ok']) {
        flash('warning', 'Pedido nao confirmado: ' . $validacao['message']);
        redirect('admin/pedidos/detalhe.php?id=' . $id);
    }

    db()->prepare(
        "UPDATE pedidos
         SET status = 'aguardando_pagamento',
             fiscal_status = :fiscal_status,
             fiscal_mensagem = :mensagem,
             confirmado_em = NOW(),
             revisado_em = NOW()
         WHERE id = :id"
    )->execute([
        'id' => $id,
        'fiscal_status' => fiscal_enabled() ? 'validado' : 'ignorado',
        'mensagem' => $validacao['message'],
    ]);
    nfe_register_event($id, 'confirmacao_pedido', 'confirmado', 'Pedido confirmado pelo gestor.');
    pedido_send_status_email($id, 'aguardando_pagamento', 'Pedido confirmado pelo gestor e liberado para pagamento/faturamento.');
    flash('success', 'Pedido confirmado e liberado para pagamento/faturamento.');
    redirect('admin/pedidos/detalhe.php?id=' . $id);
}

flash('danger', 'Acao invalida.');
redirect('admin/pedidos/index.php');
