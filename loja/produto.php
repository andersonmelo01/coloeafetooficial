<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$produto = db_one(
    "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
            pr.titulo AS promo_titulo,
            pr.descricao AS promo_descricao,
            pr.preco_promocional AS promo_preco,
            pr.percentual_desconto AS promo_percentual
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
       AND ((:slug <> '' AND p.slug = :slug) OR (:id > 0 AND p.id = :id))
     LIMIT 1",
    ['slug' => $slug, 'id' => $id]
);

if (!$produto) {
    flash('warning', 'Produto não encontrado.');
    redirect('loja/index.php');
}

$imagens = product_images((int) $produto['id']);
$preco = promotion_price($produto);
$estoque = (int) ($produto['estoque'] ?? 0);
$temPromocao = !empty($produto['promo_titulo']) || $preco < (float) $produto['preco'];
$vendasHabilitadas = loja_vendas_enabled();
$pageTitle = $produto['nome'];
$active = 'loja';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="py-5 bg-white">
    <div class="container py-4">
        <a class="btn btn-sm btn-outline-brand mb-4" href="<?= e(base_url('loja/index.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar para loja</a>
        <div class="row g-5 align-items-start">
            <div class="col-lg-6">
                <?php if ($imagens): ?>
                    <div id="produtoCarousel" class="carousel slide product-detail-carousel" data-bs-ride="carousel">
                        <div class="carousel-indicators">
                            <?php foreach ($imagens as $index => $imagem): ?>
                                <button type="button" data-bs-target="#produtoCarousel" data-bs-slide-to="<?= (int) $index ?>" class="<?= $index === 0 ? 'active' : '' ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="Imagem <?= (int) $index + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="carousel-inner">
                            <?php foreach ($imagens as $index => $imagem): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <img src="<?= e(base_url($imagem['caminho'])) ?>" class="d-block w-100" alt="<?= e($produto['nome']) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($imagens) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#produtoCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#produtoCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Próxima</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="product-detail-placeholder">
                        <i class="bi bi-bag-heart"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge text-bg-light"><?= e($produto['categoria'] ?: 'Categoria') ?></span>
                    <span class="badge text-bg-warning"><?= e($produto['grupo'] ?: 'Grupo') ?></span>
                    <?php if ($temPromocao): ?><span class="badge text-bg-danger"><i class="bi bi-megaphone"></i> Promoção</span><?php endif; ?>
                    <?php if (!$vendasHabilitadas): ?><span class="badge text-bg-secondary">Catálogo</span><?php endif; ?>
                </div>
                <?php if (!$vendasHabilitadas): ?><div class="alert alert-warning"><i class="bi bi-info-circle"></i> <?= e(loja_catalog_message()) ?></div><?php endif; ?>
                <?php if (!empty($produto['promo_titulo'])): ?><div class="fw-bold text-danger mb-2"><?= e($produto['promo_titulo']) ?></div><?php endif; ?>
                <h1 class="display-6 section-title mb-3"><?= e($produto['nome']) ?></h1>
                <p class="text-secondary lead"><?= e($produto['descricao_curta'] ?? '') ?></p>
                <div class="my-4">
                    <?php if ($preco < (float) $produto['preco']): ?>
                        <div class="text-secondary text-decoration-line-through"><?= money_br((float) $produto['preco']) ?></div>
                    <?php endif; ?>
                    <strong class="display-6 text-danger"><?= money_br($preco) ?></strong>
                    <div class="small text-secondary mt-1">Estoque disponível: <?= $estoque ?></div>
                </div>
                <?php if (!empty($produto['descricao'])): ?>
                    <div class="border-top border-bottom py-4 mb-4">
                        <h2 class="h5">Descrição</h2>
                        <p class="mb-0 text-secondary"><?= nl2br(e($produto['descricao'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($vendasHabilitadas): ?>
                    <form method="post" action="<?= e(base_url('carrinho/adicionar.php')) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $produto['id'] ?>">
                        <input type="hidden" name="nome" value="<?= e($produto['nome']) ?>">
                        <input type="hidden" name="preco" value="<?= e((string) $preco) ?>">
                        <input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? base_url('loja/produto.php?id=' . (int) $produto['id'])) ?>">
                        <div class="col-md-4"><input class="form-control" name="quantidade" type="number" min="1" max="<?= max(1, $estoque) ?>" value="1" <?= $estoque <= 0 ? 'disabled' : '' ?>></div>
                        <div class="col-md-8"><button class="btn btn-brand w-100" type="submit" <?= $estoque <= 0 ? 'disabled' : '' ?>><i class="bi bi-bag-plus"></i> Adicionar ao carrinho</button></div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
