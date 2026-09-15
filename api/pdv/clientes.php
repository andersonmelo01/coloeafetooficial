<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

mobile_require_user();

$q = trim((string) api_query('q', ''));
$limit = min(200, max(1, (int) api_query('limit', 100)));

$sql = "SELECT id, nome, email
        FROM usuarios
        WHERE tipo = 'cliente' AND ativo = 1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (nome LIKE :like_q OR email LIKE :like_q)';
    $params['like_q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY nome ASC LIMIT :__limit';

$stmt = db()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':__limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$clientes = array_map(static function (array $c) {
    $label = trim((string) $c['nome']);
    $email = trim((string) $c['email']);
    if ($email !== '') {
        $label .= ' · ' . $email;
    }
    return [
        'id' => (int) $c['id'],
        'nome' => (string) $c['nome'],
        'email' => (string) $c['email'],
        'label' => $label,
    ];
}, $stmt->fetchAll());

api_json(['ok' => true, 'clientes' => $clientes]);