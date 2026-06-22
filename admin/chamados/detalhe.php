<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$chamado = db_one(
    "SELECT ch.*, u.nome AS cliente, u.email
     FROM chamados ch
     JOIN usuarios u ON u.id = ch.usuario_id
     WHERE ch.id = :id",
    ['id' => $id]
);

if (!$chamado) {
    flash('danger', 'Chamado nao encontrado.');
    redirect('admin/chamados/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (!array_key_exists($status, chamado_status_options())) {
            flash('warning', 'Status invalido.');
            redirect('admin/chamados/detalhe.php?id=' . $id);
        }

        db()->prepare("UPDATE chamados SET status = :status, atualizado_em = NOW() WHERE id = :id")->execute([
            'id' => $id,
            'status' => $status,
        ]);
        flash('success', 'Status do chamado atualizado.');
        redirect('admin/chamados/detalhe.php?id=' . $id);
    }

    if ($acao === 'responder') {
        if ($chamado['status'] === 'finalizado') {
            flash('warning', 'Chamado finalizado. Reabra o chamado alterando o status antes de responder.');
            redirect('admin/chamados/detalhe.php?id=' . $id);
        }

        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($mensagem === '') {
            flash('warning', 'Digite uma mensagem para responder.');
            redirect('admin/chamados/detalhe.php?id=' . $id);
        }

        db()->prepare("INSERT INTO chamados_mensagens (chamado_id, usuario_id, mensagem) VALUES (:chamado_id, :usuario_id, :mensagem)")->execute([
            'chamado_id' => $id,
            'usuario_id' => current_user()['id'],
            'mensagem' => $mensagem,
        ]);
        db()->prepare(
            "UPDATE chamados
             SET status = CASE WHEN status = 'aberto' THEN 'em_analise' ELSE status END,
                 atualizado_em = NOW()
             WHERE id = :id"
        )->execute(['id' => $id]);
        flash('success', 'Mensagem enviada ao cliente.');
        redirect('admin/chamados/detalhe.php?id=' . $id);
    }
}

$mensagens = db_all(
    "SELECT cm.*, u.nome, u.tipo
     FROM chamados_mensagens cm
     JOIN usuarios u ON u.id = cm.usuario_id
     WHERE cm.chamado_id = :id
     ORDER BY cm.criado_em ASC",
    ['id' => $id]
);

$pageTitle = 'Atendimento do Chamado';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div><h1 class="h3 section-title mb-1"><?= e($chamado['assunto']) ?></h1><p class="text-secondary mb-0">Protocolo <?= e($chamado['protocolo'] ?: chamado_generate_protocol((int) $chamado['id'])) ?> · <?= e($chamado['cliente']) ?> · <?= e($chamado['email']) ?></p></div>
            <div class="d-flex gap-2 align-items-start"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/chamados/index.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar</a><span class="badge text-bg-light"><?= e(chamado_status_label($chamado['status'])) ?></span></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">Conversa</h2>
                <?php foreach ($mensagens as $msg): ?>
                    <div class="border-bottom py-3">
                        <div class="d-flex justify-content-between gap-3"><strong><?= e($msg['nome']) ?></strong><span class="small text-secondary"><?= e($msg['criado_em']) ?></span></div>
                        <div class="small text-secondary mb-2"><?= $msg['tipo'] === 'admin' ? 'Atendimento' : 'Cliente' ?></div>
                        <p class="mb-0"><?= nl2br(e($msg['mensagem'])) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$mensagens): ?><p class="text-secondary mb-0"><?= nl2br(e($chamado['mensagem'])) ?></p><?php endif; ?>
            </div>
            <div class="panel-card bg-white p-4">
                <?php if ($chamado['status'] === 'finalizado'): ?>
                    <p class="text-secondary mb-0">Chamado finalizado. Para enviar nova mensagem, altere o status primeiro.</p>
                <?php else: ?>
                    <h2 class="h5 mb-3">Responder cliente</h2>
                    <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="acao" value="responder"><input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>"><div class="col-12"><label class="form-label">Mensagem</label><textarea class="form-control" name="mensagem" rows="4" required></textarea></div><div class="col-12"><button class="btn btn-brand"><i class="bi bi-send"></i> Enviar resposta</button></div></form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel-card bg-white p-4">
                <h2 class="h5 mb-3">Status do chamado</h2>
                <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="acao" value="status"><input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>"><div class="col-12"><label class="form-label">Alterar para</label><select class="form-select" name="status"><?php foreach (chamado_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $chamado['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-12"><button class="btn btn-outline-brand"><i class="bi bi-check2-circle"></i> Atualizar status</button></div></form>
                <hr>
                <p class="small text-secondary mb-0">Quando o status estiver finalizado, o cliente nao podera enviar novas mensagens, apenas consultar o historico.</p>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
