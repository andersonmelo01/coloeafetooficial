<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Metodo nao permitido.', 405);
}

$body = api_body();
$email = trim((string) ($body['email'] ?? ''));
$senha = (string) ($body['senha'] ?? '');

if ($email === '' || $senha === '') {
    api_error('Informe e-mail e senha.');
}

$usuario = db_one(
    "SELECT * FROM usuarios WHERE email = :email AND ativo = 1",
    ['email' => $email]
);

if (!$usuario || !password_verify($senha, (string) $usuario['senha_hash'])) {
    api_error('E-mail ou senha invalidos.', 401);
}

if (($usuario['tipo'] ?? '') !== 'admin') {
    api_error('Acesso restrito a operadores do PDV.', 403);
}

if (!mobile_user_can_vendas((int) $usuario['id'])) {
    api_error('Usuario sem permissao para o modulo de vendas.', 403);
}

$token = bin2hex(random_bytes(32));
$pdo = db();
$stmt = $pdo->prepare(
    "INSERT INTO mobile_tokens (usuario_id, token_hash, criado_em, expira_em)
     VALUES (:usuario_id, :token_hash, NOW(), DATE_ADD(NOW(), INTERVAL 45 DAY))"
);
$stmt->execute([
    'usuario_id' => (int) $usuario['id'],
    'token_hash' => hash('sha256', $token),
]);

$caixa = pdv_caixa_aberto();

api_json([
    'ok' => true,
    'token' => $token,
    'usuario' => [
        'id' => (int) $usuario['id'],
        'nome' => (string) $usuario['nome'],
        'email' => (string) $usuario['email'],
    ],
    'config' => mobile_config(),
    'caixa' => $caixa ? [
        'id' => (int) $caixa['id'],
        'aberto_em' => (string) $caixa['aberto_em'],
        'saldo' => pdv_caixa_saldo($caixa),
    ] : null,
]);