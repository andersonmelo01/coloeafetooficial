<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? 0);
$nota = db_one(
    "SELECT nf.*, p.total, p.forma_pagamento, p.status AS pedido_status, p.fiscal_status, u.nome AS cliente, u.email
     FROM notas_fiscais nf
     JOIN pedidos p ON p.id = nf.pedido_id
     JOIN usuarios u ON u.id = p.usuario_id
     WHERE nf.id = :id",
    ['id' => $id]
);

if (!$nota) {
    flash('danger', 'NF-e nao encontrada.');
    redirect('admin/faturamento/index.php');
}

$eventos = db_all(
    "SELECT *
     FROM faturamento_eventos
     WHERE pedido_id = :pedido_id
     ORDER BY criado_em DESC
     LIMIT 20",
    ['pedido_id' => (int) $nota['pedido_id']]
);

$xmlPath = nfe_xml_absolute_path($nota['xml_path'] ?? $nota['xml_url'] ?? null);
$pageTitle = 'Gerenciar NF-e';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div><h1 class="h3 section-title mb-1">NF-e do pedido #<?= (int) $nota['pedido_id'] ?></h1><p class="text-secondary mb-0"><?= e($nota['cliente']) ?> - <?= money_br((float) $nota['total']) ?></p></div>
            <div class="text-md-end"><span class="badge text-bg-secondary"><?= e($nota['status']) ?></span><br><a class="btn btn-sm btn-outline-secondary mt-2" href="<?= e(base_url('admin/pedidos/detalhe.php?id=' . (int) $nota['pedido_id'])) ?>"><i class="bi bi-box-seam"></i> Ver pedido</a></div>
        </div>
        <div class="alert alert-info mt-4 mb-0">Use esta tela para salvar XML autorizado, abrir XML, cancelar NF-e e registrar carta de correcao. Para validade fiscal em producao, os eventos devem ser transmitidos e autorizados pela SEFAZ usando SPED-NFe, certificado A1 e as regras do regime tributario configurado para RJ.</div>
    </div>

    <div class="panel-card bg-white p-4 mb-4">
        <h2 class="h5 mb-3">XML e dados da NF-e</h2>
        <form method="post" action="<?= e(base_url('admin/faturamento/xml.php')) ?>" enctype="multipart/form-data" class="row g-3"><?= csrf_field() ?><input type="hidden" name="acao" value="salvar_xml"><input type="hidden" name="nota_id" value="<?= (int) $nota['id'] ?>">
            <div class="col-md-3"><label class="form-label">Numero</label><input class="form-control" name="numero" value="<?= e($nota['numero']) ?>"></div><div class="col-md-2"><label class="form-label">Serie</label><input class="form-control" name="serie" value="<?= e($nota['serie']) ?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['pendente', 'transmitida', 'autorizada', 'rejeitada', 'corrigida', 'cancelada'] as $status): ?><option value="<?= e($status) ?>" <?= $nota['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Protocolo</label><input class="form-control" name="protocolo" value="<?= e($nota['protocolo']) ?>"></div>
            <div class="col-12"><label class="form-label">Chave de acesso</label><input class="form-control" name="chave_acesso" value="<?= e($nota['chave_acesso']) ?>"></div>
            <div class="col-md-8"><label class="form-label">Salvar XML autorizado</label><input class="form-control" type="file" name="xml_file" accept=".xml,application/xml,text/xml"></div><div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-brand"><i class="bi bi-save"></i> Salvar</button><?php if ($xmlPath): ?><a class="btn btn-outline-secondary" target="_blank" href="<?= e(base_url('admin/faturamento/xml.php?acao=abrir&nota_id=' . (int) $nota['id'])) ?>"><i class="bi bi-folder2-open"></i> Abrir XML</a><?php endif; ?></div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-6"><div class="panel-card bg-white p-4 h-100"><h2 class="h5 mb-3">Cancelar NF-e</h2><form method="post" action="<?= e(base_url('admin/faturamento/xml.php')) ?>"><?= csrf_field() ?><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="nota_id" value="<?= (int) $nota['id'] ?>"><label class="form-label">Motivo do cancelamento</label><textarea class="form-control mb-3" name="motivo_cancelamento" rows="4" placeholder="Motivo com no minimo 15 caracteres"><?= e($nota['motivo_cancelamento']) ?></textarea><button class="btn btn-outline-danger" data-confirm="Registrar cancelamento desta NF-e?"><i class="bi bi-x-octagon"></i> Cancelar NF-e</button></form></div></div>
        <div class="col-lg-6"><div class="panel-card bg-white p-4 h-100"><h2 class="h5 mb-3">Carta de correcao</h2><form method="post" action="<?= e(base_url('admin/faturamento/xml.php')) ?>"><?= csrf_field() ?><input type="hidden" name="acao" value="corrigir"><input type="hidden" name="nota_id" value="<?= (int) $nota['id'] ?>"><label class="form-label">Correcao</label><textarea class="form-control mb-3" name="carta_correcao" rows="4" placeholder="Descreva a correcao permitida pela CC-e"><?= e($nota['carta_correcao']) ?></textarea><button class="btn btn-outline-brand" data-confirm="Registrar carta de correcao?"><i class="bi bi-pencil-square"></i> Corrigir NF-e</button></form></div></div>
    </div>

    <div class="panel-card bg-white p-4 mt-4">
        <h2 class="h5 mb-3">Auditoria fiscal</h2>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Data</th><th>Tipo</th><th>Status</th><th>Mensagem</th></tr></thead><tbody><?php foreach ($eventos as $evento): ?><tr><td><?= e($evento['criado_em']) ?></td><td><?= e($evento['tipo']) ?></td><td><?= e($evento['status']) ?></td><td><?= e($evento['mensagem']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php if (!$eventos): ?><p class="text-secondary mb-0">Nenhum evento fiscal registrado.</p><?php endif; ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
