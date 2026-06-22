<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$permissions = admin_permission_catalog();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $selected = array_values(array_intersect(array_keys($permissions), array_map('strval', $_POST['permissoes'] ?? [])));

        if ($nome === '') {
            flash('warning', 'Informe o nome do perfil.');
            redirect('admin/perfis/index.php');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();
            if ($id > 0) {
                $perfil = db_one("SELECT administrador FROM admin_perfis WHERE id = :id", ['id' => $id]);
                $pdo->prepare("UPDATE admin_perfis SET nome = :nome, descricao = :descricao, ativo = :ativo WHERE id = :id")->execute([
                    'id' => $id,
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'ativo' => $ativo,
                ]);
                $pdo->prepare("DELETE FROM admin_perfil_permissoes WHERE perfil_id = :id")->execute(['id' => $id]);
                if ($perfil && (int) $perfil['administrador'] === 1) {
                    $selected = array_keys($permissions);
                }
                $perfilId = $id;
            } else {
                $pdo->prepare("INSERT INTO admin_perfis (nome, descricao, ativo) VALUES (:nome, :descricao, :ativo)")->execute([
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'ativo' => $ativo,
                ]);
                $perfilId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("INSERT IGNORE INTO admin_perfil_permissoes (perfil_id, permissao) VALUES (:perfil_id, :permissao)");
            foreach ($selected as $permission) {
                $stmt->execute(['perfil_id' => $perfilId, 'permissao' => $permission]);
            }
            $pdo->commit();
            flash('success', 'Perfil salvo.');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Nao foi possivel salvar o perfil.');
        }
        redirect('admin/perfis/index.php');
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $perfil = db_one(
            "SELECT ap.*, (SELECT COUNT(*) FROM usuarios u WHERE u.perfil_admin_id = ap.id) AS usuarios
             FROM admin_perfis ap
             WHERE ap.id = :id",
            ['id' => $id]
        );

        if (!$perfil || (int) $perfil['administrador'] === 1 || (int) $perfil['usuarios'] > 0) {
            flash('warning', 'Perfil nao pode ser excluido. Verifique se e administrador ou possui usuarios vinculados.');
            redirect('admin/perfis/index.php');
        }

        db()->prepare("DELETE FROM admin_perfis WHERE id = :id")->execute(['id' => $id]);
        flash('success', 'Perfil excluido.');
        redirect('admin/perfis/index.php');
    }
}

$editId = (int) ($_GET['editar'] ?? 0);
$editPerfil = $editId ? db_one("SELECT * FROM admin_perfis WHERE id = :id", ['id' => $editId]) : null;
$editPerms = $editId ? array_column(db_all("SELECT permissao FROM admin_perfil_permissoes WHERE perfil_id = :id", ['id' => $editId]), 'permissao') : [];
if ($editPerfil && (int) $editPerfil['administrador'] === 1) {
    $editPerms = array_keys($permissions);
}

$perfisPage = paginate_query(
    "SELECT ap.*, (SELECT COUNT(*) FROM usuarios u WHERE u.perfil_admin_id = ap.id) AS usuarios
     FROM admin_perfis ap
     ORDER BY ap.administrador DESC, ap.criado_em DESC"
);
$perfis = $perfisPage['rows'];

$pageTitle = 'Perfis Administrativos';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card bg-white p-4">
                <h1 class="h4 section-title"><?= $editPerfil ? 'Editar perfil' : 'Novo perfil' ?></h1>
                <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) ($editPerfil['id'] ?? 0) ?>">
                    <div class="col-12"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?= e($editPerfil['nome'] ?? '') ?>" required></div>
                    <div class="col-12"><label class="form-label">Descricao</label><input class="form-control" name="descricao" value="<?= e($editPerfil['descricao'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" <?= !$editPerfil || (int) $editPerfil['ativo'] ? 'checked' : '' ?>> Perfil ativo</label></div>
                    <div class="col-12"><label class="form-label fw-semibold">Permissoes</label></div>
                    <?php foreach ($permissions as $key => $label): ?>
                        <div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="permissoes[]" value="<?= e($key) ?>" <?= in_array($key, $editPerms, true) ? 'checked' : '' ?> <?= $editPerfil && (int) $editPerfil['administrador'] === 1 ? 'disabled' : '' ?>> <?= e($label) ?></label></div>
                    <?php endforeach; ?>
                    <div class="col-12"><button class="btn btn-brand"><i class="bi bi-save"></i> Salvar perfil</button> <?php if ($editPerfil): ?><a class="btn btn-outline-secondary" href="<?= e(base_url('admin/perfis/index.php')) ?>">Cancelar</a><?php endif; ?></div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel-card bg-white p-4">
                <h2 class="h4 section-title">Perfis cadastrados</h2>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Perfil</th><th>Usuarios</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($perfis as $perfil): ?><tr><td><?= e($perfil['nome']) ?><?php if ((int) $perfil['administrador']): ?><br><small class="text-success">Administrador total</small><?php endif; ?></td><td><?= (int) $perfil['usuarios'] ?></td><td><?= (int) $perfil['ativo'] ? 'Ativo' : 'Inativo' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/perfis/index.php?editar=' . (int) $perfil['id'])) ?>"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $perfil['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Excluir perfil?" <?= (int) $perfil['administrador'] || (int) $perfil['usuarios'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
                <?php if (!$perfis): ?><p class="text-secondary mb-0">Nenhum perfil cadastrado.</p><?php endif; ?>
                <?= pagination_links($perfisPage) ?>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
