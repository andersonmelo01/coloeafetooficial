<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');
$q = trim((string) ($_GET['q'] ?? ''));
$pedidosPage = paginate_query(
    "SELECT p.*, u.nome AS cliente
     FROM pedidos p
     LEFT JOIN usuarios u ON u.id = p.usuario_id
     WHERE (:q = '' OR p.id = :id_busca OR u.nome LIKE :like_q OR p.status LIKE :like_q)
     ORDER BY p.criado_em DESC",
    ['q' => $q, 'id_busca' => (int) $q, 'like_q' => '%' . $q . '%']
);
$pedidos = $pedidosPage['rows'];
$pageTitle = 'Pedidos';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><h1 class="h3 section-title">Pedidos</h1><form class="search-control mb-4" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pedido, cliente ou status"><button class="btn btn-brand">Buscar</button></div></form><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Fiscal</th><th>Pagamento</th><th>Total</th><th>Data</th><th></th></tr></thead><tbody><?php foreach ($pedidos as $p): ?><tr><td>#<?= (int) $p['id'] ?></td><td><?= e($p['cliente']) ?></td><td><?= e($p['status']) ?></td><td><?= e($p['fiscal_status'] ?? 'pendente') ?></td><td><?= e($p['forma_pagamento']) ?></td><td><?= money_br((float) $p['total']) ?></td><td><?= e($p['criado_em']) ?></td><td><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/pedidos/detalhe.php?id=' . (int) $p['id'])) ?>"><i class="bi bi-eye"></i> Detalhes</a></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$pedidos): ?><p class="text-secondary mb-0">Nenhum pedido localizado.</p><?php endif; ?><?= pagination_links($pedidosPage) ?></div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
