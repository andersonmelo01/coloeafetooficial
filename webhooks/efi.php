<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/vendas/_pdv.php';
require_once dirname(__DIR__) . '/includes/EfiService.php';

header('Content-Type: application/json; charset=utf-8');

$token = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_EFI_TOKEN'] ?? ''));
$expectedToken = (string) app_config('efi.webhook_token', '');

if ($expectedToken !== '' && !hash_equals($expectedToken, $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'invalid token']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$pedidoId = (int) (
    $payload['pedido_id']
    ?? $payload['order_id']
    ?? $payload['custom_id']
    ?? 0
);

if ($pedidoId === 0 && !empty($payload['txid']) && preg_match('/pedido_(\d+)/', (string) $payload['txid'], $matches)) {
    $pedidoId = (int) $matches[1];
}

$status = (string) (
    $payload['status']
    ?? $payload['situacao']
    ?? $payload['event']
    ?? 'received'
);

db()->prepare(
    "INSERT INTO webhook_eventos (provedor, evento, status, pedido_id, payload, processado)
     VALUES ('efi', :evento, :status, :pedido_id, :payload, 0)"
)->execute([
    'evento' => (string) ($payload['event'] ?? $payload['tipo'] ?? 'payment'),
    'status' => $status,
    'pedido_id' => $pedidoId ?: null,
    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$eventId = (int) db()->lastInsertId();

$vendaId = 0;
$vendaStatus = $status;
foreach ((array) ($payload['pix'] ?? []) as $pix) {
    $txidPix = (string) ($pix['txid'] ?? $payload['txid'] ?? '');
    if ($txidPix === '') {
        continue;
    }
    $vendaPix = db_one("SELECT id FROM vendas WHERE pix_txid = :txid", ['txid' => $txidPix]);
    if ($vendaPix) {
        $vendaId = (int) $vendaPix['id'];
    } elseif (preg_match('/\bPDV(\d+)\b/', $txidPix, $matches)) {
        $vendaId = (int) $matches[1];
    }
    if ($vendaId > 0) {
        $vendaStatus = (string) ($pix['status'] ?? $status);
        break;
    }
}

$paids = pdv_efi_paid_statuses();
$processou = false;

if ($pedidoId > 0) {
    efi_apply_paid_order($pedidoId, $status, $payload);
    $processou = true;
}

if ($vendaId > 0) {
    pdv_apply_efi_payment($vendaId, in_array($vendaStatus, $paids, true), $payload);
    $processou = true;
}

if ($processou) {
    db()->prepare("UPDATE webhook_eventos SET processado = 1 WHERE id = :id")->execute(['id' => $eventId]);
}

echo json_encode(['ok' => true, 'pedido_id' => $pedidoId, 'venda_id' => $vendaId]);
