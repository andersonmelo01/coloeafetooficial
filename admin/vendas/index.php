<?php
require_once __DIR__ . '/_pdv.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'adicionar') {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = max(1, (int) ($_POST['quantidade'] ?? 1));
        $produto = db_one("SELECT id, nome, estoque FROM produtos WHERE id = :id AND ativo = 1", ['id' => $produtoId]);
        if ($produto) {
            if (pdv_stock_control() && (int) $produto['estoque'] > 0) {
                $quantidade = min($quantidade, (int) $produto['estoque']);
            }
            pdv_cart_add($produtoId, $quantidade);
            flash('success', "{$produto['nome']} adicionado ao carrinho.");
        } else {
            flash('danger', 'Produto nao encontrado ou inativo.');
        }
    } elseif ($acao === 'atualizar') {
        $indice = (int) ($_POST['indice'] ?? 0);
        $quantidade = max(1, (int) ($_POST['quantidade'] ?? 1));
        $item = pdv_cart()[$indice] ?? null;
        if ($item) {
            $produto = db_one("SELECT id, nome, estoque FROM produtos WHERE id = :id", ['id' => (int) $item['produto_id']]);
            if ($produto && pdv_stock_control() && (int) $produto['estoque'] > 0) {
                $quantidade = min($quantidade, (int) $produto['estoque']);
            }
            pdv_cart_set_quantidade($indice, $quantidade);
            flash('success', 'Carrinho atualizado.');
        }
    } elseif ($acao === 'remover') {
        pdv_cart_remove((int) ($_POST['indice'] ?? 0));
        flash('success', 'Item removido.');
    } elseif ($acao === 'limpar') {
        pdv_cart_clear();
        flash('success', 'Venda cancelada.');
    }

    redirect('admin/vendas/index.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$produtosPage = paginate_query(
    "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
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
        AND (pr.data_inicio IS NULL OR pr.data_inicio <= CURDATE())
        AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())
        AND pr.id = (
            SELECT pr2.id FROM promocoes pr2
            WHERE pr2.produto_id = p.id AND pr2.ativo = 1
            ORDER BY pr2.id DESC LIMIT 1
        )
     WHERE p.ativo = 1 AND (:q = '' OR p.nome LIKE :like_q OR p.sku LIKE :like_q OR p.descricao_curta LIKE :like_q)
     ORDER BY p.nome",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$produtos = $produtosPage['rows'];

$caixa = pdv_caixa_aberto();
$cart = pdv_cart();
$cartTotal = pdv_cart_subtotal();

$pageTitle = 'PDV Fácil';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4">
<div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
<div class="col-lg-9">
    <h1 class="h3 section-title">PDV Fácil - Venda presencial</h1>

    <?php if (!$caixa): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle"></i> Nenhum caixa aberto. Para registrar vendas presenciais, abra o caixa primeiro.</span>
            <a class="btn btn-brand btn-sm" href="<?= e(base_url('admin/vendas/caixa.php')) ?>">Abrir caixa</a>
        </div>
    <?php else: ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <span><i class="bi bi-cash-stack"></i> Caixa <?= (int) $caixa['id'] ?> aberto em <?= e($caixa['aberto_em']) ?> · Saldo: <strong><?= money_br(pdv_caixa_saldo($caixa)) ?></strong></span>
            <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin/vendas/caixa.php')) ?>">Gerenciar caixa</a>
        </div>
    <?php endif; ?>

    <?php pdv_portal_tabs('index'); ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card bg-white p-4">
                <form class="search-control mb-3" method="get">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar produto por nome ou SKU" autofocus>
                        <button class="btn btn-brand">Buscar</button>
                    </div>
                </form>
                <?php if ($produtos): ?>
                    <div class="row g-3 pdv-grid">
                        <?php foreach ($produtos as $p): ?>
                            <?php $preco = promotion_price($p); ?>
                            <?php $semEstoque = pdv_stock_control() && (int) $p['estoque'] <= 0; ?>
                            <div class="col-6 col-md-4 col-xl-3">
                                <div class="pdv-card-product <?= $semEstoque ? 'pdv-card-product-disabled' : '' ?>">
                                    <?php if (!empty($p['imagem_principal'])): ?>
                                        <img class="pdv-product-img" src="<?= e(base_url($p['imagem_principal'])) ?>" alt="<?= e($p['nome']) ?>">
                                    <?php else: ?>
                                        <div class="pdv-product-img pdv-product-img-empty"><i class="bi bi-box-seam"></i></div>
                                    <?php endif; ?>
                                    <div class="pdv-product-name"><?= e($p['nome']) ?></div>
                                    <div class="pdv-product-meta">
                                        <?php if (empty($p['preco_promocional']) && empty($p['promo_percentual'])): ?>
                                            <span class="pdv-product-price"><?= money_br($preco) ?></span>
                                        <?php else: ?>
                                            <span class="pdv-product-price"><?= money_br($preco) ?></span>
                                            <span class="pdv-product-old-price"><?= money_br((float) $p['preco']) ?></span>
                                        <?php endif; ?>
                                        <?php if (pdv_stock_control()): ?>
                                            <span class="badge text-bg-light">Est.: <?= (int) $p['estoque'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($semEstoque): ?>
                                        <button class="btn btn-light btn-sm w-100 mt-2" type="button" disabled>Sem estoque</button>
                                    <?php else: ?>
                                        <form method="post" class="pdv-quick-add mt-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="acao" value="adicionar">
                                            <input type="hidden" name="produto_id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="quantidade" value="1">
                                            <button class="btn btn-brand w-100" title="Adicionar ao carrinho">
                                                <i class="bi bi-bag-plus"></i> Adicionar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?= pagination_links($produtosPage) ?>
                <?php else: ?>
                    <p class="text-secondary mb-0">Nenhum produto encontrado.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 section-title mb-0"><i class="bi bi-cart3"></i> Venda em andamento</h2>
                    <?php if ($cart): ?>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="limpar">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Descartar a venda em andamento?">Cancelar venda</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!$cart): ?>
                    <p class="text-secondary mb-0">Adicione produtos para iniciar a venda.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Cód.</th><th>Item</th><th class="text-end">Preço</th><th class="text-center">Qtd</th><th class="text-end">Total</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($cart as $indice => $item): ?>
                                <tr>
                                    <td class="text-secondary"><?= (int) $item['produto_id'] + 1000 ?></td>
                                    <td><?= e($item['nome']) ?></td>
                                    <td class="text-end"><?= money_br((float) $item['preco']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-1 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="acao" value="atualizar">
                                            <input type="hidden" name="indice" value="<?= (int) $indice ?>">
                                            <input class="form-control form-control-sm text-center" style="width: 56px" type="number" name="quantidade" value="<?= (int) $item['quantidade'] ?>" min="1">
                                            <button class="btn btn-sm btn-outline-brand" title="Atualizar quantidade"><i class="bi bi-check"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-end"><?= money_br((float) $item['preco'] * (int) $item['quantidade']) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="acao" value="remover">
                                            <input type="hidden" name="indice" value="<?= (int) $indice ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Remover item"><i class="bi bi-x"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-3">
                        <span class="fs-5"><?= pdv_cart_count() ?> item(ns)</span>
                        <strong class="fs-4"><?= money_br($cartTotal) ?></strong>
                    </div>
                    <a class="btn btn-brand btn-lg w-100" href="<?= e(base_url('admin/vendas/finalizar.php')) ?>">
                        <i class="bi bi-cash-coin"></i> Finalizar venda
                    </a>
                    <div class="small text-secondary mt-2"><?= $caixa ? 'O pagamento será lançado no caixa aberto.' : 'Abra o caixa para concluir a venda.' ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>