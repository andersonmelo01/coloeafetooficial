<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';

$usuario = mobile_require_user();

$vendaId = (int) api_query('venda', 0);

if ($vendaId > 0) {
    $venda = db_one(
        "SELECT v.*, vendedor.nome AS vendedor, cliente.nome AS cliente, cliente.email AS cliente_email
         FROM vendas v
         LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
         LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
         WHERE v.id = :id",
        ['id' => $vendaId]
    );

    if (!$venda) {
        api_error('Venda nao encontrada.', 404);
    }

    $itens = db_all("SELECT * FROM venda_itens WHERE venda_id = :venda_id ORDER BY id", ['venda_id' => $vendaId]);
    $pagamentos = db_all("SELECT * FROM venda_pagamentos WHERE venda_id = :venda_id ORDER BY id", ['venda_id' => $vendaId]);
    $parcelas = db_all("SELECT * FROM venda_parcelas WHERE venda_id = :venda_id ORDER BY parcela", ['venda_id' => $vendaId]);

    $temPendenteOnline = count(array_filter($pagamentos, static fn (array $pg) => in_array($pg['metodo'], ['pix', 'cartao_credito', 'cartao_debito'], true) && $pg['status'] === 'pendente')) > 0;

    api_json([
        'ok' => true,
        'venda' => [
            'id' => (int) $venda['id'],
            'numero' => (string) $venda['numero'],
            'status' => (string) $venda['status'],
            'subtotal' => (float) $venda['subtotal'],
            'desconto' => (float) $venda['desconto'],
            'total' => (float) $venda['total'],
            'subtotal_formatado' => money_br((float) $venda['subtotal']),
            'desconto_formatado' => money_br((float) $venda['desconto']),
            'total_formatado' => money_br((float) $venda['total']),
            'observacao' => (string) ($venda['observacao'] ?? ''),
            'vendedor' => (string) ($venda['vendedor'] ?? ''),
            'cliente' => (string) ($venda['cliente'] ?? ''),
            'cliente_email' => (string) ($venda['cliente_email'] ?? ''),
            'email_recibo' => (string) ($venda['email_recibo'] ?? ''),
            'cupom_no' => (string) ($venda['cupom_no'] ?? ''),
            'nfce_chave' => (string) ($venda['nfce_chave'] ?? ''),
            'finalizada_em' => (string) ($venda['finalizada_em'] ?? $venda['criado_em']),
            'cancelada_em' => isset($venda['cancelada_em']) ? (string) $venda['cancelada_em'] : null,
            'motivo_cancelamento' => (string) ($venda['motivo_cancelamento'] ?? ''),
            'pix_copiaecola' => (string) ($venda['pix_copiaecola'] ?? ''),
            'pix_qrcode' => (string) ($venda['pix_qrcode'] ?? ''),
            'pix_txid' => (string) ($venda['pix_txid'] ?? ''),
            'tem_pendente_online' => $temPendenteOnline,
        ],
        'itens' => array_map(static fn (array $i) => [
            'id' => (int) $i['id'],
            'produto_id' => (int) $i['produto_id'],
            'nome' => (string) $i['nome_produto'],
            'quantidade' => (int) $i['quantidade'],
            'preco_unitario' => (float) $i['preco_unitario'],
            'preco_unitario_formatado' => money_br((float) $i['preco_unitario']),
            'total' => (float) $i['total'],
            'total_formatado' => money_br((float) $i['total']),
        ], $itens),
        'pagamentos' => array_map(static fn (array $p) => [
            'id' => (int) $p['id'],
            'metodo' => (string) $p['metodo'],
            'metodo_label' => pdv_payment_label((string) $p['metodo']),
            'valor' => (float) $p['valor'],
            'valor_formatado' => money_br((float) $p['valor']),
            'status' => (string) $p['status'],
            'pago_em' => isset($p['pago_em']) ? (string) $p['pago_em'] : null,
        ], $pagamentos),
        'parcelas' => array_map(static fn (array $pa) => [
            'id' => (int) $pa['id'],
            'parcela' => (int) $pa['parcela'],
            'vencimento' => (string) $pa['vencimento'],
            'valor' => (float) $pa['valor'],
            'valor_formatado' => money_br((float) $pa['valor']),
            'status' => (string) $pa['status'],
            'pago_em' => isset($pa['pago_em']) ? (string) $pa['pago_em'] : null,
        ], $parcelas),
        'total_parcelas' => count($parcelas),
    ]);
}

$statusFiltro = (string) api_query('status', '');
if (!in_array($statusFiltro, ['finalizada', 'pendente', 'cancelada', 'aberta'], true)) {
    $statusFiltro = '';
}

$perPage = min(50, max(1, (int) api_query('per_page', 20)));
$page = max(1, (int) api_query('pagina', 1));

$params = ['status' => $statusFiltro];
$where = '(:status = \'\' OR v.status = :status)';

$total = (int) (db_one(
    "SELECT COUNT(*) AS total FROM vendas v WHERE " . $where,
    $params
)['total'] ?? 0);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT v.*, vendedor.nome AS vendedor, cliente.nome AS cliente
     FROM vendas v
     LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
     LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
     WHERE " . $where . "
     ORDER BY v.id DESC
     LIMIT :__limit OFFSET :__offset"
);
$stmt->bindValue(':status', $statusFiltro ?: '', PDO::PARAM_STR);
$stmt->bindValue(':__limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':__offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$vendas = array_map(static fn (array $v) => [
    'id' => (int) $v['id'],
    'numero' => (string) $v['numero'],
    'status' => (string) $v['status'],
    'total' => (float) $v['total'],
    'total_formatado' => money_br((float) $v['total']),
    'cliente' => (string) ($v['cliente'] ?? ''),
    'vendedor' => (string) ($v['vendedor'] ?? ''),
    'data' => (string) ($v['finalizada_em'] ?? $v['criado_em']),
], $stmt->fetchAll());

api_json([
    'ok' => true,
    'vendas' => $vendas,
    'total' => $total,
    'pagina' => $page,
    'pages' => $pages,
]);