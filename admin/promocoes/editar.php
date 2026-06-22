<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$promo = db_one("SELECT * FROM promocoes WHERE id = :id", ['id' => $id]);
if (!$promo) {
    flash('danger', 'Promocao nao encontrada.');
    redirect('admin/promocoes/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    db()->prepare(
        "UPDATE promocoes
         SET titulo = :titulo, descricao = :descricao, preco_promocional = :preco_promocional,
             percentual_desconto = :percentual_desconto, data_inicio = :data_inicio, data_fim = :data_fim,
             ativo = :ativo, destaque = :destaque
         WHERE id = :id"
    )->execute([
        'id' => $id,
        'titulo' => $_POST['titulo'],
        'descricao' => $_POST['descricao'] ?: null,
        'preco_promocional' => $_POST['preco_promocional'] ?: null,
        'percentual_desconto' => $_POST['percentual_desconto'] ?: null,
        'data_inicio' => $_POST['data_inicio'] ?: null,
        'data_fim' => $_POST['data_fim'] ?: null,
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
        'destaque' => isset($_POST['destaque']) ? 1 : 0,
    ]);
    flash('success', 'Promocao atualizada.');
    redirect('admin/promocoes/index.php');
}

$pageTitle = 'Editar Promocao';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4">
        <h1 class="h3 section-title">Editar promocao</h1>
        <form method="post" class="row g-3">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
            <div class="col-12"><label class="form-label">Titulo</label><input class="form-control" name="titulo" value="<?= e($promo['titulo']) ?>" required></div>
            <div class="col-12"><label class="form-label">Descricao</label><input class="form-control" name="descricao" value="<?= e($promo['descricao']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Preco promocional</label><input class="form-control" name="preco_promocional" type="number" step="0.01" value="<?= e((string) $promo['preco_promocional']) ?>"></div>
            <div class="col-md-6"><label class="form-label">% desconto</label><input class="form-control" name="percentual_desconto" type="number" step="0.01" value="<?= e((string) $promo['percentual_desconto']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Inicio</label><input class="form-control" name="data_inicio" type="date" value="<?= e($promo['data_inicio']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Fim</label><input class="form-control" name="data_fim" type="date" value="<?= e($promo['data_fim']) ?>"></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" <?= (int) $promo['ativo'] ? 'checked' : '' ?>> Ativa</label></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="destaque" <?= (int) $promo['destaque'] ? 'checked' : '' ?>> Destacar na loja</label></div>
            <div class="col-12"><button class="btn btn-brand">Salvar</button></div>
        </form>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
