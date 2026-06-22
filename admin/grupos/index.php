<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    try {
        if (($_POST['acao'] ?? '') === 'excluir') {
            db()->prepare("DELETE FROM grupos_produtos WHERE id = :id")->execute(['id' => (int) $_POST['id']]);
            flash('success', 'Grupo excluido.');
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $_POST['nome']), '-'));
            db()->prepare("INSERT INTO grupos_produtos (nome, slug, descricao, ativo) VALUES (:nome, :slug, :descricao, 1)")->execute(['nome' => $_POST['nome'], 'slug' => $slug, 'descricao' => $_POST['descricao']]);
            flash('success', 'Grupo cadastrado.');
        }
    } catch (Throwable $e) {
        flash('danger', 'Nao foi possivel salvar o grupo.');
    }
    redirect('admin/grupos/index.php');
}
$q = trim((string) ($_GET['q'] ?? ''));
$gruposPage = paginate_query("SELECT * FROM grupos_produtos WHERE (:q = '' OR nome LIKE :like_q) ORDER BY criado_em DESC", ['q' => $q, 'like_q' => '%' . $q . '%']);
$grupos = $gruposPage['rows'];
$pageTitle = 'Grupos';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9"><div class="row g-4">
    <div class="col-lg-5"><div class="panel-card bg-white p-4"><h1 class="h4 section-title">Novo grupo</h1><form method="post" class="row g-3"><?= csrf_field() ?><div class="col-12"><label class="form-label">Nome</label><input class="form-control" name="nome" required></div><div class="col-12"><label class="form-label">Descricao</label><textarea class="form-control" name="descricao"></textarea></div><div class="col-12"><button class="btn btn-brand">Salvar</button></div></form></div></div>
    <div class="col-lg-7"><div class="panel-card bg-white p-4"><h2 class="h4 section-title">Grupos</h2><form class="search-control mb-3" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pesquisar grupo"><button class="btn btn-brand">Buscar</button></div></form><div class="table-responsive"><table class="table"><thead><tr><th>Nome</th><th>Slug</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($grupos as $g): ?><tr><td><?= e($g['nome']) ?></td><td><?= e($g['slug']) ?></td><td><?= (int) $g['ativo'] ? 'Ativo' : 'Inativo' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/grupos/editar.php?id=' . (int) $g['id'])) ?>"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Excluir grupo?"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$grupos): ?><p class="text-secondary mb-0">Nenhum grupo localizado.</p><?php endif; ?><?= pagination_links($gruposPage) ?></div></div>
</div></div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
