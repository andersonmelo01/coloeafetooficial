<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$usuario = mobile_require_user();

$q = trim((string) api_query('q', ''));
$categoriaId = (int) api_query('categoria', 0);
$perPage = min(100, max(1, (int) api_query('per_page', 30)));
$page = max(1, (int) api_query('pagina', 1));
$order = (string) api_query('order', 'nome');
$order = in_array($order, ['nome', 'menor_preco', 'maior_preco', 'destaque'], true) ? $order : 'nome';

$params = [
    'q' => $q,
    'like_q' => '%' . $q . '%',
    'categoria_id' => $categoriaId,
];
$where = ['p.ativo = 1'];
$where[] = '(p.nome LIKE :like_q OR p.sku LIKE :like_q OR p.descricao_curta LIKE :like_q)';
if ($categoriaId > 0) {
    $where[] = 'p.categoria_id = :categoria_id';
}

$orderSql = match ($order) {
    'menor_preco' => 'COALESCE(NULLIF(pr.preco_promocional, 0), NULLIF(p.preco_promocional, 0), p.preco) ASC',
    'maior_preco' => 'COALESCE(NULLIF(pr.preco_promocional, 0), NULLIF(p.preco_promocional, 0), p.preco) DESC',
    'destaque' => 'p.destaque DESC, p.nome ASC',
    default => 'p.nome ASC',
};

$baseSql = "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
                   pr.preco_promocional AS promo_preco,
                   pr.percentual_desconto AS promo_percentual,
                   (SELECT pi.caminho
                    FROM produto_imagens pi
                    WHERE pi.produto_id = p.id
                    ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
                    LIMIT 1) AS imagem_principal
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN grupos_produtos g ON g.id = p.grupo_id
            LEFT JOIN promocoes pr ON pr.produto_id = p.id
               AND pr.ativo = 1
               AND (pr.data_inicio IS NULL OR pr.data_inicio <= CURDATE())
               AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())
               AND pr.id = (
                   SELECT pr2.id FROM promocoes pr2
                   WHERE pr2.produto_id = p.id AND pr2.ativo = 1
                   ORDER BY pr2.id DESC LIMIT 1
               )
            WHERE " . implode(' AND ', $where) . "
            ORDER BY " . $orderSql;

$total = (int) (db_one(
    "SELECT COUNT(*) AS total FROM ({$baseSql}) contagem",
    $params
)['total'] ?? 0);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare($baseSql . ' LIMIT :__limit OFFSET :__offset');
foreach ($params as $key => $value) {
    $param = ':' . ltrim((string) $key, ':');
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($param, $value, $type);
}
$stmt->bindValue(':__limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':__offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$produtos = [];
foreach ($stmt->fetchAll() as $p) {
    $preco = promotion_price($p);
    $estoqueControle = pdv_stock_control();
    $produtos[] = [
        'id' => (int) $p['id'],
        'nome' => (string) $p['nome'],
        'sku' => (string) $p['sku'],
        'descricao_curta' => (string) ($p['descricao_curta'] ?? ''),
        'categoria' => (string) ($p['categoria'] ?? ''),
        'grupo' => (string) ($p['grupo'] ?? ''),
        'preco' => (float) $p['preco'],
        'preco_atual' => $preco,
        'promocional' => !empty($p['promo_preco']) || !empty($p['promo_percentual']) || !empty($p['preco_promocional']),
        'estoque' => (int) $p['estoque'],
        'controle_estoque' => $estoqueControle,
        'sem_estoque' => $estoqueControle && (int) $p['estoque'] <= 0,
        'destaque' => (int) $p['destaque'] === 1,
        'imagem' => !empty($p['imagem_principal']) ? mobile_absolute((string) $p['imagem_principal']) : null,
        'preco_formatado' => money_br($preco),
        'preco_original_formatado' => money_br((float) $p['preco']),
    ];
}

api_json([
    'ok' => true,
    'produtos' => $produtos,
    'total' => $total,
    'pagina' => $page,
    'pages' => $pages,
    'per_page' => $perPage,
]);