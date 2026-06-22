<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_csrf();
    if (!admin_is_super()) {
        flash('danger', 'Somente usuario administrador total pode criar, alterar ou excluir usuarios.');
        redirect('admin/gestores/index.php');
    }

    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $perfilId = $_POST['perfil_admin_id'] !== '' ? (int) $_POST['perfil_admin_id'] : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '' || $email === '') {
            flash('warning', 'Informe nome e e-mail.');
            redirect('admin/gestores/index.php');
        }

        try {
            if ($id > 0) {
                $params = [
                    'id' => $id,
                    'nome' => $nome,
                    'email' => $email,
                    'perfil_admin_id' => $perfilId,
                    'ativo' => $ativo,
                ];
                $sqlSenha = '';
                if ($senha !== '') {
                    $sqlSenha = ', senha_hash = :senha';
                    $params['senha'] = password_hash($senha, PASSWORD_DEFAULT);
                }
                db()->prepare(
                    "UPDATE usuarios
                     SET nome = :nome, email = :email, perfil_admin_id = :perfil_admin_id, ativo = :ativo {$sqlSenha}
                     WHERE id = :id AND tipo = 'admin'"
                )->execute($params);
                if ($id === (int) (current_user()['id'] ?? 0)) {
                    $_SESSION['usuario']['nome'] = $nome;
                    $_SESSION['usuario']['email'] = $email;
                    $_SESSION['usuario']['perfil_admin_id'] = $perfilId;
                }
                flash('success', 'Gestor atualizado.');
            } else {
                if (strlen($senha) < 6) {
                    flash('warning', 'Informe uma senha com pelo menos 6 caracteres.');
                    redirect('admin/gestores/index.php');
                }
                db()->prepare(
                    "INSERT INTO usuarios (nome, email, senha_hash, tipo, perfil_admin_id, ativo)
                     VALUES (:nome, :email, :senha, 'admin', :perfil_admin_id, :ativo)"
                )->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => password_hash($senha, PASSWORD_DEFAULT),
                    'perfil_admin_id' => $perfilId,
                    'ativo' => $ativo,
                ]);
                flash('success', 'Gestor cadastrado.');
            }
        } catch (Throwable $e) {
            flash('danger', 'Nao foi possivel salvar. Verifique se o e-mail ja existe.');
        }
        redirect('admin/gestores/index.php');
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) (current_user()['id'] ?? 0)) {
            flash('warning', 'Voce nao pode excluir seu proprio usuario.');
            redirect('admin/gestores/index.php');
        }

        db()->prepare("DELETE FROM usuarios WHERE id = :id AND tipo = 'admin'")->execute(['id' => $id]);
        flash('success', 'Gestor excluido.');
        redirect('admin/gestores/index.php');
    }
}

$editId = (int) ($_GET['editar'] ?? 0);
$editGestor = $editId ? db_one("SELECT * FROM usuarios WHERE id = :id AND tipo = 'admin'", ['id' => $editId]) : null;
$perfisSql = admin_is_super()
    ? "SELECT * FROM admin_perfis WHERE ativo = 1 ORDER BY administrador DESC, nome"
    : "SELECT * FROM admin_perfis WHERE ativo = 1 AND administrador = 0 ORDER BY nome";
$perfis = db_all($perfisSql);
$gestoresPage = paginate_query(
    "SELECT u.*, ap.nome AS perfil_nome, ap.administrador
     FROM usuarios u
     LEFT JOIN admin_perfis ap ON ap.id = u.perfil_admin_id
     WHERE u.tipo = 'admin'
     ORDER BY u.criado_em DESC"
);
$gestores = $gestoresPage['rows'];

$pageTitle = 'Gestores';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card bg-white p-4">
                <?php if (!admin_is_super()): ?>
                    <div class="alert alert-warning mb-0">Somente administrador total pode criar, alterar ou excluir usuarios administrativos.</div>
                <?php else: ?>
                <h1 class="h4 section-title"><?= $editGestor ? 'Editar gestor' : 'Novo gestor' ?></h1>
                <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) ($editGestor['id'] ?? 0) ?>">
                    <div class="col-12"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?= e($editGestor['nome'] ?? '') ?>" required></div>
                    <div class="col-12"><label class="form-label">E-mail</label><input class="form-control" name="email" type="email" value="<?= e($editGestor['email'] ?? '') ?>" required></div>
                    <div class="col-12"><label class="form-label"><?= $editGestor ? 'Nova senha' : 'Senha' ?></label><input class="form-control" name="senha" type="password" <?= $editGestor ? '' : 'required minlength="6"' ?>><div class="form-text"><?= $editGestor ? 'Deixe em branco para manter a senha atual.' : 'Minimo de 6 caracteres.' ?></div></div>
                    <div class="col-12"><label class="form-label">Perfil administrativo</label><select class="form-select" name="perfil_admin_id"><?php if (admin_is_super()): ?><option value="">Administrador total sem perfil limitado</option><?php endif; ?><?php foreach ($perfis as $perfil): ?><option value="<?= (int) $perfil['id'] ?>" <?= (int) ($editGestor['perfil_admin_id'] ?? 0) === (int) $perfil['id'] ? 'selected' : '' ?>><?= e($perfil['nome']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" <?= !$editGestor || (int) $editGestor['ativo'] ? 'checked' : '' ?>> Ativo</label></div>
                    <div class="col-12"><button class="btn btn-brand"><i class="bi bi-save"></i> Salvar gestor</button> <?php if ($editGestor): ?><a class="btn btn-outline-secondary" href="<?= e(base_url('admin/gestores/index.php')) ?>">Cancelar</a><?php endif; ?></div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel-card bg-white p-4">
                <h2 class="h4 section-title">Gestores cadastrados</h2>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Gestor</th><th>Perfil</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($gestores as $gestor): ?><tr><td><?= e($gestor['nome']) ?><br><small><?= e($gestor['email']) ?></small></td><td><?= e($gestor['perfil_nome'] ?: 'Administrador total') ?></td><td><?= (int) $gestor['ativo'] ? 'Ativo' : 'Inativo' ?></td><td class="text-end"><?php if (admin_is_super()): ?><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/gestores/index.php?editar=' . (int) $gestor['id'])) ?>"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $gestor['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Excluir gestor?" <?= (int) $gestor['id'] === (int) (current_user()['id'] ?? 0) ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form><?php else: ?><span class="text-secondary small">Somente leitura</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
                <?php if (!$gestores): ?><p class="text-secondary mb-0">Nenhum gestor cadastrado.</p><?php endif; ?>
                <?= pagination_links($gestoresPage) ?>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
