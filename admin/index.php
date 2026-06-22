<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login('admin');

$metricas = [
    'Produtos' => db_one("SELECT COUNT(*) AS total FROM produtos")['total'] ?? 0,
    'Pedidos' => db_one("SELECT COUNT(*) AS total FROM pedidos")['total'] ?? 0,
    'Clientes' => db_one("SELECT COUNT(*) AS total FROM usuarios WHERE tipo = 'cliente'")['total'] ?? 0,
    'Chamados' => db_one("SELECT COUNT(*) AS total FROM chamados WHERE status <> 'finalizado'")['total'] ?? 0,
];
$pedidos = db_all("SELECT p.*, u.nome AS cliente FROM pedidos p LEFT JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.criado_em DESC LIMIT 6");

$pageTitle = 'Dashboard Gestor';
$active = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/menu.php'; ?></div><div class="col-lg-9">
    <h1 class="h3 section-title">Dashboard do gestor</h1>
    <div class="row g-3 mb-4">
        <?php foreach ($metricas as $label => $total): ?><div class="col-md-3"><div class="panel-card metric bg-white p-4"><span class="text-secondary"><?= e($label) ?></span><strong class="d-block fs-3"><?= (int) $total ?></strong></div></div><?php endforeach; ?>
    </div>
    <div class="panel-card bg-white p-4"><h2 class="h5">Pedidos recentes</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach ($pedidos as $pedido): ?><tr><td>#<?= (int) $pedido['id'] ?></td><td><?= e($pedido['cliente']) ?></td><td><?= e($pedido['status']) ?></td><td><?= money_br((float) $pedido['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div></div></div></section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
