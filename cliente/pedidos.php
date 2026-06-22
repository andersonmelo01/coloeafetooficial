<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login('cliente');
$q = trim((string) ($_GET['q'] ?? ''));
$pedidosPage = paginate_query(
    "SELECT * FROM pedidos WHERE usuario_id = :id AND (:q = '' OR id = :id_busca OR status LIKE :like_q) ORDER BY criado_em DESC",
    ['id' => current_user()['id'], 'q' => $q, 'id_busca' => (int) $q, 'like_q' => '%' . $q . '%']
);
$pedidos = $pedidosPage['rows'];
$statusLabels = [
    'novo' => 'Novo',
    'aguardando_pagamento' => 'Aguardando pagamento',
    'pago' => 'Pago',
    'separacao' => 'Em separacao',
    'enviado' => 'Enviado',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado',
];
$pageTitle = 'Historico de Compras';
$active = 'cliente';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <h1 class="h3 section-title mb-0">Historico de compras</h1>
            <form class="search-control" method="get">
                <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pedido ou status"><button class="btn btn-brand">Buscar</button></div>
            </form>
        </div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pedido</th><th>Status</th><th>Pagamento</th><th>Total</th><th>Data</th></tr></thead><tbody>
            <?php foreach ($pedidos as $pedido): ?><tr><td>#<?= (int) $pedido['id'] ?></td><td><span class="badge text-bg-light"><?= e($statusLabels[$pedido['status']] ?? $pedido['status']) ?></span></td><td><?= e($pedido['forma_pagamento']) ?></td><td><?= money_br((float) $pedido['total']) ?></td><td><?= e($pedido['criado_em']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (!$pedidos): ?><p class="text-secondary mb-0">Nenhum pedido localizado.</p><?php endif; ?>
        <?= pagination_links($pedidosPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
