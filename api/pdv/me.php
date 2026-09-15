<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$usuario = mobile_require_user();
$caixa = pdv_caixa_aberto();

api_json([
    'ok' => true,
    'usuario' => $usuario,
    'config' => mobile_config(),
    'caixa' => $caixa ? [
        'id' => (int) $caixa['id'],
        'aberto_em' => (string) $caixa['aberto_em'],
        'saldo' => pdv_caixa_saldo($caixa),
    ] : null,
]);