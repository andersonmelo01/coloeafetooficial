<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    if (($_POST['acao'] ?? '') === 'excluir') {
        $imagePaths = [];
        try {
            $id = (int) $_POST['id'];
            $imagePaths = array_column(product_images($id), 'caminho');
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM produto_imagens WHERE produto_id = :id")->execute(['id' => $id]);
            $pdo->prepare("DELETE FROM produtos WHERE id = :id")->execute(['id' => $id]);
            $pdo->commit();
            foreach ($imagePaths as $path) {
                $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $path);
                $realUploads = realpath(product_upload_dir());
                $realFile = realpath($absolute);
                if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
                    unlink($realFile);
                }
            }
            flash('success', 'Produto excluido.');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Nao foi possivel excluir. Verifique se o produto possui pedidos vinculados.');
        }
        redirect('admin/produtos/index.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$produtosPage = paginate_query(
    "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
            (SELECT pi.caminho
             FROM produto_imagens pi
             WHERE pi.produto_id = p.id
             ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
             LIMIT 1) AS imagem_principal
     FROM produtos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     LEFT JOIN grupos_produtos g ON g.id = p.grupo_id
     WHERE (:q = '' OR p.nome LIKE :like_q OR p.sku LIKE :like_q)
     ORDER BY p.criado_em DESC",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$produtos = $produtosPage['rows'];
$pageTitle = 'Produtos';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4"><h1 class="h3 section-title mb-0">Produtos</h1><a class="btn btn-brand" href="<?= e(base_url('admin/produtos/novo.php')) ?>"><i class="bi bi-plus-lg"></i> Novo produto</a></div>
    <form class="search-control mb-4" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pesquisar por nome ou SKU"><button class="btn btn-brand">Buscar</button></div></form>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Imagem</th><th>SKU</th><th>Produto</th><th>Categoria</th><th>Grupo</th><th>Preco</th><th>Estoque</th><th>Fiscal NF-e</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($produtos as $p): ?><?php $issues = produto_fiscal_issues($p); ?><tr><td><?php if (!empty($p['imagem_principal'])): ?><img class="product-list-thumb" src="<?= e(base_url($p['imagem_principal'])) ?>" alt="<?= e($p['nome']) ?>"><?php else: ?><span class="product-list-thumb is-empty"><i class="bi bi-image"></i></span><?php endif; ?></td><td><?= e($p['sku']) ?></td><td><?= e($p['nome']) ?></td><td><?= e($p['categoria']) ?></td><td><?= e($p['grupo']) ?></td><td><?= money_br((float) $p['preco']) ?></td><td><?= (int) $p['estoque'] ?></td><td><?php if ($issues['errors']): ?><span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> Ajustar fiscal</span><div class="small text-secondary mt-1"><?= e(implode(' | ', $issues['errors'])) ?></div><?php elseif ($issues['warnings']): ?><span class="badge text-bg-warning"><i class="bi bi-info-circle"></i> Revisar</span><div class="small text-secondary mt-1"><?= e(implode(' | ', $issues['warnings'])) ?></div><?php else: ?><span class="badge text-bg-success"><i class="bi bi-check2-circle"></i> Fiscal OK</span><?php endif; ?></td><td><?= (int) $p['ativo'] ? 'Ativo' : 'Inativo' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/produtos/editar.php?id=' . (int) $p['id'])) ?>" title="Editar produto"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Excluir produto?" title="Excluir produto"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$produtos): ?><p class="text-secondary mb-0">Nenhum produto cadastrado.</p><?php endif; ?><?= pagination_links($produtosPage) ?></div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
