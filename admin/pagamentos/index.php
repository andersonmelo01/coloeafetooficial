<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/EfiService.php';
require_login('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$pedidosPage = paginate_query(
    "SELECT p.*, u.nome AS cliente
     FROM pedidos p
     JOIN usuarios u ON u.id = p.usuario_id
     WHERE (:q = '' OR p.id = :id_busca OR u.nome LIKE :like_q OR p.pagamento_status LIKE :like_q)
     ORDER BY p.criado_em DESC",
    ['q' => $q, 'id_busca' => (int) $q, 'like_q' => '%' . $q . '%']
);
$pedidos = $pedidosPage['rows'];
$efiStatus = efi_can_charge();

$pageTitle = 'Pagamentos';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4 mb-4">
        <h1 class="h3 section-title">Pagamentos / Efi Bank</h1>
        <div class="alert <?= $efiStatus['ok'] ? 'alert-success' : 'alert-warning' ?> mb-0"><?= e($efiStatus['message']) ?> Webhook: <?= e(base_url('webhooks/efi.php?token=' . urlencode((string) app_config('efi.webhook_token', '')))) ?></div>
    </div>
    <div class="panel-card bg-white p-4">
        <form class="search-control mb-4" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pedido, cliente ou pagamento"><button class="btn btn-brand">Buscar</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Forma</th><th>Total</th><th></th></tr></thead><tbody>
            <?php foreach ($pedidos as $p): ?><tr><td>#<?= (int) $p['id'] ?></td><td><?= e($p['cliente']) ?></td><td><?= e($p['pagamento_status']) ?></td><td><?= e($p['forma_pagamento']) ?></td><td><?= money_br((float) $p['total']) ?></td><td><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/pagamentos/preparar.php?id=' . (int) $p['id'])) ?>"><i class="bi bi-qr-code"></i> Preparar cobranca</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (!$pedidos): ?><p class="text-secondary mb-0">Nenhum pedido localizado.</p><?php endif; ?>
        <?= pagination_links($pedidosPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
