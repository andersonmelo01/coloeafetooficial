<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';

$usuario = mobile_require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Metodo nao permitido.', 405);
}

$body = api_body();
$id = (int) ($body['venda_id'] ?? 0);
$email = trim((string) ($body['email'] ?? ''));

if ($id <= 0) {
    api_error('Venda invalida.');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('E-mail invalido.');
}

$result = pdv_cupom_send_email($id, $email);
if ($result['ok']) {
    api_json(['ok' => true, 'message' => $result['message'] ?? 'Cupom enviado.']);
}
api_error($result['message'] ?? 'Nao foi possivel enviar o cupom.', 400);