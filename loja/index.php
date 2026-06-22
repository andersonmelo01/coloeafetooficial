<?php
$pageTitle = 'Loja Virtual';
$active = 'loja';
require_once dirname(__DIR__) . '/includes/header.php';

$busca = trim((string) ($_GET['q'] ?? ''));
$categoria = trim((string) ($_GET['categoria'] ?? ''));
$queryTodos = $busca !== '' ? '?q=' . urlencode($busca) : '';
$vendasHabilitadas = loja_vendas_enabled();

$produtosPage = paginate_query(
    "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
            pr.titulo AS promo_titulo,
            pr.preco_promocional AS promo_preco,
            pr.percentual_desconto AS promo_percentual,
            (SELECT pi.caminho
             FROM produto_imagens pi
             WHERE pi.produto_id = p.id
             ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
             LIMIT 1) AS imagem_principal
     FROM produtos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     LEFT JOIN grupos_produtos g ON g.id = p.grupo_id
     LEFT JOIN promocoes pr ON pr.produto_id = p.id
        AND pr.ativo = 1
        AND pr.destaque = 1
        AND (pr.data_inicio IS NULL OR pr.data_inicio <= CURDATE())
        AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())
        AND pr.id = (
            SELECT pr2.id
            FROM promocoes pr2
            WHERE pr2.produto_id = p.id
              AND pr2.ativo = 1
              AND pr2.destaque = 1
              AND (pr2.data_inicio IS NULL OR pr2.data_inicio <= CURDATE())
              AND (pr2.data_fim IS NULL OR pr2.data_fim >= CURDATE())
            ORDER BY pr2.criado_em DESC
            LIMIT 1
        )
     WHERE p.ativo = 1
       AND (:busca = '' OR p.nome LIKE :like_busca OR p.sku LIKE :like_busca)
       AND (:categoria = '' OR c.slug = :categoria)
     ORDER BY pr.destaque DESC, p.destaque DESC, p.nome ASC",
    [
        'busca' => $busca,
        'like_busca' => '%' . $busca . '%',
        'categoria' => $categoria,
    ]
);
$produtos = $produtosPage['rows'];

if (!$produtos && $busca === '' && $categoria === '') {
    $produtos = sample_products();
    $produtosPage = ['total' => count($produtos), 'page' => 1, 'pages' => 1, 'per_page' => 15, 'page_param' => 'pagina'];
}

$categorias = db_all("SELECT nome, slug FROM categorias WHERE ativo = 1 ORDER BY nome");
?>

<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="row align-items-end g-4">
            <div class="col-lg-7">
                <p class="eyebrow">Loja virtual</p>
                <h1 class="display-5 section-title mb-3">Produtos selecionados para maternidade e bebe.</h1>
                <p class="text-secondary mb-0">Catalogo preparado para produtos, categorias, grupos, estoque, checkout e pedidos.</p>
                <?php if (!$vendasHabilitadas): ?><div class="alert alert-warning mt-4 mb-0"><i class="bi bi-info-circle"></i> <?= e(loja_catalog_message()) ?></div><?php endif; ?>
            </div>
            <div class="col-lg-5">
                <form class="search-control" method="get">
                    <label class="form-label fw-semibold" for="q">Pesquisar produtos</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="q" name="q" value="<?= e($busca) ?>" placeholder="Digite nome ou SKU">
                        <button class="btn btn-brand" type="submit">Buscar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="py-4">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a class="btn btn-sm <?= $categoria === '' ? 'btn-brand' : 'btn-outline-brand' ?>" href="<?= e(base_url('loja/index.php' . $queryTodos)) ?>">Todos</a>
            <?php foreach ($categorias as $cat): ?>
                <?php $catQuery = '?' . http_build_query(array_filter(['q' => $busca, 'categoria' => $cat['slug']])); ?>
                <a class="btn btn-sm <?= $categoria === $cat['slug'] ? 'btn-brand' : 'btn-outline-brand' ?>" href="<?= e(base_url('loja/index.php' . $catQuery)) ?>">
                    <?= e($cat['nome']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <?php foreach ($produtos as $produto): ?>
                <?php
                $preco = promotion_price($produto);
                $estoque = (int) ($produto['estoque'] ?? 0);
                $temPromocao = !empty($produto['promo_titulo']) || !empty($produto['preco_promocional']);
                ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="product-card bg-white h-100 overflow-hidden">
                        <a class="product-media product-media-link" href="<?= e(base_url('loja/produto.php?slug=' . urlencode((string) ($produto['slug'] ?? '')))) ?>">
                            <?php if (!empty($produto['imagem_principal'])): ?>
                                <img src="<?= e(base_url($produto['imagem_principal'])) ?>" alt="<?= e($produto['nome']) ?>">
                            <?php else: ?>
                                <i class="bi bi-bag-heart display-4"></i>
                            <?php endif; ?>
                        </a>
                        <div class="p-4">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <span class="badge text-bg-light"><?= e($produto['categoria'] ?? 'Categoria') ?></span>
                                <?php if ($temPromocao): ?><span class="badge text-bg-danger"><i class="bi bi-megaphone"></i> Promo</span><?php endif; ?>
                                <span class="badge text-bg-warning"><?= e($produto['grupo'] ?? 'Grupo') ?></span>
                            </div>
                            <?php if (!empty($produto['promo_titulo'])): ?><div class="small fw-bold text-danger mb-1"><?= e($produto['promo_titulo']) ?></div><?php endif; ?>
                            <h2 class="h5"><a class="text-decoration-none text-reset" href="<?= e(base_url('loja/produto.php?slug=' . urlencode((string) ($produto['slug'] ?? '')))) ?>"><?= e($produto['nome']) ?></a></h2>
                            <p class="text-secondary small"><?= e($produto['descricao_curta'] ?? '') ?></p>
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <?php if ($preco < (float) $produto['preco']): ?>
                                        <div class="small text-decoration-line-through text-secondary"><?= money_br((float) $produto['preco']) ?></div>
                                    <?php endif; ?>
                                    <strong class="fs-5 text-danger"><?= money_br($preco) ?></strong>
                                    <div class="small text-secondary">Estoque: <?= $estoque ?></div>
                                </div>
                                <?php if ($vendasHabilitadas): ?>
                                    <form method="post" action="<?= e(base_url('carrinho/adicionar.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $produto['id'] ?>">
                                        <input type="hidden" name="nome" value="<?= e($produto['nome']) ?>">
                                        <input type="hidden" name="preco" value="<?= e((string) $preco) ?>">
                                        <input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? base_url('loja/index.php')) ?>">
                                        <button class="btn btn-brand" type="submit" <?= $estoque <= 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-plus-lg"></i> Adicionar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge text-bg-light">Catalogo</span>
                                <?php endif; ?>
                            </div>
                            <a class="btn btn-sm btn-outline-brand w-100 mt-3" href="<?= e(base_url('loja/produto.php?slug=' . urlencode((string) ($produto['slug'] ?? '')))) ?>"><i class="bi bi-eye"></i> Ver detalhes</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$produtos): ?><p class="text-secondary mb-0">Nenhum produto localizado.</p><?php endif; ?>
        <?= pagination_links($produtosPage) ?>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
