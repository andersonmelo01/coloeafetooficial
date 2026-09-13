<?php
require_once __DIR__ . '/_pdv.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';
require_login('admin');

$vendaId = (int) ($_GET['venda'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    if (($_POST['acao'] ?? '') === 'enviar_cupom') {
        $id = (int) ($_POST['venda_id'] ?? 0);
        $email = trim((string) ($_POST['email_envio'] ?? ''));
        $result = pdv_cupom_send_email($id, $email);
        if ($result['ok']) {
            flash('success', $result['message']);
        } else {
            flash('danger', $result['message']);
        }
        redirect('admin/vendas/historico.php?venda=' . $id);
    }

    if (($_POST['acao'] ?? '') === 'cancelar') {
        $id = (int) ($_POST['venda_id'] ?? 0);
        $motivo = trim((string) ($_POST['motivo_cancelamento'] ?? ''));
        $pdo = db();

        try {
            $pdo->beginTransaction();
            $venda = db_one("SELECT * FROM vendas WHERE id = :id FOR UPDATE", ['id' => $id]);
            if (!$venda || !in_array($venda['status'], ['finalizada', 'pendente'], true)) {
                throw new RuntimeException('Venda não está em situação de cancelamento.');
            }

            $pdo->prepare(
                "UPDATE vendas
                 SET status = 'cancelada', cancelada_em = NOW(), cancelada_por = :cancelada_por, motivo_cancelamento = :motivo
                 WHERE id = :id"
            )->execute([
                'cancelada_por' => (int) current_user()['id'],
                'motivo' => $motivo !== '' ? $motivo : null,
                'id' => $id,
            ]);

            $insertMovimento = $pdo->prepare(
                "INSERT INTO movimentos_estoque (produto_id, usuario_id, tipo, quantidade, saldo_anterior, saldo_posterior, motivo, origem, observacao)
                 VALUES (:produto_id, :usuario_id, 'entrada', :quantidade, :saldo_anterior, :saldo_posterior, :motivo, :origem, :observacao)"
            );
            $updateEstoque = $pdo->prepare("UPDATE produtos SET estoque = :estoque WHERE id = :id");

            foreach (db_all("SELECT * FROM venda_itens WHERE venda_id = :venda_id", ['venda_id' => $id]) as $item) {
                $saldoAtual = (int) (db_one("SELECT estoque FROM produtos WHERE id = :id", ['id' => (int) $item['produto_id']])['estoque'] ?? 0);
                $quantidade = (int) $item['quantidade'];
                $saldoNovo = $saldoAtual + $quantidade;
                $updateEstoque->execute(['estoque' => $saldoNovo, 'id' => (int) $item['produto_id']]);
                $insertMovimento->execute([
                    'produto_id' => (int) $item['produto_id'],
                    'usuario_id' => (int) current_user()['id'],
                    'quantidade' => $quantidade,
                    'saldo_anterior' => $saldoAtual,
                    'saldo_posterior' => $saldoNovo,
                    'motivo' => 'Estorno da venda ' . $venda['numero'],
                    'origem' => 'pdv',
                    'observacao' => $motivo !== '' ? $motivo : null,
                ]);
            }

            $pdo->prepare("UPDATE venda_pagamentos SET status = 'cancelado' WHERE venda_id = :venda_id AND status IN ('pago', 'pendente')")->execute(['venda_id' => $id]);
            $pdo->prepare("UPDATE venda_parcelas SET status = 'cancelado' WHERE venda_id = :venda_id AND status IN ('pago', 'pendente')")->execute(['venda_id' => $id]);

            $cancelaPagamento = "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                                 SELECT cm.caixa_id, cm.venda_id, 'saida', cm.metodo, cm.valor, :observacao, :usuario_id
                                 FROM caixa_movimentos cm
                                 WHERE cm.venda_id = :venda_id AND cm.tipo = 'entrada'";
            $pdo->prepare($cancelaPagamento)->execute([
                'observacao' => 'Estorno da venda ' . $venda['numero'],
                'usuario_id' => (int) current_user()['id'],
                'venda_id' => $id,
            ]);

            $pdo->commit();
            flash('success', "Venda {$venda['numero']} cancelada. Estoque e caixa ajustados.");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Não foi possível cancelar a venda: ' . $e->getMessage());
        }

        redirect($vendaId > 0 ? 'admin/vendas/historico.php?venda=' . $vendaId : 'admin/vendas/historico.php');
    }
}

$statusFiltro = trim((string) ($_GET['status'] ?? ''));
if (!in_array($statusFiltro, ['finalizada', 'pendente', 'cancelada', 'aberta'], true)) {
    $statusFiltro = '';
}

$venda = null;
$itens = $pagamentos = $parcelas = [];
if ($vendaId > 0) {
    $venda = db_one(
        "SELECT v.*, vendedor.nome AS vendedor, cliente.nome AS cliente, cliente.email AS cliente_email, caixa.id AS caixa
         FROM vendas v
         LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
         LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
         LEFT JOIN caixas caixa ON caixa.id = v.caixa_id
         WHERE v.id = :id",
        ['id' => $vendaId]
    );
    if ($venda) {
        $itens = db_all("SELECT * FROM venda_itens WHERE venda_id = :venda_id ORDER BY id", ['venda_id' => $vendaId]);
        $pagamentos = db_all("SELECT * FROM venda_pagamentos WHERE venda_id = :venda_id ORDER BY id", ['venda_id' => $vendaId]);
        $parcelas = db_all("SELECT * FROM venda_parcelas WHERE venda_id = :venda_id ORDER BY parcela", ['venda_id' => $vendaId]);
        $temPendenteOnline = count(array_filter($pagamentos, static fn (array $pg) => in_array($pg['metodo'], ['pix', 'cartao_credito', 'cartao_debito'], true) && $pg['status'] === 'pendente')) > 0;
    }
}

$vendasPage = paginate_query(
    "SELECT v.*, vendedor.nome AS vendedor, cliente.nome AS cliente
     FROM vendas v
     LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
     LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
     WHERE (:status = '' OR v.status = :status)
     ORDER BY v.id DESC",
    ['status' => $statusFiltro]
);
$vendas = $vendasPage['rows'];

$pageTitle = 'Vendas PDV';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4">
<div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
<div class="col-lg-9">
    <h1 class="h3 section-title">Vendas do PDV</h1>
    <?php pdv_portal_tabs('historico'); ?>

    <?php if ($venda): ?>
        <div class="panel-card bg-white p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 section-title mb-1"><?= e($venda['numero']) ?></h2>
                    <div class="small text-secondary">
                        <?= e($venda['finalizada_em'] ?? $venda['criado_em']) ?>
                        · Vendedor: <?= e($venda['vendedor']) ?>
                        · Cliente: <?= e($venda['cliente'] ?? 'Venda sem cliente') ?>
                        <?php if (!empty($venda['caixa'])): ?> · Caixa #<?= (int) $venda['caixa'] ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($venda['status'] === 'finalizada'): ?>
                    <span class="badge text-bg-success fs-6">Finalizada</span>
                <?php elseif ($venda['status'] === 'pendente'): ?>
                    <span class="badge text-bg-warning fs-6">Aguarda pagamento</span>
                <?php elseif ($venda['status'] === 'cancelada'): ?>
                    <span class="badge text-bg-secondary fs-6">Cancelada</span>
                <?php else: ?>
                    <span class="badge text-bg-warning fs-6">Aberta</span>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="metric-label">Subtotal</div><strong><?= money_br((float) $venda['subtotal']) ?></strong></div>
                <div class="col-md-3"><div class="metric-label">Desconto</div><strong><?= money_br((float) $venda['desconto']) ?></strong></div>
                <div class="col-md-3"><div class="metric-label">Total</div><strong class="text-brand-price"><?= money_br((float) $venda['total']) ?></strong></div>
                <?php if ($venda['status'] === 'cancelada'): ?>
                    <div class="col-md-3"><div class="metric-label">Cancelamento</div><span class="text-secondary"><?= e($venda['motivo_cancelamento'] ?? '—') ?></span></div>
                <?php endif; ?>
            </div>

            <?php if ($venda['observacao']): ?>
                <p class="small text-secondary mb-3"><?= e($venda['observacao']) ?></p>
            <?php endif; ?>

            <?php if ($itens): ?>
                <h3 class="h6 fw-semibold">Itens</h3>
                <div class="table-responsive mb-3">
                    <table class="table align-middle">
                        <thead><tr><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Preço</th><th class="text-end">Total</th></tr></thead>
                        <tbody><?php foreach ($itens as $item): ?><tr><td><?= e($item['nome_produto']) ?></td><td class="text-end"><?= (int) $item['quantidade'] ?></td><td class="text-end"><?= money_br((float) $item['preco_unitario']) ?></td><td class="text-end"><?= money_br((float) $item['total']) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 class="h6 fw-semibold">Pagamentos</h3>
            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead><tr><th>Forma</th><th class="text-end">Valor</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($pagamentos as $pag): ?><tr><td><?= e(pdv_payment_label($pag['metodo'])) ?></td><td class="text-end"><?= money_br((float) $pag['valor']) ?></td><td><?= e($pag['status']) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>

            <?php if ($parcelas): ?>
                <h3 class="h6 fw-semibold">Parcelas (a receber)</h3>
                <div class="table-responsive mb-3">
                    <table class="table align-middle">
                        <thead><tr><th>Parcela</th><th>Vencimento</th><th class="text-end">Valor</th><th>Status</th></tr></thead>
                        <tbody><?php foreach ($parcelas as $par): ?><tr><td><?= (int) $par['parcela'] ?>/<?= count($parcelas) ?></td><td><?= e($par['vencimento']) ?></td><td class="text-end"><?= money_br((float) $par['valor']) ?></td><td><?= e($par['status']) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($temPendenteOnline)): ?>
                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-hourglass-split"></i> Há pagamento(s) online aguardando confirmação.</span>
                    <a class="btn btn-warning btn-sm" href="<?= e(base_url('admin/vendas/confirmar.php?venda=' . (int) $venda['id'])) ?>">Confirmar agora</a>
                </div>
            <?php endif; ?>

            <?php if ($venda['status'] === 'finalizada' || $venda['status'] === 'pendente'): ?>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelar"><i class="bi bi-x-circle"></i> Cancelar venda</button>
            <?php endif; ?>

            <?php if ($venda['status'] === 'finalizada' || $venda['status'] === 'pendente'): ?>
                <a class="btn btn-outline-brand" target="_blank" href="<?= e(base_url('admin/vendas/cupom.php?venda=' . (int) $venda['id'])) ?>"><i class="bi bi-receipt-cutoff"></i> Imprimir cupom</a>
                <button class="btn btn-outline-brand" data-bs-toggle="modal" data-bs-target="#modalEmailCupom"><i class="bi bi-envelope"></i> Enviar por e-mail</button>
            <?php endif; ?>
            <a class="btn btn-outline-brand" href="<?= e(base_url('admin/vendas/historico.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        <?php if ($venda['status'] === 'finalizada' || $venda['status'] === 'pendente'): ?>
        <div class="modal fade" id="modalEmailCupom" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content">
                    <?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Enviar cupom <?= e($venda['numero']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                    <div class="modal-body">
                        <p class="text-secondary">O cupom será enviado em formato HTML, pronto para impressão.</p>
                        <input type="hidden" name="acao" value="enviar_cupom">
                        <input type="hidden" name="venda_id" value="<?= (int) $venda['id'] ?>">
                        <label class="form-label">E-mail do cliente</label>
                        <input class="form-control" name="email_envio" type="email" required value="<?= e((string) ($venda['email_recibo'] ?? $venda['cliente_email'] ?? '')) ?>" placeholder="cliente@exemplo.com">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button class="btn btn-brand" type="submit"><i class="bi bi-send"></i> Enviar cupom</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($venda['status'] === 'finalizada' || $venda['status'] === 'pendente'): ?>
        <div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content">
                    <?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Cancelar venda <?= e($venda['numero']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                    <div class="modal-body">
                        <p class="text-secondary">O estoque será restituído e os pagamentos do caixa serão revertidos. Parcelas pendentes serão canceladas.</p>
                        <input type="hidden" name="acao" value="cancelar">
                        <input type="hidden" name="venda_id" value="<?= (int) $venda['id'] ?>">
                        <label class="form-label">Motivo do cancelamento</label>
                        <textarea class="form-control" name="motivo_cancelamento" rows="3" required placeholder="Descreva o motivo"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button class="btn btn-danger" type="submit"><i class="bi bi-x-circle"></i> Confirmar cancelamento</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="panel-card bg-white p-4">
        <form class="search-control mb-3" method="get">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-funnel"></i></span>
                <select class="form-select" name="status">
                    <option value="">Todos os status</option>
                    <option value="finalizada" <?= $statusFiltro === 'finalizada' ? 'selected' : '' ?>>Finalizadas</option>
                    <option value="pendente" <?= $statusFiltro === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="cancelada" <?= $statusFiltro === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
                </select>
                <button class="btn btn-brand">Filtrar</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Venda</th><th>Data</th><th>Vendedor</th><th>Cliente</th><th class="text-end">Total</th><th>Status</th><th>Cupom</th></tr></thead>
                <tbody>
                <?php foreach ($vendas as $v): ?>
                    <tr>
                        <td><a href="<?= e(base_url('admin/vendas/historico.php?venda=' . (int) $v['id'])) ?>"><?= e($v['numero']) ?></a></td>
                        <td><?= e($v['finalizada_em'] ?? $v['criado_em']) ?></td>
                        <td><?= e($v['vendedor']) ?></td>
                        <td><?= e($v['cliente'] ?? '—') ?></td>
                        <td class="text-end"><?= money_br((float) $v['total']) ?></td>
                        <td>
                            <?php if ($v['status'] === 'finalizada'): ?>
                                <span class="badge text-bg-success">Finalizada</span>
                            <?php elseif ($v['status'] === 'pendente'): ?>
                                <span class="badge text-bg-warning">Aguarda pagamento</span>
                            <?php elseif ($v['status'] === 'cancelada'): ?>
                                <span class="badge text-bg-secondary">Cancelada</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Aberta</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($v['status'] === 'finalizada' || $v['status'] === 'pendente'): ?>
                                <a class="btn btn-sm btn-outline-brand" target="_blank" href="<?= e(base_url('admin/vendas/cupom.php?venda=' . (int) $v['id'])) ?>" title="Imprimir cupom"><i class="bi bi-receipt-cutoff"></i></a>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$vendas): ?><tr><td colspan="7" class="text-center text-secondary py-4">Nenhuma venda localizada.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($vendasPage) ?>
    </div>
</div>
</div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>