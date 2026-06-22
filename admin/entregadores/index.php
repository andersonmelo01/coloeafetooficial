<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_csrf();
    if (!admin_is_super()) {
        flash('danger', 'Somente usuario administrador total pode criar ou alterar usuarios.');
        redirect('admin/entregadores/index.php');
    }

    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'criar') {
        $senha = (string) ($_POST['senha'] ?? '');
        if (strlen($senha) < 6) {
            flash('warning', 'Informe uma senha com pelo menos 6 caracteres.');
            redirect('admin/entregadores/index.php');
        }

        try {
            db()->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo)
                 VALUES (:nome, :email, :senha, 'entregador', 1)"
            )->execute([
                'nome' => trim((string) $_POST['nome']),
                'email' => trim((string) $_POST['email']),
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);
            flash('success', 'Entregador cadastrado.');
        } catch (Throwable $e) {
            flash('danger', 'Nao foi possivel cadastrar. Verifique se o e-mail ja existe.');
        }
        redirect('admin/entregadores/index.php');
    }

    if ($acao === 'status') {
        db()->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id AND tipo = 'entregador'")->execute([
            'id' => (int) $_POST['id'],
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ]);
        flash('success', 'Entregador atualizado.');
        redirect('admin/entregadores/index.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$entregadoresPage = paginate_query(
    "SELECT u.*, COUNT(en.id) AS entregas_total
     FROM usuarios u
     LEFT JOIN entregas en ON en.entregador_id = u.id
     WHERE u.tipo = 'entregador'
       AND (:q = '' OR u.nome LIKE :like_q OR u.email LIKE :like_q)
     GROUP BY u.id
     ORDER BY u.criado_em DESC",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$entregadores = $entregadoresPage['rows'];

$pageTitle = 'Entregadores';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="row g-4">
        <div class="col-lg-5"><div class="panel-card bg-white p-4"><?php if (admin_is_super()): ?><h1 class="h4 section-title">Novo entregador</h1><form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="acao" value="criar"><div class="col-12"><label class="form-label">Nome</label><input class="form-control" name="nome" required></div><div class="col-12"><label class="form-label">E-mail</label><input class="form-control" name="email" type="email" required></div><div class="col-12"><label class="form-label">Senha</label><input class="form-control" name="senha" type="password" minlength="6" required></div><div class="col-12"><button class="btn btn-brand"><i class="bi bi-person-plus"></i> Cadastrar</button></div></form><?php else: ?><div class="alert alert-warning mb-0">Somente administrador total pode criar ou alterar usuarios entregadores.</div><?php endif; ?></div></div>
        <div class="col-lg-7"><div class="panel-card bg-white p-4"><div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4"><h2 class="h4 section-title mb-0">Entregadores</h2><form class="search-control" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nome ou e-mail"><button class="btn btn-brand">Buscar</button></div></form></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nome</th><th>E-mail</th><th>Entregas</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($entregadores as $entregador): ?><tr><td><?= e($entregador['nome']) ?></td><td><?= e($entregador['email']) ?></td><td><?= (int) $entregador['entregas_total'] ?></td><td><?= (int) $entregador['ativo'] ? 'Ativo' : 'Inativo' ?></td><td><?php if (admin_is_super()): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="status"><input type="hidden" name="id" value="<?= (int) $entregador['id'] ?>"><label class="form-check form-switch mb-0"><input class="form-check-input js-auto-submit" type="checkbox" name="ativo" <?= (int) $entregador['ativo'] ? 'checked' : '' ?>></label></form><?php else: ?><span class="text-secondary small">Somente leitura</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$entregadores): ?><p class="text-secondary mb-0">Nenhum entregador cadastrado.</p><?php endif; ?><?= pagination_links($entregadoresPage) ?></div></div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
