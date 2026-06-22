<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$pedidosPage = paginate_query(
    "SELECT p.*, u.nome AS cliente, nf.id AS nota_id, nf.numero, nf.status AS nf_status, nf.xml_path
     FROM pedidos p
     JOIN usuarios u ON u.id = p.usuario_id
     LEFT JOIN notas_fiscais nf ON nf.pedido_id = p.id
     WHERE (:q = '' OR p.id = :id_busca OR u.nome LIKE :like_q OR p.fiscal_status LIKE :like_q)
     ORDER BY p.criado_em DESC",
    ['q' => $q, 'id_busca' => (int) $q, 'like_q' => '%' . $q . '%']
);
$pedidos = $pedidosPage['rows'];
$checklist = [
    'SPED-NFe instalado' => nfe_sped_available(),
    'Fiscal habilitado' => fiscal_enabled(),
    'CNPJ emitente' => trim((string) app_config('fiscal.cnpj', '')) !== '',
    'Inscricao estadual' => trim((string) app_config('fiscal.inscricao_estadual', '')) !== '',
    'Certificado A1/PFX' => trim((string) app_config('fiscal.certificado_pfx', '')) !== '',
    'Senha certificado' => trim((string) app_config('fiscal.certificado_senha', '')) !== '',
    'Serie e numeracao' => trim((string) app_config('fiscal.serie_nfe', '')) !== '' && trim((string) app_config('fiscal.proximo_numero_nfe', '')) !== '',
];

$pageTitle = 'Faturamento';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4 mb-4">
        <h1 class="h3 section-title">Faturamento / NF-e</h1>
        <div class="row g-3">
            <div class="col-md-4"><div class="p-3 bg-light rounded-2"><strong>Fiscal</strong><br><?= fiscal_enabled() ? 'Habilitado' : 'Desabilitado' ?></div></div>
            <div class="col-md-4"><div class="p-3 bg-light rounded-2"><strong>Ambiente</strong><br><?= e(app_config('fiscal.ambiente', 'homologacao')) ?></div></div>
            <div class="col-md-4"><div class="p-3 bg-light rounded-2"><strong>SPED-NFe</strong><br><?= nfe_sped_available() ? 'Instalado' : 'Pendente composer install' ?></div></div>
        </div>
        <div class="row g-2 mt-3">
            <?php foreach ($checklist as $label => $ok): ?>
                <div class="col-md-6"><span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $ok ? 'OK' : 'Pendente' ?></span> <?= e($label) ?></div>
            <?php endforeach; ?>
        </div>
        <p class="text-secondary small mt-3 mb-0">Este modulo esta preparado para montar/assinar/enviar NF-e pela biblioteca SPED-NFe. A chamada real deve ser completada apos configurar certificado A1, emitente, CSC/ambiente quando aplicavel e regras fiscais da empresa.</p>
    </div>
    <div class="panel-card bg-white p-4">
        <form class="search-control mb-4" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pedido, cliente ou status fiscal"><button class="btn btn-brand">Buscar</button></div></form>
        <form method="post" action="<?= e(base_url('admin/faturamento/lote.php')) ?>"><?= csrf_field() ?>
            <div class="d-flex justify-content-end mb-3"><button class="btn btn-brand"><i class="bi bi-stack"></i> Emitir selecionadas</button></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th style="width:40px"></th><th>Pedido</th><th>Cliente</th><th>Fiscal</th><th>NF-e</th><th>Total</th><th></th></tr></thead><tbody>
                <?php foreach ($pedidos as $pedido): ?><tr><td><input class="form-check-input" type="checkbox" name="pedido_ids[]" value="<?= (int) $pedido['id'] ?>"></td><td>#<?= (int) $pedido['id'] ?></td><td><?= e($pedido['cliente']) ?></td><td><?= e($pedido['fiscal_status']) ?></td><td><?= e($pedido['numero'] ?: ($pedido['nf_status'] ?: 'Sem emissao')) ?><?php if (!empty($pedido['xml_path'])): ?><div class="small text-success"><i class="bi bi-filetype-xml"></i> XML salvo</div><?php endif; ?></td><td><?= money_br((float) $pedido['total']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/faturamento/emitir.php?id=' . (int) $pedido['id'])) ?>"><i class="bi bi-receipt"></i> Emitir/preparar</a> <?php if (!empty($pedido['nota_id'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/faturamento/nota.php?id=' . (int) $pedido['nota_id'])) ?>"><i class="bi bi-folder2-open"></i> Gerenciar</a><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if (!$pedidos): ?><p class="text-secondary mb-0">Nenhum pedido localizado.</p><?php endif; ?>
        </form>
        <?= pagination_links($pedidosPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
