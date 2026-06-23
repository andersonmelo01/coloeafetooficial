<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login('cliente');
$q = trim((string) ($_GET['q'] ?? ''));
$notasPage = paginate_query(
    "SELECT nf.*, p.id AS pedido_id
     FROM notas_fiscais nf
     JOIN pedidos p ON p.id = nf.pedido_id
     WHERE p.usuario_id = :id AND (:q = '' OR nf.numero LIKE :like_q OR nf.chave_acesso LIKE :like_q)
     ORDER BY nf.emitida_em DESC",
    ['id' => current_user()['id'], 'q' => $q, 'like_q' => '%' . $q . '%']
);
$notas = $notasPage['rows'];
$pageTitle = 'NF-e';
$active = 'cliente';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <h1 class="h3 section-title mb-0">Notas fiscais</h1>
            <form class="search-control" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Numero ou chave"><button class="btn btn-brand">Buscar</button></div></form>
        </div>
        <div class="table-responsive mobile-card-table"><table class="table align-middle"><thead><tr><th>NF-e</th><th>Pedido</th><th>Status</th><th>Emissao</th><th></th></tr></thead><tbody>
            <?php foreach ($notas as $nota): ?><tr><td class="mobile-card-title"><?= e($nota['numero']) ?></td><td data-label="Pedido">#<?= (int) $nota['pedido_id'] ?></td><td data-label="Status"><?= e($nota['status']) ?></td><td data-label="Emissao"><?= e($nota['emitida_em']) ?></td><td class="mobile-card-actions"><a class="btn btn-sm btn-outline-brand" href="<?= e($nota['xml_url'] ?: '#') ?>"><i class="bi bi-download"></i> XML</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (!$notas): ?><p class="text-secondary mb-0">Nenhuma NF-e encontrada.</p><?php endif; ?>
        <?= pagination_links($notasPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
