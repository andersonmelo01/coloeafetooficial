<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

mobile_require_user();

$categorias = db_all(
    "SELECT c.id, c.nome,
            (SELECT COUNT(*) FROM produtos p WHERE p.categoria_id = c.id AND p.ativo = 1) AS total_produtos
     FROM categorias c
     WHERE c.ativo = 1
     ORDER BY c.nome ASC"
);

api_json([
    'ok' => true,
    'categorias' => array_map(static fn (array $c) => [
        'id' => (int) $c['id'],
        'nome' => (string) $c['nome'],
        'total_produtos' => (int) $c['total_produtos'],
    ], $categorias),
]);