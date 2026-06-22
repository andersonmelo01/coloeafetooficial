<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/EmailService.php';
require_login('entregador');

$entregadorId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? 'confirmar');
    $entregaId = (int) ($_POST['entrega_id'] ?? 0);
    $observacao = trim((string) ($_POST['observacao_entregador'] ?? ''));
    $entrega = db_one(
        "SELECT en.*
         FROM entregas en
         WHERE en.id = :id AND en.entregador_id = :entregador_id",
        ['id' => $entregaId, 'entregador_id' => $entregadorId]
    );

    if (!$entrega) {
        flash('danger', 'Entrega nao encontrada para seu usuario.');
        redirect('entregador/index.php');
    }

    if (($entrega['status'] ?? '') === 'entregue') {
        flash('warning', 'Entrega ja confirmada.');
        redirect('entregador/index.php');
    }

    if (($entrega['status'] ?? '') === 'cancelada') {
        flash('warning', 'Entrega cancelada pelo gestor. Consulte o historico.');
        redirect('entregador/index.php');
    }

    if ($acao === 'tentativa') {
        if ($observacao === '') {
            flash('warning', 'Informe a observacao da tentativa de entrega.');
            redirect('entregador/index.php');
        }

        db()->prepare(
            "INSERT INTO entrega_tentativas (entrega_id, pedido_id, entregador_id, observacao, status)
             VALUES (:entrega_id, :pedido_id, :entregador_id, :observacao, 'tentativa')"
        )->execute([
            'entrega_id' => $entregaId,
            'pedido_id' => (int) $entrega['pedido_id'],
            'entregador_id' => $entregadorId,
            'observacao' => $observacao,
        ]);

        db()->prepare(
            "UPDATE entregas
             SET status = 'tentativa', observacao_entregador = :observacao
             WHERE id = :id"
        )->execute([
            'id' => $entregaId,
            'observacao' => $observacao,
        ]);

        flash('success', 'Tentativa de entrega registrada.');
        redirect('entregador/index.php');
    }

    if ($acao !== 'confirmar') {
        flash('danger', 'Acao de entrega invalida.');
        redirect('entregador/index.php');
    }

    if ($observacao !== '') {
        db()->prepare(
            "INSERT INTO entrega_tentativas (entrega_id, pedido_id, entregador_id, observacao, status)
             VALUES (:entrega_id, :pedido_id, :entregador_id, :observacao, 'entregue')"
        )->execute([
            'entrega_id' => $entregaId,
            'pedido_id' => (int) $entrega['pedido_id'],
            'entregador_id' => $entregadorId,
            'observacao' => $observacao,
        ]);
    }

    db()->prepare(
        "UPDATE entregas
         SET status = 'entregue', observacao_entregador = :observacao,
             entregue_em = NOW(), confirmado_por_entregador_em = NOW()
         WHERE id = :id"
    )->execute([
        'id' => $entregaId,
        'observacao' => $observacao,
    ]);

    db()->prepare("UPDATE pedidos SET status = 'entregue', atualizado_em = NOW() WHERE id = :id")->execute([
        'id' => (int) $entrega['pedido_id'],
    ]);

    pedido_send_status_email((int) $entrega['pedido_id'], 'entregue', 'Entrega confirmada pelo entregador.');
    pedido_send_delivery_completed_email((int) $entrega['pedido_id'], $entregaId);
    flash('success', 'Entrega confirmada e cliente notificado.');
    redirect('entregador/index.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$inicio = trim((string) ($_GET['inicio'] ?? ''));
$fim = trim((string) ($_GET['fim'] ?? ''));
$statusOptions = [
    '' => 'Todos os status',
    'em_rota' => 'Em rota',
    'tentativa' => 'Tentativa',
    'entregue' => 'Entregue',
    'cancelada' => 'Cancelada',
];
if (!array_key_exists($status, $statusOptions)) {
    $status = '';
}
if ($inicio !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
    $inicio = '';
}
if ($fim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
    $fim = '';
}
$entregasPage = paginate_query(
    "SELECT en.*, p.total, p.status AS pedido_status, p.forma_pagamento, u.nome AS cliente, u.email,
            cp.telefone, e.cep, e.logradouro, e.numero, e.complemento, e.bairro, e.cidade, e.uf
     FROM entregas en
     JOIN pedidos p ON p.id = en.pedido_id
     JOIN usuarios u ON u.id = p.usuario_id
     LEFT JOIN clientes_perfis cp ON cp.usuario_id = u.id
     LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
     WHERE en.entregador_id = :entregador_id
       AND (:q = '' OR p.id = :id_busca OR u.nome LIKE :like_q OR en.status LIKE :like_q OR e.cidade LIKE :like_q)
       AND (:status = '' OR en.status = :status)
       AND (:inicio = '' OR DATE(COALESCE(en.enviado_em, p.criado_em)) >= :inicio)
       AND (:fim = '' OR DATE(COALESCE(en.enviado_em, p.criado_em)) <= :fim)
     ORDER BY CASE WHEN en.status = 'entregue' THEN 1 WHEN en.status = 'cancelada' THEN 2 ELSE 0 END, en.enviado_em DESC",
    ['entregador_id' => $entregadorId, 'q' => $q, 'id_busca' => (int) $q, 'like_q' => '%' . $q . '%', 'status' => $status, 'inicio' => $inicio, 'fim' => $fim]
);
$entregas = $entregasPage['rows'];

$tentativasPorEntrega = [];
$entregaIds = [];
foreach ($entregas as $entrega) {
    $entregaIds[] = (int) $entrega['id'];
}

if ($entregaIds) {
    $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));
    $stmt = db()->prepare(
        "SELECT et.*
         FROM entrega_tentativas et
         WHERE et.entrega_id IN ($placeholders)
         ORDER BY et.criado_em DESC"
    );
    $stmt->execute($entregaIds);
    foreach ($stmt->fetchAll() as $tentativa) {
        $tentativasPorEntrega[(int) $tentativa['entrega_id']][] = $tentativa;
    }
}

$pageTitle = 'Minhas Entregas';
$active = 'entregador';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 section-title mb-1">Minhas entregas</h1>
                <p class="text-secondary mb-0">Registre tentativas, acompanhe historico e confirme entregas realizadas.</p>
            </div>
            <form class="search-control row g-2" method="get">
                <div class="col-md-4"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pedido, cliente ou cidade"></div></div>
                <div class="col-md-2"><select class="form-select" name="status"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><input class="form-control" type="date" name="inicio" value="<?= e($inicio) ?>" title="Data inicial"></div>
                <div class="col-md-2"><input class="form-control" type="date" name="fim" value="<?= e($fim) ?>" title="Data final"></div>
                <div class="col-md-2"><button class="btn btn-brand w-100">Buscar</button></div>
            </form>
        </div>
        <?php foreach ($entregas as $entrega): ?>
            <?php $tentativas = $tentativasPorEntrega[(int) $entrega['id']] ?? []; ?>
            <div class="border-bottom py-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                    <div>
                        <h2 class="h5 mb-1">Pedido #<?= (int) $entrega['pedido_id'] ?> - <?= e($entrega['cliente']) ?></h2>
                        <div class="small text-secondary"><?= e($entrega['telefone']) ?> · <?= e($entrega['email']) ?></div>
                        <p class="mb-1 mt-2"><?= e($entrega['logradouro'] . ', ' . $entrega['numero'] . ($entrega['complemento'] ? ' - ' . $entrega['complemento'] : '')) ?></p>
                        <p class="mb-0 text-secondary"><?= e($entrega['bairro'] . ' - ' . $entrega['cidade'] . '/' . $entrega['uf'] . ' - CEP ' . $entrega['cep']) ?></p>
                    </div>
                    <div class="text-md-end">
                        <span class="badge <?= $entrega['status'] === 'cancelada' ? 'text-bg-danger' : ($entrega['status'] === 'entregue' ? 'text-bg-success' : 'text-bg-light') ?>"><?= e($entrega['status']) ?></span><br>
                        <strong><?= money_br((float) $entrega['total']) ?></strong>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-3"><strong>Rastreio</strong><br><span class="text-secondary"><?= e($entrega['codigo_rastreio'] ?: 'Nao informado') ?></span></div>
                    <div class="col-md-3"><strong>Previsao</strong><br><span class="text-secondary"><?= e($entrega['previsao_entrega'] ?: 'Nao informada') ?></span></div>
                    <div class="col-md-3"><strong>Enviado em</strong><br><span class="text-secondary"><?= e($entrega['enviado_em']) ?></span></div>
                    <div class="col-md-3"><strong>Entregue em</strong><br><span class="text-secondary"><?= e($entrega['entregue_em'] ?: '-') ?></span></div>
                </div>

                <?php if ($entrega['status'] === 'cancelada'): ?>
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Entrega cancelada pelo gestor.</strong><br>
                        <?= e($entrega['motivo_cancelamento'] ?: 'Motivo nao informado.') ?>
                    </div>
                <?php elseif ($entrega['status'] !== 'entregue'): ?>
                    <form method="post" class="row g-2 mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entrega_id" value="<?= (int) $entrega['id'] ?>">
                        <div class="col-12">
                            <label class="form-label">Observacao da entrega ou tentativa</label>
                            <textarea class="form-control" name="observacao_entregador" rows="3" placeholder="Ex.: cliente ausente, endereco nao localizado, entrega realizada com sucesso..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-outline-brand w-100" name="acao" value="tentativa">
                                <i class="bi bi-journal-plus"></i> Adicionar tentativa
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-brand w-100" name="acao" value="confirmar" data-confirm="Confirmar entrega deste pedido?">
                                <i class="bi bi-check2-circle"></i> Confirmar entrega
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-success mt-3 mb-0"><i class="bi bi-check2-circle"></i> Entrega confirmada.</p>
                <?php endif; ?>

                <?php if ($tentativas): ?>
                    <div class="mt-3 p-3 bg-light rounded-2">
                        <strong class="d-block mb-2">Historico de tentativas</strong>
                        <?php foreach ($tentativas as $tentativa): ?>
                            <div class="small border-bottom py-2">
                                <span class="badge text-bg-light border"><?= e($tentativa['status']) ?></span>
                                <span class="text-secondary"><?= e($tentativa['criado_em']) ?></span><br>
                                <?= e($tentativa['observacao']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$entregas): ?><p class="text-secondary mb-0">Nenhuma entrega vinculada ao seu usuario.</p><?php endif; ?>
        <?= pagination_links($entregasPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
