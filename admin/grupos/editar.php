<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$grupo = db_one("SELECT * FROM grupos_produtos WHERE id = :id", ['id' => $id]);
if (!$grupo) {
    flash('danger', 'Grupo nao encontrado.');
    redirect('admin/grupos/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $_POST['nome']), '-'));
    db()->prepare("UPDATE grupos_produtos SET nome = :nome, slug = :slug, descricao = :descricao, ativo = :ativo WHERE id = :id")->execute([
        'id' => $id,
        'nome' => $_POST['nome'],
        'slug' => $slug,
        'descricao' => $_POST['descricao'],
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ]);
    flash('success', 'Grupo atualizado.');
    redirect('admin/grupos/index.php');
}

$pageTitle = 'Editar Grupo';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9"><div class="panel-card bg-white p-4">
    <h1 class="h3 section-title">Editar grupo</h1>
    <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $grupo['id'] ?>"><div class="col-md-8"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?= e($grupo['nome']) ?>" required></div><div class="col-12"><label class="form-label">Descricao</label><textarea class="form-control" name="descricao"><?= e($grupo['descricao']) ?></textarea></div><div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" <?= (int) $grupo['ativo'] ? 'checked' : '' ?>> Ativo</label></div><div class="col-12"><button class="btn btn-brand">Salvar</button></div></form>
</div></div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
