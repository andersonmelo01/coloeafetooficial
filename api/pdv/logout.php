<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Metodo nao permitido.', 405);
}

$usuario = mobile_require_user();

$stmt = db()->prepare("DELETE FROM mobile_tokens WHERE usuario_id = :usuario_id");
$stmt->execute(['usuario_id' => (int) $usuario['id']]);

api_json(['ok' => true, 'message' => 'Sessao encerrada.']);