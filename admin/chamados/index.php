<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');
$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && !array_key_exists($status, chamado_status_options())) {
    $status = '';
}
$chamadosPage = paginate_query(
    "SELECT ch.*, u.nome AS cliente
     FROM chamados ch
     LEFT JOIN usuarios u ON u.id = ch.usuario_id
     WHERE (:q = '' OR ch.protocolo LIKE :like_q OR ch.assunto LIKE :like_q OR u.nome LIKE :like_q OR ch.status LIKE :like_q)
       AND (:status = '' OR ch.status = :status)
     ORDER BY ch.atualizado_em DESC, ch.criado_em DESC",
    ['q' => $q, 'like_q' => '%' . $q . '%', 'status' => $status]
);
$chamados = $chamadosPage['rows'];
$pageTitle = 'Chamados';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><h1 class="h3 section-title">Chamados</h1><form class="search-control row g-2 mb-4" method="get"><div class="col-md-7"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Protocolo, cliente ou assunto"></div></div><div class="col-md-3"><select class="form-select" name="status"><option value="">Todos os status</option><?php foreach (chamado_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-brand w-100">Buscar</button></div></form><?php foreach ($chamados as $ch): ?><div class="border-bottom py-3"><div class="d-flex justify-content-between gap-3"><strong><?= e($ch['assunto']) ?></strong><span class="badge text-bg-light"><?= e(chamado_status_label($ch['status'])) ?></span></div><div class="small text-secondary">Protocolo <?= e($ch['protocolo'] ?: chamado_generate_protocol((int) $ch['id'])) ?> · <?= e($ch['cliente']) ?> · <?= e($ch['tipo']) ?> · <?= e($ch['criado_em']) ?></div><p class="mb-2 mt-2"><?= e($ch['mensagem']) ?></p><a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/chamados/detalhe.php?id=' . (int) $ch['id'])) ?>"><i class="bi bi-chat-dots"></i> Atender</a></div><?php endforeach; ?><?php if (!$chamados): ?><p class="text-secondary mb-0">Nenhum chamado localizado.</p><?php endif; ?><?= pagination_links($chamadosPage) ?></div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
