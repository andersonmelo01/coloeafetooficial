<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('cliente');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$chamado = db_one(
    "SELECT ch.*
     FROM chamados ch
     WHERE ch.id = :id AND ch.usuario_id = :usuario_id",
    ['id' => $id, 'usuario_id' => current_user()['id']]
);

if (!$chamado) {
    flash('danger', 'Chamado nao encontrado.');
    redirect('cliente/chamados.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    if ($chamado['status'] === 'finalizado') {
        flash('warning', 'Chamado finalizado. Voce pode ver o historico, mas nao pode enviar novas mensagens.');
        redirect('cliente/chamados/detalhe.php?id=' . $id);
    }

    $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
    if ($mensagem === '') {
        flash('warning', 'Digite uma mensagem para responder ao chamado.');
        redirect('cliente/chamados/detalhe.php?id=' . $id);
    }

    db()->prepare("INSERT INTO chamados_mensagens (chamado_id, usuario_id, mensagem) VALUES (:chamado_id, :usuario_id, :mensagem)")->execute([
        'chamado_id' => $id,
        'usuario_id' => current_user()['id'],
        'mensagem' => $mensagem,
    ]);
    db()->prepare("UPDATE chamados SET atualizado_em = NOW() WHERE id = :id")->execute(['id' => $id]);
    flash('success', 'Mensagem enviada.');
    redirect('cliente/chamados/detalhe.php?id=' . $id);
}

$mensagens = db_all(
    "SELECT cm.*, u.nome, u.tipo
     FROM chamados_mensagens cm
     JOIN usuarios u ON u.id = cm.usuario_id
     WHERE cm.chamado_id = :id
     ORDER BY cm.criado_em ASC",
    ['id' => $id]
);

$pageTitle = 'Detalhe do Chamado';
$active = 'cliente';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div><h1 class="h3 section-title mb-1"><?= e($chamado['assunto']) ?></h1><p class="text-secondary mb-0">Protocolo <?= e($chamado['protocolo'] ?: chamado_generate_protocol((int) $chamado['id'])) ?> · <?= e($chamado['tipo']) ?> · <?= e($chamado['criado_em']) ?></p></div>
            <div class="d-flex gap-2 align-items-start"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('cliente/chamados.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar</a><span class="badge text-bg-light"><?= e(chamado_status_label($chamado['status'])) ?></span></div>
        </div>
    </div>

    <div class="panel-card bg-white p-4 mb-4">
        <h2 class="h5 mb-3">Historico da conversa</h2>
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
            <p class="text-secondary mb-0">Este chamado foi finalizado. O historico permanece disponivel para consulta.</p>
        <?php else: ?>
            <h2 class="h5 mb-3">Responder chamado</h2>
            <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>"><div class="col-12"><label class="form-label">Mensagem</label><textarea class="form-control" name="mensagem" rows="4" required></textarea></div><div class="col-12"><button class="btn btn-brand"><i class="bi bi-send"></i> Enviar mensagem</button></div></form>
        <?php endif; ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
