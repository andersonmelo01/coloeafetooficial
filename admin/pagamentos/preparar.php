<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/EfiService.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? 0);
$pedido = db_one("SELECT * FROM pedidos WHERE id = :id", ['id' => $id]);
if (!$pedido) {
    flash('danger', 'Pedido nao encontrado.');
    redirect('admin/pagamentos/index.php');
}

$method = $pedido['forma_pagamento'] === 'cartao_credito' ? 'credit_card' : 'pix_qrcode';
$status = efi_can_charge_method($method);
if (!$status['ok']) {
    flash('warning', $status['message']);
    redirect('admin/pagamentos/index.php');
}

$payload = efi_prepare_payment_payload($pedido, $method);
$payload['txid'] = 'pedido_' . (int) $pedido['id'];
$payload['expected_webhook'] = base_url('webhooks/efi.php?token=' . urlencode((string) app_config('efi.webhook_token', '')));
db()->prepare("UPDATE pedidos SET pagamento_status = 'preparado', pagamento_payload = :payload WHERE id = :id")->execute([
    'id' => $id,
    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);

flash('success', 'Cobranca preparada para ' . ($method === 'credit_card' ? 'cartao de credito' : 'Pix QR Code') . '. O webhook fara a validacao automatica quando a Efi confirmar o pagamento.');
redirect('admin/pedidos/detalhe.php?id=' . $id);
