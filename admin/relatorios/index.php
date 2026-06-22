<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/EmailService.php';
require_login('admin');

$inicio = (string) ($_GET['inicio'] ?? date('Y-m-01'));
$fim = (string) ($_GET['fim'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
    $inicio = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
    $fim = date('Y-m-d');
}
$inicioDateTime = $inicio . ' 00:00:00';
$fimDateTime = $fim . ' 23:59:59';
$params = ['inicio' => $inicioDateTime, 'fim' => $fimDateTime];
$dateWhere = "BETWEEN :inicio AND :fim";

$metricas = [
    'Pedidos' => (int) (db_one("SELECT COUNT(*) AS total FROM pedidos WHERE criado_em $dateWhere", $params)['total'] ?? 0),
    'Vendas' => (float) (db_one("SELECT COALESCE(SUM(total), 0) AS total FROM pedidos WHERE status <> 'cancelado' AND criado_em $dateWhere", $params)['total'] ?? 0),
    'Pagamentos pagos' => (float) (db_one("SELECT COALESCE(SUM(valor), 0) AS total FROM pagamentos WHERE status = 'pago' AND criado_em $dateWhere", $params)['total'] ?? 0),
    'Entregas concluidas' => (int) (db_one("SELECT COUNT(*) AS total FROM entregas WHERE status = 'entregue' AND entregue_em $dateWhere", $params)['total'] ?? 0),
    'Tentativas de entrega' => (int) (db_one("SELECT COUNT(*) AS total FROM entrega_tentativas WHERE criado_em $dateWhere", $params)['total'] ?? 0),
    'Clientes novos' => (int) (db_one("SELECT COUNT(*) AS total FROM usuarios WHERE tipo = 'cliente' AND criado_em $dateWhere", $params)['total'] ?? 0),
];

$pedidosPorStatus = db_all(
    "SELECT status, COUNT(*) AS total, COALESCE(SUM(total), 0) AS valor
     FROM pedidos
     WHERE criado_em $dateWhere
     GROUP BY status
     ORDER BY total DESC",
    $params
);
$pagamentosPorMetodo = db_all(
    "SELECT metodo, status, COUNT(*) AS total, COALESCE(SUM(valor), 0) AS valor
     FROM pagamentos
     WHERE criado_em $dateWhere
     GROUP BY metodo, status
     ORDER BY valor DESC",
    $params
);
$produtosMaisVendidos = db_all(
    "SELECT pi.nome_produto, SUM(pi.quantidade) AS quantidade, COALESCE(SUM(pi.total), 0) AS valor
     FROM pedido_itens pi
     JOIN pedidos p ON p.id = pi.pedido_id
     WHERE p.criado_em $dateWhere
       AND p.status <> 'cancelado'
     GROUP BY pi.nome_produto
     ORDER BY quantidade DESC
     LIMIT 10",
    $params
);
$estoqueMovimentos = db_all(
    "SELECT tipo, COUNT(*) AS total, COALESCE(SUM(quantidade), 0) AS quantidade
     FROM movimentos_estoque
     WHERE criado_em $dateWhere
     GROUP BY tipo
     ORDER BY tipo",
    $params
);
$estoqueBaixo = db_all(
    "SELECT sku, nome, estoque
     FROM produtos
     WHERE ativo = 1 AND estoque <= 5
     ORDER BY estoque ASC, nome
     LIMIT 15"
);
$notasPorStatus = db_all(
    "SELECT status, COUNT(*) AS total
     FROM notas_fiscais
     WHERE criado_em $dateWhere
     GROUP BY status
     ORDER BY total DESC",
    $params
);
$entregasPorEntregadorMes = db_all(
    "SELECT DATE_FORMAT(en.entregue_em, '%Y-%m') AS mes, u.nome AS entregador, COUNT(*) AS total
     FROM entregas en
     JOIN usuarios u ON u.id = en.entregador_id
     WHERE en.status = 'entregue'
       AND en.entregue_em $dateWhere
     GROUP BY mes, u.id, u.nome
     ORDER BY mes DESC, total DESC",
    $params
);
$entregasStatus = db_all(
    "SELECT COALESCE(en.status, 'sem_entrega') AS status, COUNT(*) AS total
     FROM pedidos p
     LEFT JOIN entregas en ON en.pedido_id = p.id
     WHERE p.criado_em $dateWhere
     GROUP BY COALESCE(en.status, 'sem_entrega')
     ORDER BY total DESC",
    $params
);
$tentativasEntrega = db_all(
    "SELECT COALESCE(u.nome, 'Sem entregador') AS entregador, COUNT(*) AS total, MAX(et.criado_em) AS ultima_tentativa
     FROM entrega_tentativas et
     LEFT JOIN usuarios u ON u.id = et.entregador_id
     WHERE et.criado_em $dateWhere
     GROUP BY COALESCE(u.nome, 'Sem entregador')
     ORDER BY total DESC",
    $params
);
$chamadosPorStatus = db_all(
    "SELECT status, COUNT(*) AS total
     FROM chamados
     WHERE criado_em $dateWhere
     GROUP BY status
     ORDER BY total DESC",
    $params
);
$clientesPorTipo = db_all(
    "SELECT COALESCE(cp.tipo_pessoa, 'sem_perfil') AS tipo, COUNT(*) AS total
     FROM usuarios u
     LEFT JOIN clientes_perfis cp ON cp.usuario_id = u.id
     WHERE u.tipo = 'cliente'
       AND u.criado_em $dateWhere
     GROUP BY COALESCE(cp.tipo_pessoa, 'sem_perfil')
     ORDER BY total DESC",
    $params
);
$emailsPorStatus = db_all(
    "SELECT status, COUNT(*) AS total
     FROM emails_envios
     WHERE criado_em $dateWhere
     GROUP BY status
     ORDER BY total DESC",
    $params
);
$promocoes = db_all(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS ativas,
            SUM(CASE WHEN destaque = 1 AND ativo = 1 THEN 1 ELSE 0 END) AS destaques
     FROM promocoes"
)[0] ?? ['total' => 0, 'ativas' => 0, 'destaques' => 0];
$cadastros = db_one(
    "SELECT
        SUM(CASE WHEN tipo = 'cliente' THEN 1 ELSE 0 END) AS clientes,
        SUM(CASE WHEN tipo = 'entregador' THEN 1 ELSE 0 END) AS entregadores,
        SUM(CASE WHEN tipo = 'admin' THEN 1 ELSE 0 END) AS gestores
     FROM usuarios"
) ?? ['clientes' => 0, 'entregadores' => 0, 'gestores' => 0];
$catalogo = db_one(
    "SELECT
        (SELECT COUNT(*) FROM produtos) AS produtos,
        (SELECT COUNT(*) FROM categorias) AS categorias,
        (SELECT COUNT(*) FROM grupos_produtos) AS grupos"
) ?? ['produtos' => 0, 'categorias' => 0, 'grupos' => 0];

$pageTitle = 'Relatorios';
$active = 'admin';
$bodyClass = 'reports-page';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5 report-print-shell"><div class="container"><div class="row g-4"><div class="col-lg-3 report-sidebar"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9 report-content">
    <div class="panel-card bg-white p-4 mb-4 report-header-card">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-end">
            <div>
                <h1 class="h3 section-title mb-1">Relatorios gerenciais</h1>
                <p class="text-secondary mb-0">Periodo de <?= e(date('d/m/Y', strtotime($inicio))) ?> ate <?= e(date('d/m/Y', strtotime($fim))) ?>.</p>
            </div>
            <form method="get" class="row g-2 align-items-end no-print">
                <div class="col-auto"><label class="form-label small">Inicio</label><input class="form-control" type="date" name="inicio" value="<?= e($inicio) ?>"></div>
                <div class="col-auto"><label class="form-label small">Fim</label><input class="form-control" type="date" name="fim" value="<?= e($fim) ?>"></div>
                <div class="col-auto"><button class="btn btn-brand"><i class="bi bi-search"></i> Filtrar</button></div>
                <div class="col-auto"><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4 report-metrics">
        <?php foreach ($metricas as $label => $valor): ?>
            <div class="col-md-4"><div class="panel-card metric bg-white p-4 h-100"><span class="text-secondary"><?= e($label) ?></span><strong class="d-block fs-4"><?= is_float($valor) ? money_br($valor) : (int) $valor ?></strong></div></div>
        <?php endforeach; ?>
    </div>

    <ul class="nav nav-tabs report-tabs no-print" id="relatoriosTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rel-vendas" type="button">Vendas</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-estoque" type="button">Estoque</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-fiscal" type="button">Fiscal</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-entregas" type="button">Entregas</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-clientes" type="button">Clientes</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rel-atendimento" type="button">Atendimento</button></li>
    </ul>

    <div class="tab-content report-tab-content bg-white">
        <div class="tab-pane fade show active report-tab-pane" id="rel-vendas">
            <div class="report-tab-title"><h2>Relatorio de vendas</h2><span><?= e($inicio) ?> a <?= e($fim) ?></span></div>
            <div class="row g-4">
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Pedidos por status</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Status</th><th>Qtd</th><th>Valor</th></tr></thead><tbody><?php foreach ($pedidosPorStatus as $row): ?><tr><td><?= e(pedido_status_label($row['status'])) ?></td><td><?= (int) $row['total'] ?></td><td><?= money_br((float) $row['valor']) ?></td></tr><?php endforeach; ?><?php if (!$pedidosPorStatus): ?><tr><td colspan="3" class="text-secondary">Sem pedidos no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Pagamentos</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Metodo</th><th>Status</th><th>Qtd</th><th>Valor</th></tr></thead><tbody><?php foreach ($pagamentosPorMetodo as $row): ?><tr><td><?= e($row['metodo']) ?></td><td><?= e($row['status']) ?></td><td><?= (int) $row['total'] ?></td><td><?= money_br((float) $row['valor']) ?></td></tr><?php endforeach; ?><?php if (!$pagamentosPorMetodo): ?><tr><td colspan="4" class="text-secondary">Sem pagamentos no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-12"><div class="panel-card p-4 report-section"><h3 class="h5">Produtos mais vendidos</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produto</th><th>Qtd</th><th>Valor</th></tr></thead><tbody><?php foreach ($produtosMaisVendidos as $row): ?><tr><td><?= e($row['nome_produto']) ?></td><td><?= (int) $row['quantidade'] ?></td><td><?= money_br((float) $row['valor']) ?></td></tr><?php endforeach; ?><?php if (!$produtosMaisVendidos): ?><tr><td colspan="3" class="text-secondary">Sem produtos vendidos no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade report-tab-pane" id="rel-estoque">
            <div class="report-tab-title"><h2>Relatorio de estoque</h2><span>Movimentacao e saldo baixo</span></div>
            <div class="row g-4">
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Movimentacao de estoque</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tipo</th><th>Movimentos</th><th>Qtd</th></tr></thead><tbody><?php foreach ($estoqueMovimentos as $row): ?><tr><td><?= e($row['tipo']) ?></td><td><?= (int) $row['total'] ?></td><td><?= (int) $row['quantidade'] ?></td></tr><?php endforeach; ?><?php if (!$estoqueMovimentos): ?><tr><td colspan="3" class="text-secondary">Sem movimentacoes no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Estoque baixo</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>SKU</th><th>Produto</th><th>Saldo</th></tr></thead><tbody><?php foreach ($estoqueBaixo as $row): ?><tr><td><?= e($row['sku']) ?></td><td><?= e($row['nome']) ?></td><td><?= (int) $row['estoque'] ?></td></tr><?php endforeach; ?><?php if (!$estoqueBaixo): ?><tr><td colspan="3" class="text-secondary">Nenhum item com estoque baixo.</td></tr><?php endif; ?></tbody></table></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade report-tab-pane" id="rel-fiscal">
            <div class="report-tab-title"><h2>Relatorio fiscal</h2><span>NF-e e eventos fiscais</span></div>
            <div class="row g-4">
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">NF-e por status</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Status</th><th>Qtd</th></tr></thead><tbody><?php foreach ($notasPorStatus as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$notasPorStatus): ?><tr><td colspan="2" class="text-secondary">Sem notas no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">E-mails por status</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Status</th><th>Qtd</th></tr></thead><tbody><?php foreach ($emailsPorStatus as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$emailsPorStatus): ?><tr><td colspan="2" class="text-secondary">Sem e-mails no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade report-tab-pane" id="rel-entregas">
            <div class="report-tab-title"><h2>Relatorio de entregas</h2><span>Produtividade por entregador e tentativas</span></div>
            <div class="row g-4">
                <div class="col-12"><div class="panel-card p-4 report-section"><h3 class="h5">Entregas realizadas por entregador no mes</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mes</th><th>Entregador</th><th>Entregas confirmadas</th></tr></thead><tbody><?php foreach ($entregasPorEntregadorMes as $row): ?><tr><td><?= e($row['mes']) ?></td><td><?= e($row['entregador']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$entregasPorEntregadorMes): ?><tr><td colspan="3" class="text-secondary">Nenhuma entrega confirmada no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Status das entregas</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Status</th><th>Qtd</th></tr></thead><tbody><?php foreach ($entregasStatus as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$entregasStatus): ?><tr><td colspan="2" class="text-secondary">Sem entregas no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Tentativas por entregador</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Entregador</th><th>Tentativas</th><th>Ultima</th></tr></thead><tbody><?php foreach ($tentativasEntrega as $row): ?><tr><td><?= e($row['entregador']) ?></td><td><?= (int) $row['total'] ?></td><td><?= e($row['ultima_tentativa']) ?></td></tr><?php endforeach; ?><?php if (!$tentativasEntrega): ?><tr><td colspan="3" class="text-secondary">Sem tentativas registradas no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade report-tab-pane" id="rel-clientes">
            <div class="report-tab-title"><h2>Relatorio de clientes e catalogo</h2><span>Cadastros, categorias e promocoes</span></div>
            <div class="row g-4">
                <div class="col-lg-4"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Clientes por tipo</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tipo</th><th>Qtd</th></tr></thead><tbody><?php foreach ($clientesPorTipo as $row): ?><tr><td><?= e($row['tipo']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$clientesPorTipo): ?><tr><td colspan="2" class="text-secondary">Sem clientes no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-4"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Cadastros</h3><div class="d-flex justify-content-between border-bottom py-2"><span>Clientes</span><strong><?= (int) $cadastros['clientes'] ?></strong></div><div class="d-flex justify-content-between border-bottom py-2"><span>Entregadores</span><strong><?= (int) $cadastros['entregadores'] ?></strong></div><div class="d-flex justify-content-between py-2"><span>Gestores</span><strong><?= (int) $cadastros['gestores'] ?></strong></div></div></div>
                <div class="col-lg-4"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Catalogo e promocoes</h3><div class="d-flex justify-content-between border-bottom py-2"><span>Produtos</span><strong><?= (int) $catalogo['produtos'] ?></strong></div><div class="d-flex justify-content-between border-bottom py-2"><span>Categorias</span><strong><?= (int) $catalogo['categorias'] ?></strong></div><div class="d-flex justify-content-between border-bottom py-2"><span>Grupos</span><strong><?= (int) $catalogo['grupos'] ?></strong></div><div class="d-flex justify-content-between border-bottom py-2"><span>Promocoes</span><strong><?= (int) $promocoes['total'] ?></strong></div><div class="d-flex justify-content-between py-2"><span>Destaques ativos</span><strong><?= (int) $promocoes['destaques'] ?></strong></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade report-tab-pane" id="rel-atendimento">
            <div class="report-tab-title"><h2>Relatorio de atendimento</h2><span>Chamados e comunicacao</span></div>
            <div class="row g-4">
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">Chamados por status</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Status</th><th>Qtd</th></tr></thead><tbody><?php foreach ($chamadosPorStatus as $row): ?><tr><td><?= e(chamado_status_label($row['status'])) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$chamadosPorStatus): ?><tr><td colspan="2" class="text-secondary">Sem chamados no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
                <div class="col-lg-6"><div class="panel-card p-4 h-100 report-section"><h3 class="h5">E-mails por status</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Status</th><th>Qtd</th></tr></thead><tbody><?php foreach ($emailsPorStatus as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?><?php if (!$emailsPorStatus): ?><tr><td colspan="2" class="text-secondary">Sem e-mails no periodo.</td></tr><?php endif; ?></tbody></table></div></div></div>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
