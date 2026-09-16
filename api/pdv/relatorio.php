<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

mobile_require_user();

function rel_money(float $value): string
{
    return money_br($value);
}

$hoje = date('Y-m-d');
$inicio = mobile_date_mysql((string) api_query('inicio', $hoje));
$fim = mobile_date_mysql((string) api_query('fim', $hoje));
if ($inicio === '') {
    $inicio = $hoje;
}
if ($fim === '') {
    $fim = $inicio;
}
if (strtotime($fim) < strtotime($inicio)) {
    [$inicio, $fim] = [$fim, $inicio];
}

$statusLabels = [
    'ativas' => 'Vendas ativas',
    'todas' => 'Todas (inclui canceladas)',
    'finalizada' => 'Finalizadas',
    'pendente' => 'Pendentes',
    'cancelada' => 'Canceladas',
];
$status = (string) api_query('status', 'ativas');
if (!array_key_exists($status, $statusLabels)) {
    $status = 'ativas';
}

$metodo = trim((string) api_query('metodo', ''));
if ($metodo !== '' && !array_key_exists($metodo, pdv_payment_methods())) {
    $metodo = '';
}

$vendedor = (int) api_query('vendedor', 0);
$cliente = (int) api_query('cliente', 0);

$conditions = [];
$filterParams = [];

if ($vendedor > 0) {
    $conditions[] = 'v.usuario_id = :vendedor';
    $filterParams['vendedor'] = $vendedor;
}
if ($cliente === -1) {
    $conditions[] = 'v.cliente_id IS NULL';
} elseif ($cliente > 0) {
    $conditions[] = 'v.cliente_id = :cliente';
    $filterParams['cliente'] = $cliente;
}
if ($metodo !== '') {
    $conditions[] = 'EXISTS (SELECT 1 FROM venda_pagamentos vpf WHERE vpf.venda_id = v.id AND vpf.metodo = :metodo)';
    $filterParams['metodo'] = $metodo;
}

$statusCondition = '';
if ($status === 'ativas') {
    $statusCondition = "v.status <> 'cancelada'";
} elseif (in_array($status, ['finalizada', 'pendente', 'cancelada'], true)) {
    $statusCondition = 'v.status = :status_filtro';
}

$dateCondition = 'COALESCE(v.finalizada_em, v.criado_em) BETWEEN :inicio AND :fim';
$baseParams = array_merge($filterParams, [
    'inicio' => $inicio . ' 00:00:00',
    'fim' => $fim . ' 23:59:59',
]);
$params = $baseParams;
if ($statusCondition === 'v.status = :status_filtro') {
    $params['status_filtro'] = $status;
}

$where = implode(' AND ', array_merge(
    $statusCondition !== '' ? [$statusCondition] : [],
    $conditions,
    [$dateCondition]
));

$canceladaWhere = implode(' AND ', array_merge(
    array_filter($conditions, static fn (string $c): bool => !str_starts_with($c, 'v.status')),
    [$dateCondition]
));

$kpis = db_one(
    "SELECT COUNT(*) AS vendas,
            COALESCE(SUM(v.total), 0) AS faturamento,
            COALESCE(SUM(v.subtotal), 0) AS subtotal,
            COALESCE(SUM(v.desconto), 0) AS descontos,
            COALESCE(AVG(v.total), 0) AS ticket_medio
     FROM vendas v
     WHERE " . $where,
    $params
) ?? ['vendas' => 0, 'faturamento' => 0.0, 'subtotal' => 0.0, 'descontos' => 0.0, 'ticket_medio' => 0.0];

$itens = db_one(
    "SELECT COALESCE(SUM(vi.quantidade), 0) AS itens
     FROM venda_itens vi
     JOIN vendas v ON v.id = vi.venda_id
     WHERE " . $where,
    $params
) ?? ['itens' => 0];

$canceladas = db_one(
    "SELECT COUNT(*) AS n, COALESCE(SUM(v.total), 0) AS valor
     FROM vendas v
     WHERE v.status = 'cancelada' AND " . $canceladaWhere,
    $baseParams
) ?? ['n' => 0, 'valor' => 0.0];

$porMetodo = db_all(
    "SELECT vp.metodo,
            COUNT(DISTINCT vp.venda_id) AS vendas,
            COALESCE(SUM(CASE WHEN vp.status = 'pago' THEN vp.valor END), 0) AS pago,
            COALESCE(SUM(CASE WHEN vp.status = 'pendente' THEN vp.valor END), 0) AS pendente,
            COALESCE(SUM(vp.valor), 0) AS total
     FROM venda_pagamentos vp
     JOIN vendas v ON v.id = vp.venda_id
     WHERE " . $where . "
     GROUP BY vp.metodo
     ORDER BY total DESC",
    $params
);

$porVendedor = db_all(
    "SELECT u.nome AS vendedor, COUNT(*) AS vendas,
            COALESCE(SUM(v.total), 0) AS valor,
            COALESCE(AVG(v.total), 0) AS ticket_medio
     FROM vendas v
     JOIN usuarios u ON u.id = v.usuario_id
     WHERE " . $where . "
     GROUP BY u.id, u.nome
     ORDER BY valor DESC",
    $params
);

$topProdutos = db_all(
    "SELECT vi.nome_produto AS nome,
            COALESCE(SUM(vi.quantidade), 0) AS quantidade,
            COALESCE(SUM(vi.total), 0) AS valor
     FROM venda_itens vi
     JOIN vendas v ON v.id = vi.venda_id
     WHERE " . $where . "
     GROUP BY vi.nome_produto
     ORDER BY quantidade DESC, valor DESC
     LIMIT 10",
    $params
);

$topClientes = db_all(
    "SELECT COALESCE(c.nome, 'Venda sem cliente') AS cliente,
            COUNT(*) AS vendas,
            COALESCE(SUM(v.total), 0) AS valor
     FROM vendas v
     LEFT JOIN usuarios c ON c.id = v.cliente_id
     WHERE " . $where . "
     GROUP BY c.id, c.nome
     ORDER BY valor DESC
     LIMIT 10",
    $params
);

$crediario = db_one(
    "SELECT COUNT(*) AS parcelas,
            COALESCE(SUM(vp2.valor), 0) AS a_receber,
            COALESCE(SUM(CASE WHEN vp2.status = 'pago' THEN vp2.valor END), 0) AS recebido
     FROM venda_parcelas vp2
     JOIN vendas v ON v.id = vp2.venda_id
     WHERE " . $where,
    $params
) ?? ['parcelas' => 0, 'a_receber' => 0.0, 'recebido' => 0.0];

$vendas = db_all(
    "SELECT v.id, v.numero, v.status, v.total,
            cliente.nome AS cliente, vendedor.nome AS vendedor,
            COALESCE(v.finalizada_em, v.criado_em) AS data
     FROM vendas v
     LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
     LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
     WHERE " . $where . "
     ORDER BY v.id DESC
     LIMIT 200",
    $params
);

$vendedores = db_all(
    "SELECT DISTINCT u.id, u.nome
     FROM vendas v
     JOIN usuarios u ON u.id = v.usuario_id
     ORDER BY u.nome"
);

$clientes = db_all(
    "SELECT DISTINCT
            CASE WHEN v.cliente_id IS NULL THEN 0 ELSE v.cliente_id END AS id,
            CASE WHEN v.cliente_id IS NULL THEN 'Venda sem cliente' ELSE COALESCE(c.nome, CONCAT('Cliente #', v.cliente_id)) END AS nome
     FROM vendas v
     LEFT JOIN usuarios c ON c.id = v.cliente_id
     ORDER BY nome"
);

$metodosOpcoes = [];
foreach (pdv_payment_methods() as $slug => $label) {
    $metodosOpcoes[] = ['slug' => $slug, 'label' => $label];
}

api_json([
    'ok' => true,
    'periodo' => [
        'inicio' => $inicio,
        'fim' => $fim,
        'inicio_formatado' => date('d/m/Y', strtotime($inicio)),
        'fim_formatado' => date('d/m/Y', strtotime($fim)),
    ],
    'filtros' => [
        'status' => $status,
        'metodo' => $metodo,
        'vendedor' => $vendedor,
        'cliente' => $cliente,
    ],
    'kpis' => [
        'vendas' => (int) $kpis['vendas'],
        'faturamento' => round((float) $kpis['faturamento'], 2),
        'subtotal' => round((float) $kpis['subtotal'], 2),
        'descontos' => round((float) $kpis['descontos'], 2),
        'ticket_medio' => round((float) $kpis['ticket_medio'], 2),
        'itens' => (int) $itens['itens'],
        'faturamento_formatado' => rel_money((float) $kpis['faturamento']),
        'subtotal_formatado' => rel_money((float) $kpis['subtotal']),
        'descontos_formatado' => rel_money((float) $kpis['descontos']),
        'ticket_medio_formatado' => rel_money((float) $kpis['ticket_medio']),
    ],
    'canceladas' => [
        'n' => (int) $canceladas['n'],
        'valor' => round((float) $canceladas['valor'], 2),
        'valor_formatado' => rel_money((float) $canceladas['valor']),
    ],
    'por_metodo' => array_map(static fn (array $m) => [
        'metodo' => (string) $m['metodo'],
        'metodo_label' => pdv_payment_label((string) $m['metodo']),
        'vendas' => (int) $m['vendas'],
        'pago' => round((float) $m['pago'], 2),
        'pendente' => round((float) $m['pendente'], 2),
        'total' => round((float) $m['total'], 2),
        'pago_formatado' => rel_money((float) $m['pago']),
        'pendente_formatado' => rel_money((float) $m['pendente']),
        'total_formatado' => rel_money((float) $m['total']),
    ], $porMetodo),
    'por_vendedor' => array_map(static fn (array $v) => [
        'vendedor' => (string) ($v['vendedor'] ?? ''),
        'vendas' => (int) $v['vendas'],
        'valor' => round((float) $v['valor'], 2),
        'ticket_medio' => round((float) $v['ticket_medio'], 2),
        'valor_formatado' => rel_money((float) $v['valor']),
        'ticket_medio_formatado' => rel_money((float) $v['ticket_medio']),
    ], $porVendedor),
    'top_produtos' => array_map(static fn (array $p) => [
        'nome' => (string) $p['nome'],
        'quantidade' => (int) $p['quantidade'],
        'valor' => round((float) $p['valor'], 2),
        'valor_formatado' => rel_money((float) $p['valor']),
    ], $topProdutos),
    'top_clientes' => array_map(static fn (array $c) => [
        'cliente' => (string) $c['cliente'],
        'vendas' => (int) $c['vendas'],
        'valor' => round((float) $c['valor'], 2),
        'valor_formatado' => rel_money((float) $c['valor']),
    ], $topClientes),
    'crediario' => [
        'parcelas' => (int) $crediario['parcelas'],
        'a_receber' => round((float) $crediario['a_receber'], 2),
        'recebido' => round((float) $crediario['recebido'], 2),
        'a_receber_formatado' => rel_money((float) $crediario['a_receber']),
        'recebido_formatado' => rel_money((float) $crediario['recebido']),
    ],
    'vendas' => array_map(static fn (array $v) => [
        'id' => (int) $v['id'],
        'numero' => (string) $v['numero'],
        'status' => (string) $v['status'],
        'total' => round((float) $v['total'], 2),
        'total_formatado' => rel_money((float) $v['total']),
        'cliente' => (string) ($v['cliente'] ?? ''),
        'vendedor' => (string) ($v['vendedor'] ?? ''),
        'data' => (string) $v['data'],
    ], $vendas),
    'opcoes' => [
        'status' => array_map(static fn (string $valor, string $label) => ['valor' => $valor, 'label' => $label], array_keys($statusLabels), array_values($statusLabels)),
        'metodos' => $metodosOpcoes,
        'vendedores' => array_map(static fn (array $v) => ['id' => (int) $v['id'], 'nome' => (string) $v['nome']], $vendedores),
        'clientes' => array_map(static fn (array $c) => ['id' => (int) $c['id'], 'nome' => (string) $c['nome']], $clientes),
    ],
]);
