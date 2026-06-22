<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? 0);
$pedido = db_one(
    "SELECT p.*, u.nome AS cliente, u.email, cp.documento, cp.tipo_pessoa, cp.inscricao_estadual, cp.telefone,
            e.cep, e.logradouro, e.numero, e.complemento, e.bairro, e.cidade, e.uf
     FROM pedidos p
     JOIN usuarios u ON u.id = p.usuario_id
     LEFT JOIN clientes_perfis cp ON cp.usuario_id = u.id
     LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
     WHERE p.id = :id",
    ['id' => $id]
);

if (!$pedido) {
    flash('danger', 'Pedido nao encontrado.');
    redirect('admin/pedidos/index.php');
}

$itens = db_all(
    "SELECT pi.*, p.sku, p.ncm, p.cfop, p.unidade
     FROM pedido_itens pi
     LEFT JOIN produtos p ON p.id = pi.produto_id
     WHERE pi.pedido_id = :id",
    ['id' => $id]
);
$eventos = db_all("SELECT * FROM faturamento_eventos WHERE pedido_id = :id ORDER BY criado_em DESC", ['id' => $id]);
$validacao = nfe_validate_order($id);
$statusOptions = [
    'novo' => 'Novo',
    'aguardando_pagamento' => 'Aguardando pagamento',
    'pago' => 'Pago',
    'separacao' => 'Em separacao',
    'enviado' => 'Enviado',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado',
];

$pageTitle = 'Detalhe do Pedido';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div><h1 class="h3 section-title mb-1">Pedido #<?= (int) $pedido['id'] ?></h1><p class="text-secondary mb-0">Revise dados comerciais, fiscais e pagamento antes de confirmar.</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="post" action="<?= e(base_url('admin/pedidos/acao.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $pedido['id'] ?>"><input type="hidden" name="acao" value="validar_fiscal"><button class="btn btn-outline-brand"><i class="bi bi-shield-check"></i> Validar fiscal</button></form>
            <form method="post" action="<?= e(base_url('admin/pedidos/acao.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $pedido['id'] ?>"><input type="hidden" name="acao" value="confirmar"><button class="btn btn-brand" data-confirm="Confirmar pedido?"><i class="bi bi-check2-circle"></i> Confirmar pedido</button></form>
        </div>
    </div>

    <div class="alert <?= $validacao['ok'] ? 'alert-success' : 'alert-warning' ?>">
        <strong>Fiscal <?= fiscal_enabled() ? 'habilitado' : 'desabilitado' ?>:</strong> <?= e($validacao['message']) ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card bg-white p-4 mb-4">
                <h2 class="h5">Itens do pedido</h2>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produto</th><th>NCM</th><th>CFOP</th><th>RTC</th><th>Qtd</th><th>Total</th></tr></thead><tbody><?php foreach ($itens as $item): ?><tr><td><?= e($item['nome_produto']) ?><br><small><?= e($item['sku']) ?></small></td><td><?= e($item['ncm']) ?></td><td><?= e($item['cfop']) ?></td><td class="small">CST <?= e($item['cst_ibs_cbs'] ?? '') ?><br>Class <?= e($item['cclass_trib_ibs_cbs'] ?? '') ?><br>IBS <?= e((string) ($item['aliquota_ibs_uf'] ?? '0')) ?>% / <?= e((string) ($item['aliquota_ibs_municipal'] ?? '0')) ?>% · CBS <?= e((string) ($item['aliquota_cbs'] ?? '0')) ?>%</td><td><?= (int) $item['quantidade'] ?></td><td><?= money_br((float) $item['total']) ?></td></tr><?php endforeach; ?></tbody></table></div>
            </div>
            <div class="panel-card bg-white p-4">
                <h2 class="h5">Eventos de faturamento</h2>
                <?php foreach ($eventos as $evento): ?><div class="border-bottom py-2"><strong><?= e($evento['tipo']) ?> - <?= e($evento['status']) ?></strong><br><small class="text-secondary"><?= e($evento['mensagem']) ?> · <?= e($evento['criado_em']) ?></small></div><?php endforeach; ?>
                <?php if (!$eventos): ?><p class="text-secondary mb-0">Nenhum evento registrado.</p><?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel-card bg-white p-4 mb-4">
                <h2 class="h5">Cliente e entrega</h2>
                <p class="mb-1"><strong><?= e($pedido['cliente']) ?></strong></p>
                <p class="text-secondary small mb-2"><?= e($pedido['email']) ?> · <?= e($pedido['telefone']) ?></p>
                <p class="mb-1">Documento: <?= e($pedido['documento']) ?> (<?= e($pedido['tipo_pessoa']) ?>)</p>
                <p class="mb-0 text-secondary"><?= e($pedido['logradouro'] . ', ' . $pedido['numero'] . ' - ' . $pedido['bairro'] . ' - ' . $pedido['cidade'] . '/' . $pedido['uf']) ?></p>
            </div>
            <div class="panel-card bg-white p-4">
                <h2 class="h5">Resumo</h2>
                <div class="d-flex justify-content-between border-bottom py-2"><span>Status</span><strong><?= e($pedido['status']) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>Fiscal</span><strong><?= e($pedido['fiscal_status']) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>Pagamento</span><strong><?= e($pedido['pagamento_status']) ?></strong></div>
                <div class="d-flex justify-content-between pt-2 fs-5"><span>Total</span><strong><?= money_br((float) $pedido['total']) ?></strong></div>
                <form method="post" action="<?= e(base_url('admin/pedidos/acao.php')) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $pedido['id'] ?>">
                    <input type="hidden" name="acao" value="alterar_status">
                    <label class="form-label fw-semibold">Alterar status para acompanhamento do cliente</label>
                    <div class="input-group">
                        <select class="form-select" name="status" required>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $pedido['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-brand" type="submit">Salvar</button>
                    </div>
                    <div class="form-text">Essa alteracao aparece no historico de compras do cliente.</div>
                </form>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
