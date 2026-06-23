<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login('cliente');

$pedidos = db_all("SELECT * FROM pedidos WHERE usuario_id = :id ORDER BY criado_em DESC LIMIT 5", ['id' => current_user()['id']]);
$chamados = db_all("SELECT * FROM chamados WHERE usuario_id = :id ORDER BY criado_em DESC LIMIT 5", ['id' => current_user()['id']]);
$notas = db_all("SELECT nf.* FROM notas_fiscais nf JOIN pedidos p ON p.id = nf.pedido_id WHERE p.usuario_id = :id ORDER BY nf.emitida_em DESC LIMIT 5", ['id' => current_user()['id']]);

$pageTitle = 'Area do Cliente';
$active = 'cliente';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php require __DIR__ . '/menu.php'; ?>
            </div>
            <div class="col-lg-9">
                <h1 class="h3 section-title">Ola, <?= e(current_user()['nome']) ?></h1>
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="panel-card metric bg-white p-4"><span class="text-secondary">Pedidos</span><strong class="d-block fs-3"><?= count($pedidos) ?></strong></div></div>
                    <div class="col-md-4"><div class="panel-card metric bg-white p-4"><span class="text-secondary">NF-e</span><strong class="d-block fs-3"><?= count($notas) ?></strong></div></div>
                    <div class="col-md-4"><div class="panel-card metric bg-white p-4"><span class="text-secondary">Chamados</span><strong class="d-block fs-3"><?= count($chamados) ?></strong></div></div>
                </div>
                <div class="panel-card bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Ultimos pedidos</h2>
                        <a href="<?= e(base_url('cliente/pedidos.php')) ?>">Ver todos</a>
                    </div>
                    <?php if (!$pedidos): ?>
                        <p class="text-secondary mb-0">Nenhum pedido encontrado.</p>
                    <?php else: ?>
                        <div class="table-responsive mobile-card-table">
                            <table class="table align-middle">
                                <thead><tr><th>Pedido</th><th>Status</th><th>Total</th><th>Data</th></tr></thead>
                                <tbody>
                                    <?php foreach ($pedidos as $pedido): ?>
                                        <tr>
                                            <td class="mobile-card-title">#<?= (int) $pedido['id'] ?></td>
                                            <td data-label="Status"><?= e($pedido['status']) ?></td>
                                            <td data-label="Total"><?= money_br((float) $pedido['total']) ?></td>
                                            <td data-label="Data"><?= e($pedido['criado_em']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
