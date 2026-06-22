<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login('cliente');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO chamados (usuario_id, tipo, assunto, mensagem, status) VALUES (:usuario_id, :tipo, :assunto, :mensagem, 'aberto')");
        $stmt->execute([
            'usuario_id' => current_user()['id'],
            'tipo' => $_POST['tipo'],
            'assunto' => $_POST['assunto'],
            'mensagem' => $_POST['mensagem'],
        ]);
        $chamadoId = (int) $pdo->lastInsertId();
        $protocolo = chamado_generate_protocol($chamadoId);
        $pdo->prepare("UPDATE chamados SET protocolo = :protocolo WHERE id = :id")->execute(['id' => $chamadoId, 'protocolo' => $protocolo]);
        $pdo->prepare("INSERT INTO chamados_mensagens (chamado_id, usuario_id, mensagem) VALUES (:chamado_id, :usuario_id, :mensagem)")->execute([
            'chamado_id' => $chamadoId,
            'usuario_id' => current_user()['id'],
            'mensagem' => $_POST['mensagem'],
        ]);
        $pdo->commit();
        flash('success', 'Chamado aberto com sucesso. Protocolo: ' . $protocolo . '.');
        redirect('cliente/chamados/detalhe.php?id=' . $chamadoId);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Nao foi possivel abrir o chamado.');
    }
    redirect('cliente/chamados.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && !array_key_exists($status, chamado_status_options())) {
    $status = '';
}
$chamadosPage = paginate_query(
    "SELECT *
     FROM chamados
     WHERE usuario_id = :id
       AND (:q = '' OR protocolo LIKE :like_q OR assunto LIKE :like_q OR status LIKE :like_q)
       AND (:status = '' OR status = :status)
     ORDER BY criado_em DESC",
    ['id' => current_user()['id'], 'q' => $q, 'like_q' => '%' . $q . '%', 'status' => $status]
);
$chamados = $chamadosPage['rows'];
$pageTitle = 'Chamados';
$active = 'cliente';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="row g-4">
        <div class="col-lg-5"><div class="panel-card bg-white p-4"><h1 class="h4 section-title">Abrir chamado</h1><form method="post" class="row g-3"><?= csrf_field() ?><div class="col-12"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><option value="reclamacao">Reclamacao</option><option value="elogio">Elogio</option><option value="duvida">Duvida</option></select></div><div class="col-12"><label class="form-label">Assunto</label><input class="form-control" name="assunto" required></div><div class="col-12"><label class="form-label">Mensagem</label><textarea class="form-control" name="mensagem" rows="5" required></textarea></div><div class="col-12"><button class="btn btn-brand">Enviar</button></div></form></div></div>
        <div class="col-lg-7"><div class="panel-card bg-white p-4"><div class="d-flex flex-column gap-3 mb-4"><h2 class="h4 section-title mb-0">Meus chamados</h2><form class="search-control row g-2" method="get"><div class="col-md-7"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Protocolo ou assunto"></div></div><div class="col-md-3"><select class="form-select" name="status"><option value="">Todos os status</option><?php foreach (chamado_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-brand w-100">Buscar</button></div></form></div><?php foreach ($chamados as $chamado): ?><div class="border-bottom py-3"><div class="d-flex justify-content-between gap-3"><strong><?= e($chamado['assunto']) ?></strong><span class="badge text-bg-light"><?= e(chamado_status_label($chamado['status'])) ?></span></div><div class="small text-secondary">Protocolo <?= e($chamado['protocolo'] ?: chamado_generate_protocol((int) $chamado['id'])) ?> · <?= e($chamado['tipo']) ?> · <?= e($chamado['criado_em']) ?></div><a class="btn btn-sm btn-outline-brand mt-2" href="<?= e(base_url('cliente/chamados/detalhe.php?id=' . (int) $chamado['id'])) ?>"><i class="bi bi-chat-dots"></i> Ver conversa</a></div><?php endforeach; ?><?php if (!$chamados): ?><p class="text-secondary mb-0">Nenhum chamado encontrado.</p><?php endif; ?><?= pagination_links($chamadosPage) ?></div></div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
