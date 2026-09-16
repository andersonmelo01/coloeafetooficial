<?php
declare(strict_types=1);

/*
 * Bootstrap da API do PDV Mobile.
 * Reutiliza as regras de negocio do projeto original (admin/vendas/_pdv.php,
 * includes/functions.php, includes/CupomService.php e includes/EfiService.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/admin/vendas/_pdv.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';
require_once dirname(__DIR__, 2) . '/includes/EfiService.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    ensure_database_schema();
} catch (Throwable $e) {
    api_error('Nao foi possivel preparar o banco de dados: ' . $e->getMessage(), 500);
}

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS mobile_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        criado_em DATETIME NOT NULL,
        expira_em DATETIME NOT NULL,
        ultimo_uso DATETIME DEFAULT NULL,
        INDEX idx_mobile_tokens_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

function api_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $message, int $status = 400): never
{
    api_json(['ok' => false, 'error' => $message], $status);
}

function api_body(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function api_query(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function mobile_bearer_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if ($header === '' && function_exists('getallheaders')) {
        foreach ((getallheaders() ?: []) as $key => $value) {
            if (strcasecmp((string) $key, 'Authorization') === 0) {
                $header = (string) $value;
                break;
            }
        }
    }
    if (preg_match('/Bearer\s+([A-Za-z0-9]+)/', $header, $matches)) {
        return $matches[1];
    }
    return '';
}

function mobile_money($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    if (is_int($value) || is_float($value)) {
        return round((float) $value, 2);
    }
    if (is_numeric($value)) {
        return round((float) $value, 2);
    }
    return round(pdv_money_to_float((string) $value), 2);
}

/**
 * Normaliza datas vindas do app (dd/mm/aaaa ou aaaa-mm-dd) para o formato
 * aceito pelo MySQL (aaaa-mm-dd). Retorna '' quando nao for uma data valida.
 */
function mobile_date_mysql(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
        $dia = (int) $m[1];
        $mes = (int) $m[2];
        $ano = (int) $m[3];
        if (checkdate($mes, $dia, $ano)) {
            return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        }
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
        if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
    }

    return '';
}

function mobile_config(): array
{
    $pixStatus = efi_can_charge_method('pix_qrcode');
    $cartaoStatus = efi_can_charge_method('cartao_credito');

    return [
        'controle_estoque' => pdv_stock_control(),
        'fiscal_habilitado' => function_exists('pdv_fiscal_mode') ? pdv_fiscal_mode() : fiscal_enabled(),
        'efi_pix' => efi_pix_enabled(),
        'efi_pix_automatico' => $pixStatus['ok'] ?? false,
        'efi_cartao' => efi_card_enabled(),
        'efi_cartao_automatico' => $cartaoStatus['ok'] ?? false,
        'metodos_pagamento' => pdv_payment_methods(),
        'nome_estabelecimento' => (string) (app_config('fiscal.razao_social', '') ?: 'Colo & Afeto'),
    ];
}

function mobile_absolute(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . base_url($path);
}

function mobile_user_can_vendas(int $userId): bool
{
    $usuario = db_one(
        "SELECT id, perfil_admin_id, ativo FROM usuarios WHERE id = :id AND tipo = 'admin'",
        ['id' => $userId]
    );
    if (!$usuario || (int) $usuario['ativo'] !== 1) {
        return false;
    }
    if (empty($usuario['perfil_admin_id'])) {
        return true;
    }
    $perfil = db_one(
        "SELECT administrador, ativo FROM admin_perfis WHERE id = :id",
        ['id' => (int) $usuario['perfil_admin_id']]
    );
    if (!$perfil || (int) $perfil['ativo'] !== 1) {
        return false;
    }
    if ((int) $perfil['administrador'] === 1) {
        return true;
    }
    $permissao = db_one(
        "SELECT id FROM admin_perfil_permissoes
         WHERE perfil_id = :perfil_id AND permissao = 'vendas'
         LIMIT 1",
        ['perfil_id' => (int) $usuario['perfil_admin_id']]
    );
    return (bool) $permissao;
}

function mobile_require_user(): array
{
    $token = mobile_bearer_token();
    if ($token === '') {
        api_error('Nao autorizado. Faca o login novamente.', 401);
    }

    $linha = db_one(
        "SELECT * FROM mobile_tokens WHERE token_hash = :hash AND expira_em > NOW()",
        ['hash' => hash('sha256', $token)]
    );
    if (!$linha) {
        api_error('Sessao expirada. Faca o login novamente.', 401);
    }

    if (!mobile_user_can_vendas((int) $linha['usuario_id'])) {
        api_error('Usuario sem permissao para o modulo de vendas.', 403);
    }

    $usuario = db_one(
        "SELECT id, nome, email, tipo FROM usuarios WHERE id = :id AND ativo = 1",
        ['id' => (int) $linha['usuario_id']]
    );
    if (!$usuario) {
        api_error('Usuario nao encontrado.', 401);
    }

    db()->prepare("UPDATE mobile_tokens SET ultimo_uso = NOW() WHERE id = :id")
        ->execute(['id' => (int) $linha['id']]);

    return [
        'id' => (int) $usuario['id'],
        'nome' => (string) $usuario['nome'],
        'email' => (string) $usuario['email'],
        'tipo' => (string) $usuario['tipo'],
    ];
}