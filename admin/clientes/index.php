<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    if (($_POST['acao'] ?? '') === 'excluir') {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $id = (int) $_POST['id'];
            $pdo->prepare("DELETE FROM enderecos WHERE usuario_id = :id")->execute(['id' => $id]);
            $pdo->prepare("DELETE FROM clientes_perfis WHERE usuario_id = :id")->execute(['id' => $id]);
            $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND tipo = 'cliente'")->execute(['id' => $id]);
            $pdo->commit();
            flash('success', 'Cliente excluido.');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Nao foi possivel excluir. Cliente possui registros vinculados.');
        }
        redirect('admin/clientes/index.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$clientesPage = paginate_query(
    "SELECT u.*, cp.documento, cp.telefone, e.cidade, e.uf
     FROM usuarios u
     LEFT JOIN clientes_perfis cp ON cp.usuario_id = u.id
     LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
     WHERE u.tipo = 'cliente' AND (:q = '' OR u.nome LIKE :like_q OR u.email LIKE :like_q OR cp.documento LIKE :like_q)
     ORDER BY u.criado_em DESC",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$clientes = $clientesPage['rows'];
$pageTitle = 'Clientes';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><h1 class="h3 section-title">Clientes</h1><form class="search-control mb-4" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nome, e-mail ou CPF/CNPJ"><button class="btn btn-brand">Buscar</button></div></form><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Cliente</th><th>E-mail</th><th>Documento</th><th>Telefone</th><th>Cidade</th><th></th></tr></thead><tbody><?php foreach ($clientes as $c): ?><tr><td><?= e($c['nome']) ?></td><td><?= e($c['email']) ?></td><td><?= e($c['documento']) ?></td><td><?= e($c['telefone']) ?></td><td><?= e(trim(($c['cidade'] ?? '') . '/' . ($c['uf'] ?? ''), '/')) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/clientes/editar.php?id=' . (int) $c['id'])) ?>"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Excluir cliente?"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$clientes): ?><p class="text-secondary mb-0">Nenhum cliente localizado.</p><?php endif; ?><?= pagination_links($clientesPage) ?></div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
