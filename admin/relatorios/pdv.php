<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__) . '/vendas/_pdv.php';
require_login('admin');

$period = rel_period();
$inicio = $period['inicio'];
$fim = $period['fim'];

$fVendedor = (int) ($_GET['vendedor'] ?? 0);
$fCliente = (int) ($_GET['cliente'] ?? 0);
$fStatus = (string) ($_GET['status'] ?? 'ativas');
$fMetodo = trim((string) ($_GET['metodo'] ?? ''));
$fCaixa = (int) ($_GET['caixa'] ?? 0);

$statusLabels = [
    'ativas' => 'Vendas ativas',
    'todas' => 'Todas (inclui canceladas)',
    'finalizada' => 'Finalizadas',
    'pendente' => 'Pendentes',
    'cancelada' => 'Canceladas',
];
if (!array_key_exists($fStatus, $statusLabels)) {
    $fStatus = 'ativas';
}
if ($fMetodo !== '' && !array_key_exists($fMetodo, pdv_payment_methods())) {
    $fMetodo = '';
}

$conditions = [];
$filterParams = [];
if ($fVendedor > 0) {
    $conditions[] = 'v.usuario_id = :vendedor';
    $filterParams['vendedor'] = $fVendedor;
}
if ($fCliente === -1) {
    $conditions[] = 'v.cliente_id IS NULL';
} elseif ($fCliente > 0) {
    $conditions[] = 'v.cliente_id = :cliente';
    $filterParams['cliente'] = $fCliente;
}
if ($fCaixa > 0) {
    $conditions[] = 'v.caixa_id = :caixa';
    $filterParams['caixa'] = $fCaixa;
}
if ($fMetodo !== '') {
    $conditions[] = 'EXISTS (SELECT 1 FROM venda_pagamentos vpf WHERE vpf.venda_id = v.id AND vpf.metodo = :metodo)';
    $filterParams['metodo'] = $fMetodo;
}

$statusCondition = '';
if ($fStatus === 'ativas') {
    $statusCondition = "v.status <> 'cancelada'";
} elseif (in_array($fStatus, ['finalizada', 'pendente', 'cancelada'], true)) {
    $statusCondition = 'v.status = :status';
    $filterParams['status'] = $fStatus;
}

$len = (int) ((strtotime($fim) - strtotime($inicio)) / 86400) + 1;
$prevFim = date('Y-m-d', strtotime($inicio . ' -1 day'));
$prevInicio = date('Y-m-d', strtotime($prevFim . ' -' . ($len - 1) . ' days'));

$allConditions = array_values(array_filter(array_merge($statusCondition !== '' ? [$statusCondition] : [], $conditions)));
$vendaDataConditions = array_merge($allConditions, ["COALESCE(v.finalizada_em, v.criado_em) BETWEEN :inicio AND :fim"]);
$vendaWhere = implode(' AND ', $vendaDataConditions);
$params = array_merge($period['params'], $filterParams);

$prevVendaWhere = implode(' AND ', array_merge($allConditions, ["COALESCE(v.finalizada_em, v.criado_em) BETWEEN :inicio AND :fim"]));
$prevParams = array_merge(['inicio' => $prevInicio . ' 00:00:00', 'fim' => $prevFim . ' 23:59:59'], $filterParams);

$canceladaConditions = array_values(array_filter($conditions, static fn (string $c) => !str_starts_with($c, 'v.status')));
$canceladaWhere = implode(' AND ', array_merge($canceladaConditions, [$vendaDataConditions[array_key_last($vendaDataConditions)]]));
$canceladaParams = array_merge($period['params'], array_filter($filterParams, static fn (string $k) => $k !== 'status', ARRAY_FILTER_USE_KEY));
$mostrarCancelamentos = !in_array($fStatus, ['finalizada', 'pendente'], true);

$kpis = db_one(
    "SELECT COUNT(*) AS n,
            COALESCE(SUM(v.total), 0) AS faturamento,
            COALESCE(SUM(v.subtotal), 0) AS subtotal,
            COALESCE(SUM(v.desconto), 0) AS descontos,
            COALESCE(AVG(v.total), 0) AS ticket
     FROM vendas v
     WHERE $vendaWhere",
    $params
) ?? ['n' => 0, 'faturamento' => 0.0, 'subtotal' => 0.0, 'descontos' => 0.0, 'ticket' => 0.0];

$kpiItens = db_one(
    "SELECT COALESCE(SUM(vi.quantidade), 0) AS itens
     FROM venda_itens vi
     JOIN vendas v ON v.id = vi.venda_id
     WHERE $vendaWhere",
    $params
) ?? ['itens' => 0];

$canceladas = $mostrarCancelamentos
    ? (db_one(
        "SELECT COUNT(*) AS n, COALESCE(SUM(v.total), 0) AS valor
         FROM vendas v
         WHERE v.status = 'cancelada' AND $canceladaWhere",
        $canceladaParams
    ) ?? ['n' => 0, 'valor' => 0.0])
    : ['n' => 0, 'valor' => 0.0];

$prevKpis = db_one(
    "SELECT COUNT(*) AS n,
            COALESCE(SUM(v.total), 0) AS faturamento,
            COALESCE(AVG(v.total), 0) AS ticket
     FROM vendas v
     WHERE $prevVendaWhere",
    $prevParams
) ?? ['n' => 0, 'faturamento' => 0.0, 'ticket' => 0.0];

$prevItens = db_one(
    "SELECT COALESCE(SUM(vi.quantidade), 0) AS itens
     FROM venda_itens vi
     JOIN vendas v ON v.id = vi.venda_id
     WHERE $prevVendaWhere",
    $prevParams
) ?? ['itens' => 0];

[$pdvBucket, $pdvBucketSql] = rel_bucket_for_period($inicio, $fim);
$curvaRows = db_all(
    "SELECT " . sprintf($pdvBucketSql, 'v', 'v', 'v', 'v') . " AS bucket,
            COUNT(*) AS n,
            COALESCE(SUM(v.total), 0) AS valor
     FROM vendas v
     WHERE $vendaWhere
     GROUP BY bucket
     ORDER BY bucket",
    $params
);

if ($pdvBucket === 'd') {
    $curva = rel_fill_daily($curvaRows, $inicio, $fim);
} else {
    $map = [];
    foreach ($curvaRows as $row) {
        $map[(string) $row['bucket']] = $row;
    }
    ksort($map);
    $curva = [];
    foreach ($map as $key => $row) {
        $curva[] = [
            'label' => rel_bucket_label($pdvBucket, (string) $key),
            'n' => (int) $row['n'],
            'valor' => (float) $row['valor'],
        ];
    }
}

$curvaTotal = 0.0;
$curvaMelhor = ['label' => '-', 'valor' => 0.0, 'n' => 0];
$curvaDias = max(1, count($curva));
foreach ($curva as $ponto) {
    $curvaTotal += (float) $ponto['valor'];
    if ((float) $ponto['valor'] > $curvaMelhor['valor']) {
        $curvaMelhor = ['label' => $ponto['label'], 'valor' => (float) $ponto['valor'], 'n' => (int) $ponto['n']];
    }
}
$curvaMedia = $curvaTotal / $curvaDias;

$produtosMaisVendidos = db_all(
    "SELECT vi.nome_produto,
            COALESCE(SUM(vi.quantidade), 0) AS quantidade,
            COALESCE(SUM(vi.total), 0) AS valor
     FROM venda_itens vi
     JOIN vendas v ON v.id = vi.venda_id
     WHERE $vendaWhere
     GROUP BY vi.nome_produto
     ORDER BY quantidade DESC, valor DESC
     LIMIT 10",
    $params
);

$pagamentosPorMetodo = db_all(
    "SELECT vp.metodo,
            COUNT(DISTINCT vp.venda_id) AS vendas,
            COALESCE(SUM(CASE WHEN vp.status = 'pago' THEN vp.valor END), 0) AS pago,
            COALESCE(SUM(CASE WHEN vp.status = 'pendente' THEN vp.valor END), 0) AS pendente,
            COALESCE(SUM(vp.valor), 0) AS total
     FROM venda_pagamentos vp
     JOIN vendas v ON v.id = vp.venda_id
     WHERE $vendaWhere
     GROUP BY vp.metodo
     ORDER BY total DESC",
    $params
);

$vendasPorVendedor = db_all(
    "SELECT u.nome AS vendedor, COUNT(*) AS vendas,
            COALESCE(SUM(v.total), 0) AS valor,
            COALESCE(AVG(v.total), 0) AS ticket
     FROM vendas v
     JOIN usuarios u ON u.id = v.usuario_id
     WHERE $vendaWhere
     GROUP BY u.id, u.nome
     ORDER BY valor DESC",
    $params
);

$diasSemanaNomes = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
$diasSemanaRows = db_all(
    "SELECT WEEKDAY(COALESCE(v.finalizada_em, v.criado_em)) AS dow,
            COUNT(*) AS n,
            COALESCE(SUM(v.total), 0) AS valor
     FROM vendas v
     WHERE $vendaWhere
     GROUP BY dow
     ORDER BY dow",
    $params
);
$diasSemana = [];
foreach ($diasSemanaNomes as $idx => $nome) {
    $diasSemana[] = ['label' => $nome, 'n' => 0, 'valor' => 0.0];
}
foreach ($diasSemanaRows as $row) {
    $idx = (int) $row['dow'];
    if (isset($diasSemana[$idx])) {
        $diasSemana[$idx]['n'] = (int) $row['n'];
        $diasSemana[$idx]['valor'] = (float) $row['valor'];
    }
}

$horasRows = db_all(
    "SELECT HOUR(COALESCE(v.finalizada_em, v.criado_em)) AS hora,
            COUNT(*) AS n,
            COALESCE(SUM(v.total), 0) AS valor
     FROM vendas v
     WHERE $vendaWhere
     GROUP BY hora
     ORDER BY hora",
    $params
);
$horas = [];
for ($h = 0; $h < 24; $h++) {
    $horas[] = ['label' => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . 'h', 'n' => 0, 'valor' => 0.0];
}
foreach ($horasRows as $row) {
    $hora = (int) $row['hora'];
    if (isset($horas[$hora])) {
        $horas[$hora]['n'] = (int) $row['n'];
        $horas[$hora]['valor'] = (float) $row['valor'];
    }
}
$horasChart = [];
foreach ($horas as $h) {
    $horasChart[] = ['label' => $h['label'], 'value' => $h['valor'], 'tooltip' => $h['label'] . ' — ' . $h['n'] . ' venda(s) · ' . money_br($h['valor'])];
}

$cancelamentos = $mostrarCancelamentos
    ? db_all(
        "SELECT v.numero,
                DATE(COALESCE(v.cancelada_em, v.criado_em)) AS data_cancelamento,
                u.nome AS operador,
                v.motivo_cancelamento,
                v.total
         FROM vendas v
         LEFT JOIN usuarios u ON u.id = v.cancelada_por
         WHERE v.status = 'cancelada' AND $canceladaWhere
         ORDER BY v.cancelada_em DESC
         LIMIT 30",
        $canceladaParams
    )
    : [];

$topClientes = db_all(
    "SELECT COALESCE(c.nome, 'Venda sem cliente') AS cliente,
            COUNT(*) AS vendas,
            COALESCE(SUM(v.total), 0) AS valor
     FROM vendas v
     LEFT JOIN usuarios c ON c.id = v.cliente_id
     WHERE $vendaWhere
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
     WHERE $vendaWhere",
    $params
) ?? ['parcelas' => 0, 'a_receber' => 0.0, 'recebido' => 0.0];

$vendedorOptions = db_all(
    "SELECT DISTINCT u.id AS id, u.nome AS nome
     FROM vendas v
     JOIN usuarios u ON u.id = v.usuario_id
     ORDER BY u.nome"
);
$clienteOptions = db_all(
    "SELECT DISTINCT
        CASE WHEN v.cliente_id IS NULL THEN 0 ELSE v.cliente_id END AS id,
        CASE WHEN v.cliente_id IS NULL THEN 'Sem cliente cadastrado' ELSE COALESCE(c.nome, 'Cliente #' || v.cliente_id) END AS nome
     FROM vendas v
     LEFT JOIN usuarios c ON c.id = v.cliente_id
     ORDER BY nome"
);
$caixaOptions = db_all(
    "SELECT DISTINCT v.caixa_id AS id, c.aberto_em, c.status
     FROM vendas v
     JOIN caixas c ON c.id = v.caixa_id
     ORDER BY v.caixa_id DESC"
);

$filtrosAtivos = [];
foreach ($vendedorOptions as $op) {
    if ((int) $op['id'] === $fVendedor) {
        $filtrosAtivos[] = 'Vendedor: ' . (string) $op['nome'];
    }
}
if ($fCliente === -1) {
    $filtrosAtivos[] = 'Cliente: sem cliente cadastrado';
} elseif ($fCliente > 0) {
    foreach ($clienteOptions as $op) {
        if ((int) $op['id'] === $fCliente) {
            $filtrosAtivos[] = 'Cliente: ' . (string) $op['nome'];
        }
    }
}
if ($fStatus !== 'ativas') {
    $filtrosAtivos[] = 'Status: ' . $statusLabels[$fStatus];
}
if ($fMetodo !== '') {
    $filtrosAtivos[] = 'Pagamento: ' . pdv_payment_label($fMetodo);
}
if ($fCaixa > 0) {
    $filtrosAtivos[] = 'Caixa: #' . $fCaixa;
}

$pageTitle = 'Relatórios PDV';
$active = 'admin';
$bodyClass = 'reports-page';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5 report-print-shell"><div class="container"><div class="row g-4"><div class="col-lg-3 report-sidebar"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9 report-content">
    <div class="panel-card bg-white p-4 mb-4 report-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div>
                <h1 class="h3 section-title mb-1">Relatórios do PDV Fácil</h1>
                <p class="text-secondary mb-0">Vendas presenciais. Periodo de <?= e(date('d/m/Y', strtotime($inicio))) ?> ate <?= e(date('d/m/Y', strtotime($fim))) ?>.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <a class="btn btn-outline-brand" href="<?= e(base_url('admin/relatorios/index.php')) ?>">Relatorios gerenciais</a>
                <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            </div>
        </div>
        <form method="get" class="row g-2 align-items-end no-print">
            <div class="col-auto"><label class="form-label small">Inicio</label><input class="form-control" type="date" name="inicio" value="<?= e($inicio) ?>"></div>
            <div class="col-auto"><label class="form-label small">Fim</label><input class="form-control" type="date" name="fim" value="<?= e($fim) ?>"></div>
            <div class="col-auto"><label class="form-label small">Vendedor</label>
                <select class="form-select" name="vendedor">
                    <option value="0">Todos</option>
                    <?php foreach ($vendedorOptions as $op): ?>
                        <option value="<?= (int) $op['id'] ?>" <?= $fVendedor === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><label class="form-label small">Cliente</label>
                <select class="form-select" name="cliente">
                    <option value="0">Todos</option>
                    <option value="-1" <?= $fCliente === -1 ? 'selected' : '' ?>>Venda sem cliente</option>
                    <?php foreach ($clienteOptions as $op): ?>
                        <option value="<?= (int) $op['id'] ?>" <?= $fCliente === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><label class="form-label small">Status</label>
                <select class="form-select" name="status">
                    <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= $fStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><label class="form-label small">Pagamento</label>
                <select class="form-select" name="metodo">
                    <option value="">Todos</option>
                    <?php foreach (pdv_payment_methods() as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $fMetodo === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($caixaOptions): ?>
            <div class="col-auto"><label class="form-label small">Caixa</label>
                <select class="form-select" name="caixa">
                    <option value="0">Todos</option>
                    <?php foreach ($caixaOptions as $cx): ?>
                        <option value="<?= (int) $cx['id'] ?>" <?= $fCaixa === (int) $cx['id'] ? 'selected' : '' ?>>Caixa #<?= (int) $cx['id'] ?> (<?= e((string) $cx['status']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto"><button class="btn btn-brand"><i class="bi bi-search"></i> Filtrar</button></div>
            <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= e(base_url('admin/relatorios/pdv.php')) ?>">Limpar</a></div>
        </form>
        <?php if ($filtrosAtivos): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 no-print">
            <span class="small text-secondary fw-bold"><i class="bi bi-funnel"></i> Filtros ativos:</span>
            <?php foreach ($filtrosAtivos as $filtro): ?>
                <span class="badge text-bg-light text-dark border"><?= e($filtro) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4 report-metrics">
        <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary">Faturamento</span><strong class="d-block fs-4"><?= money_br((float) $kpis['faturamento']) ?></strong></div></div>
        <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary">Vendas</span><strong class="d-block fs-4"><?= (int) $kpis['n'] ?></strong></div></div>
        <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary">Ticket medio</span><strong class="d-block fs-4"><?= money_br((float) $kpis['ticket']) ?></strong></div></div>
        <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary">Itens vendidos</span><strong class="d-block fs-4"><?= (int) $kpiItens['itens'] ?></strong></div></div>
        <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary">Descontos concedidos</span><strong class="d-block fs-4"><?= money_br((float) $kpis['descontos']) ?></strong></div></div>
        <?php if ($mostrarCancelamentos): ?>
        <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary">Canceladas (<?= (int) $canceladas['n'] ?>)</span><strong class="d-block fs-4"><?= money_br((float) $canceladas['valor']) ?></strong></div></div>
        <?php endif; ?>
    </div>

    <div class="panel-card bg-white p-4 mb-4 report-section">
        <div class="report-tab-title"><h2>Curva de vendas</h2><span><?= $pdvBucket === 'd' ? 'Diaria' : ($pdvBucket === 'w' ? 'Semanal' : 'Mensal') ?> — <?= $statusLabels[$fStatus] ?> · <?= e(date('d/m/Y', strtotime($inicio))) ?> a <?= e(date('d/m/Y', strtotime($fim))) ?></span></div>
        <div class="row g-3 mb-2">
            <div class="col-sm-4"><div class="metric-label">Total no periodo</div><strong><?= money_br($curvaTotal) ?></strong></div>
            <div class="col-sm-4"><div class="metric-label">Media do periodo</div><strong><?= money_br($curvaMedia) ?></strong></div>
            <div class="col-sm-4"><div class="metric-label">Melhor dia</div><strong><?= e($curvaMelhor['label']) ?> <span class="text-secondary fw-normal">/ <?= money_br($curvaMelhor['valor']) ?> (<?= (int) $curvaMelhor['n'] ?> venda<?= (int) $curvaMelhor['n'] === 1 ? '' : 's' ?>)</span></strong></div>
        </div>
        <div class="mb-1"><?= rel_chart_legend([['label' => 'Faturamento diario', 'color' => '#e8407a']]) ?></div>
        <?php
        $curvaValores = array_map(static fn (array $p) => ['label' => $p['label'], 'value' => (float) $p['valor'], 'tooltip' => $p['label'] . ' — ' . money_br((float) $p['valor']) . ' / ' . (int) $p['n'] . ' venda(s)'], $curva);
        $curvaQtds = array_map(static fn (array $p) => ['label' => $p['label'], 'value' => (float) $p['n'], 'tooltip' => $p['label'] . ' — ' . (int) $p['n'] . ' venda(s)'], $curva);
        ?>
        <div class="row g-3">
            <div class="col-12"><?= rel_svg_curve($curvaValores, ['money' => true, 'height' => 260]) ?></div>
        </div>
        <div class="mt-4"><h3 class="h6 fw-semibold">Numero de vendas por dia</h3><?= rel_svg_bars($curvaQtds, ['money' => false, 'height' => 180]) ?></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Vendas por dia da semana</h3><div><?= rel_chart_legend([['label' => 'Faturamento por dia', 'color' => '#e8407a']]) ?></div><?= rel_svg_bars(array_map(static fn (array $d) => ['label' => $d['label'], 'value' => (float) $d['valor'], 'tooltip' => $d['label'] . ' — ' . money_br((float) $d['valor']) . ' / ' . (int) $d['n'] . ' venda(s)'], $diasSemana), ['money' => true, 'height' => 230]) ?><div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Dia</th><th class="text-end">Vendas</th><th class="text-end">Faturamento</th></tr></thead><tbody><?php foreach ($diasSemana as $d): ?><tr><td><?= e($d['label']) ?></td><td class="text-end"><?= (int) $d['n'] ?></td><td class="text-end"><?= money_br((float) $d['valor']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
        <div class="col-lg-6"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Vendas por hora do dia</h3><div><?= rel_chart_legend([['label' => 'Faturamento por hora', 'color' => '#e8407a']]) ?></div><?= rel_svg_bars($horasChart, ['money' => true, 'height' => 230, 'padB' => 26]) ?></div></div>
    </div>

    <?php if ((float) $prevKpis['faturamento'] > 0 || (int) $prevKpis['n'] > 0 || (float) $kpis['faturamento'] > 0): ?>
    <div class="panel-card bg-white p-4 mb-4 report-section">
        <div class="report-tab-title"><h2>Comparativo com o periodo anterior</h2><span><?= $statusLabels[$fStatus] ?> · <?= e(date('d/m/Y', strtotime($prevInicio))) ?> a <?= e(date('d/m/Y', strtotime($prevFim))) ?></span></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Indicador</th><th class="text-end">Periodo atual</th><th class="text-end">Periodo anterior</th><th class="text-end">Variacao</th></tr></thead>
                <tbody>
                    <tr><td>Faturamento</td><td class="text-end"><?= money_br((float) $kpis['faturamento']) ?></td><td class="text-end"><?= money_br((float) $prevKpis['faturamento']) ?></td><td class="text-end"><?= rel_trend(rel_pct((float) $kpis['faturamento'], (float) $prevKpis['faturamento'])) ?></td></tr>
                    <tr><td>Numero de vendas</td><td class="text-end"><?= (int) $kpis['n'] ?></td><td class="text-end"><?= (int) $prevKpis['n'] ?></td><td class="text-end"><?= rel_trend(rel_pct((float) $kpis['n'], (float) $prevKpis['n'])) ?></td></tr>
                    <tr><td>Itens vendidos</td><td class="text-end"><?= (int) $kpiItens['itens'] ?></td><td class="text-end"><?= (int) $prevItens['itens'] ?></td><td class="text-end"><?= rel_trend(rel_pct((float) $kpiItens['itens'], (float) $prevItens['itens'])) ?></td></tr>
                    <tr><td>Ticket medio</td><td class="text-end"><?= money_br((float) $kpis['ticket']) ?></td><td class="text-end"><?= money_br((float) $prevKpis['ticket']) ?></td><td class="text-end"><?= rel_trend(rel_pct((float) $kpis['ticket'], (float) $prevKpis['ticket'])) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-6"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Formas de pagamento</h3><div class="mb-2"><?= rel_chart_legend([['label' => 'Pago', 'color' => '#e8407a'], ['label' => 'Pendente', 'color' => '#f4a988']]) ?></div><?= rel_svg_bars(array_map(static fn (array $p) => ['label' => pdv_payment_label((string) $p['metodo']), 'value' => (float) $p['pago'], 'value2' => (float) $p['pendente'], 'tooltip' => pdv_payment_label((string) $p['metodo'])], $pagamentosPorMetodo), ['money' => true, 'height' => 220]) ?><div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Forma</th><th class="text-end">Vendas</th><th class="text-end">Pago</th><th class="text-end">Pendente</th><th class="text-end">Total</th></tr></thead><tbody><?php foreach ($pagamentosPorMetodo as $p): ?><tr><td><?= e(pdv_payment_label((string) $p['metodo'])) ?></td><td class="text-end"><?= (int) $p['vendas'] ?></td><td class="text-end"><?= money_br((float) $p['pago']) ?></td><td class="text-end"><?= money_br((float) $p['pendente']) ?></td><td class="text-end"><?= money_br((float) $p['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
        <div class="col-lg-6"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Produtos mais vendidos</h3><div class="mb-2"><?= rel_chart_legend([['label' => 'Quantidade vendida', 'color' => '#f4621f']]) ?></div><?= rel_svg_bars(array_map(static fn (array $p) => ['label' => mb_strimwidth((string) $p['nome_produto'], 0, 22, '...'), 'value' => (float) $p['quantidade'], 'tooltip' => $p['nome_produto'] . ' — ' . (int) $p['quantidade'] . ' un · ' . money_br((float) $p['valor'])], $produtosMaisVendidos), ['money' => false, 'height' => 220]) ?><div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Valor</th></tr></thead><tbody><?php foreach ($produtosMaisVendidos as $p): ?><tr><td><?= e($p['nome_produto']) ?></td><td class="text-end"><?= (int) $p['quantidade'] ?></td><td class="text-end"><?= money_br((float) $p['valor']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Vendas por vendedor</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Vendedor</th><th class="text-end">Vendas</th><th class="text-end">Faturamento</th><th class="text-end">Ticket medio</th></tr></thead><tbody><?php foreach ($vendasPorVendedor as $v): ?><tr><td><?= e($v['vendedor']) ?></td><td class="text-end"><?= (int) $v['vendas'] ?></td><td class="text-end"><?= money_br((float) $v['valor']) ?></td><td class="text-end"><?= money_br((float) $v['ticket']) ?></td></tr><?php endforeach; ?><?php if (!$vendasPorVendedor): ?><tr><td colspan="4" class="text-secondary">Sem vendas no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-lg-5"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Top clientes do PDV</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Cliente</th><th class="text-end">Vendas</th><th class="text-end">Total</th></tr></thead><tbody><?php foreach ($topClientes as $c): ?><tr><td><?= e($c['cliente']) ?></td><td class="text-end"><?= (int) $c['vendas'] ?></td><td class="text-end"><?= money_br((float) $c['valor']) ?></td></tr><?php endforeach; ?><?php if (!$topClientes): ?><tr><td colspan="3" class="text-secondary">Sem clientes no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
    </div>

    <?php if ($mostrarCancelamentos): ?>
    <div class="row g-4">
        <div class="col-lg-7"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Cancelamentos</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Venda</th><th>Data</th><th>Operador</th><th>Motivo</th><th class="text-end">Valor</th></tr></thead><tbody><?php foreach ($cancelamentos as $c): ?><tr><td><?= e($c['numero']) ?></td><td><?= e(date('d/m/Y', strtotime((string) $c['data_cancelamento']))) ?></td><td><?= e($c['operador'] ?? '—') ?></td><td><?= e($c['motivo_cancelamento'] ?? '—') ?></td><td class="text-end"><?= money_br((float) $c['total']) ?></td></tr><?php endforeach; ?><?php if (!$cancelamentos): ?><tr><td colspan="5" class="text-secondary">Nenhum cancelamento no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-lg-5"><div class="panel-card bg-white p-4 h-100 report-section"><h3 class="h5">Crediario (a prazo) gerado</h3><div class="d-flex justify-content-between border-bottom py-2"><span>Parcelas geradas</span><strong><?= (int) $crediario['parcelas'] ?></strong></div><div class="d-flex justify-content-between border-bottom py-2"><span>Valor total das parcelas</span><strong><?= money_br((float) $crediario['a_receber']) ?></strong></div><div class="d-flex justify-content-between py-2"><span>Recebido no periodo</span><strong class="text-success"><?= money_br((float) $crediario['recebido']) ?></strong></div></div></div>
    </div>
    <?php else: ?>
    <div class="panel-card bg-white p-4 report-section">
        <h3 class="h5">Crediario (a prazo) gerado</h3>
        <div class="d-flex justify-content-between border-bottom py-2"><span>Parcelas geradas</span><strong><?= (int) $crediario['parcelas'] ?></strong></div>
        <div class="d-flex justify-content-between border-bottom py-2"><span>Valor total das parcelas</span><strong><?= money_br((float) $crediario['a_receber']) ?></strong></div>
        <div class="d-flex justify-content-between py-2"><span>Recebido no periodo</span><strong class="text-success"><?= money_br((float) $crediario['recebido']) ?></strong></div>
    </div>
    <?php endif; ?>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>