<?php
$pageTitle = 'Carrinho';
$active = 'loja';
require_once dirname(__DIR__) . '/includes/header.php';
$items = cart_items();
$vendasHabilitadas = loja_vendas_enabled();
?>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="panel-card bg-white p-4">
                    <h1 class="h3 section-title mb-4">Meu carrinho</h1>
                    <?php if (!$vendasHabilitadas): ?><div class="alert alert-warning"><i class="bi bi-info-circle"></i> <?= e(loja_catalog_message()) ?></div><?php endif; ?>
                    <?php if (!$items): ?>
                        <p class="text-secondary">Seu carrinho esta vazio.</p>
                        <a class="btn btn-brand" href="<?= e(base_url('loja/index.php')) ?>"><i class="bi bi-search"></i> Buscar produtos</a>
                    <?php else: ?>
                        <form method="post" action="<?= e(base_url('carrinho/atualizar.php')) ?>">
                            <?= csrf_field() ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Preco</th>
                                            <th style="width: 130px;">Quantidade</th>
                                            <th class="text-end">Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= e($item['nome']) ?></td>
                                                <td><?= money_br((float) $item['preco']) ?></td>
                                                <td><input class="form-control" type="number" min="0" name="quantidades[<?= (int) $item['id'] ?>]" value="<?= (int) $item['quantidade'] ?>"></td>
                                                <td class="text-end"><?= money_br((float) $item['preco'] * (int) $item['quantidade']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger" form="remove-<?= (int) $item['id'] ?>" data-confirm="Remover este item?"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-outline-brand" type="submit"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
                            <?php if ($vendasHabilitadas): ?><a class="btn btn-brand" href="<?= e(base_url('checkout/index.php')) ?>"><i class="bi bi-credit-card"></i> Finalizar compra</a><?php endif; ?>
                        </form>
                        <?php foreach ($items as $item): ?>
                            <form id="remove-<?= (int) $item['id'] ?>" method="post" action="<?= e(base_url('carrinho/remover.php')) ?>" class="d-none">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="panel-card bg-white p-4">
                    <h2 class="h5">Resumo</h2>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Itens</span><strong><?= cart_count() ?></strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Subtotal</span><strong><?= money_br(cart_total()) ?></strong></div>
                    <div class="d-flex justify-content-between py-2"><span>Frete</span><span class="text-secondary">Calculado no checkout</span></div>
                    <?php if ($vendasHabilitadas): ?><a class="btn btn-brand w-100 mt-3" href="<?= e(base_url('checkout/index.php')) ?>">Continuar</a><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
