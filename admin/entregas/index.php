<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/EmailService.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? 'salvar');

    if ($acao === 'cancelar') {
        $entregaId = (int) ($_POST['entrega_id'] ?? 0);
        $motivo = trim((string) ($_POST['motivo_cancelamento'] ?? ''));
        $entrega = db_one("SELECT id, pedido_id, status FROM entregas WHERE id = :id", ['id' => $entregaId]);

        if (!$entrega) {
            flash('danger', 'Entrega nao encontrada.');
            redirect('admin/entregas/index.php');
        }

        if (($entrega['status'] ?? '') === 'entregue') {
            flash('warning', 'Entrega ja confirmada nao pode ser cancelada por esta tela.');
            redirect('admin/entregas/index.php');
        }

        if (strlen($motivo) < 10) {
            flash('warning', 'Informe uma justificativa com pelo menos 10 caracteres.');
            redirect('admin/entregas/index.php');
        }

        db()->prepare(
            "UPDATE entregas
             SET status = 'cancelada', motivo_cancelamento = :motivo, cancelada_em = NOW(), cancelada_por = :usuario_id
             WHERE id = :id"
        )->execute([
            'id' => $entregaId,
            'motivo' => $motivo,
            'usuario_id' => (int) current_user()['id'],
        ]);

        db()->prepare("UPDATE pedidos SET status = 'separacao', atualizado_em = NOW() WHERE id = :id")->execute([
            'id' => (int) $entrega['pedido_id'],
        ]);

        pedido_send_status_email((int) $entrega['pedido_id'], 'separacao', 'A entrega foi cancelada pelo gestor e o pedido voltou para separacao.');
        flash('success', 'Entrega cancelada com justificativa.');
        redirect('admin/entregas/index.php');
    }

    $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
    $entregadorId = (int) ($_POST['entregador_id'] ?? 0);

    if ($pedidoId <= 0 || $entregadorId <= 0) {
        flash('warning', 'Selecione pedido e entregador.');
        redirect('admin/entregas/index.php');
    }

    $entregador = db_one("SELECT id FROM usuarios WHERE id = :id AND tipo = 'entregador' AND ativo = 1", ['id' => $entregadorId]);
    if (!$entregador) {
        flash('danger', 'Entregador invalido ou inativo.');
        redirect('admin/entregas/index.php');
    }

    $entrega = db_one("SELECT id FROM entregas WHERE pedido_id = :pedido_id LIMIT 1", ['pedido_id' => $pedidoId]);
    if ($entrega) {
        db()->prepare(
            "UPDATE entregas
             SET entregador_id = :entregador_id, transportadora = :transportadora, servico = :servico,
                 codigo_rastreio = :codigo_rastreio, status = 'em_rota', previsao_entrega = :previsao_entrega,
                 enviado_em = COALESCE(enviado_em, NOW()), motivo_cancelamento = NULL, cancelada_em = NULL, cancelada_por = NULL
             WHERE id = :id"
        )->execute([
            'id' => (int) $entrega['id'],
            'entregador_id' => $entregadorId,
            'transportadora' => trim((string) ($_POST['transportadora'] ?? '')),
            'servico' => trim((string) ($_POST['servico'] ?? '')),
            'codigo_rastreio' => trim((string) ($_POST['codigo_rastreio'] ?? '')),
            'previsao_entrega' => $_POST['previsao_entrega'] ?: null,
        ]);
        $entregaId = (int) $entrega['id'];
    } else {
        db()->prepare(
            "INSERT INTO entregas (pedido_id, entregador_id, transportadora, servico, codigo_rastreio, status, previsao_entrega, enviado_em)
             VALUES (:pedido_id, :entregador_id, :transportadora, :servico, :codigo_rastreio, 'em_rota', :previsao_entrega, NOW())"
        )->execute([
            'pedido_id' => $pedidoId,
            'entregador_id' => $entregadorId,
            'transportadora' => trim((string) ($_POST['transportadora'] ?? '')),
            'servico' => trim((string) ($_POST['servico'] ?? '')),
            'codigo_rastreio' => trim((string) ($_POST['codigo_rastreio'] ?? '')),
            'previsao_entrega' => $_POST['previsao_entrega'] ?: null,
        ]);
        $entregaId = (int) db()->lastInsertId();
    }

    db()->prepare("UPDATE pedidos SET status = 'enviado', codigo_rastreio = :codigo, atualizado_em = NOW() WHERE id = :id")->execute([
        'id' => $pedidoId,
        'codigo' => trim((string) ($_POST['codigo_rastreio'] ?? '')) ?: null,
    ]);

    pedido_send_status_email($pedidoId, 'enviado', 'Seu pedido foi encaminhado para entrega.');
    pedido_send_delivery_assigned_email($pedidoId, $entregaId);
    flash('success', 'Entrega vinculada ao entregador.');
    redirect('admin/entregas/index.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$inicio = trim((string) ($_GET['inicio'] ?? ''));
$fim = trim((string) ($_GET['fim'] ?? ''));
$statusOptions = [
    '' => 'Todos os status',
    'sem_entrega' => 'Sem entrega',
    'pendente' => 'Pendente',
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
$entregadores = db_all("SELECT id, nome FROM usuarios WHERE tipo = 'entregador' AND ativo = 1 ORDER BY nome");
$pedidosPage = paginate_query(
    "SELECT p.*, u.nome AS cliente, en.id AS entrega_id, en.entregador_id, en.status AS entrega_status,
            en.transportadora, en.servico, en.codigo_rastreio AS entrega_rastreio, en.previsao_entrega,
            en.motivo_cancelamento, en.cancelada_em, eu.nome AS entregador,
            e.logradouro, e.numero, e.bairro, e.cidade, e.uf,
            (SELECT COUNT(*) FROM entrega_tentativas et WHERE et.entrega_id = en.id) AS tentativas_total
     FROM pedidos p
     JOIN usuarios u ON u.id = p.usuario_id
     LEFT JOIN entregas en ON en.pedido_id = p.id
     LEFT JOIN usuarios eu ON eu.id = en.entregador_id
     LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
     WHERE (:q = '' OR p.id = :id_busca OR u.nome LIKE :like_q OR p.status LIKE :like_q OR eu.nome LIKE :like_q OR en.status LIKE :like_q)
       AND (:status = '' OR (:status = 'sem_entrega' AND en.id IS NULL) OR en.status = :status)
       AND (:inicio = '' OR DATE(COALESCE(en.enviado_em, p.criado_em)) >= :inicio)
       AND (:fim = '' OR DATE(COALESCE(en.enviado_em, p.criado_em)) <= :fim)
     ORDER BY p.criado_em DESC",
    ['q' => $q, 'id_busca' => (int) $q, 'like_q' => '%' . $q . '%', 'status' => $status, 'inicio' => $inicio, 'fim' => $fim]
);
$pedidos = $pedidosPage['rows'];

$tentativasPorEntrega = [];
$entregaIds = [];
foreach ($pedidos as $pedido) {
    if (!empty($pedido['entrega_id'])) {
        $entregaIds[] = (int) $pedido['entrega_id'];
    }
}

if ($entregaIds) {
    $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));
    $stmt = db()->prepare(
        "SELECT et.*, u.nome AS entregador
         FROM entrega_tentativas et
         LEFT JOIN usuarios u ON u.id = et.entregador_id
         WHERE et.entrega_id IN ($placeholders)
         ORDER BY et.criado_em DESC"
    );
    $stmt->execute($entregaIds);
    foreach ($stmt->fetchAll() as $tentativa) {
        $tentativasPorEntrega[(int) $tentativa['entrega_id']][] = $tentativa;
    }
}

$pageTitle = 'Entregas';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 section-title mb-1">Vincular entregas</h1>
                <p class="text-secondary mb-0">Acompanhe tentativas, vincule entregador e cancele entregas com justificativa quando necessario.</p>
            </div>
            <form class="search-control row g-2" method="get">
                <div class="col-md-4"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Pedido, cliente ou entregador"></div></div>
                <div class="col-md-2"><select class="form-select" name="status"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><input class="form-control" type="date" name="inicio" value="<?= e($inicio) ?>" title="Data inicial"></div>
                <div class="col-md-2"><input class="form-control" type="date" name="fim" value="<?= e($fim) ?>" title="Data final"></div>
                <div class="col-md-2"><button class="btn btn-brand w-100">Buscar</button></div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente e endereco</th>
                        <th>Status</th>
                        <th>Entregador</th>
                        <th>Vincular</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php $tentativas = !empty($pedido['entrega_id']) ? ($tentativasPorEntrega[(int) $pedido['entrega_id']] ?? []) : []; ?>
                        <tr>
                            <td>#<?= (int) $pedido['id'] ?><br><small><?= money_br((float) $pedido['total']) ?></small></td>
                            <td>
                                <strong><?= e($pedido['cliente']) ?></strong><br>
                                <small class="text-secondary"><?= e(trim((string) $pedido['logradouro'] . ', ' . (string) $pedido['numero'] . ' - ' . (string) $pedido['bairro'] . ' - ' . (string) $pedido['cidade'] . '/' . (string) $pedido['uf'])) ?></small>
                            </td>
                            <td>
                                <?= e(pedido_status_label($pedido['status'])) ?><br>
                                <span class="badge <?= $pedido['entrega_status'] === 'cancelada' ? 'text-bg-danger' : ($pedido['entrega_status'] === 'entregue' ? 'text-bg-success' : 'text-bg-light') ?>"><?= e($pedido['entrega_status'] ?: 'sem entrega') ?></span>
                                <?php if ((int) ($pedido['tentativas_total'] ?? 0) > 0): ?>
                                    <div class="small text-secondary mt-1"><?= (int) $pedido['tentativas_total'] ?> tentativa(s)</div>
                                <?php endif; ?>
                                <?php if ($pedido['entrega_status'] === 'cancelada'): ?>
                                    <div class="small text-danger mt-1"><?= e($pedido['motivo_cancelamento']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($pedido['entregador'] ?: 'Nao vinculado') ?></td>
                            <td>
                                <form method="post" class="row g-2" style="min-width: 380px">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="salvar">
                                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                    <div class="col-12">
                                        <select class="form-select form-select-sm" name="entregador_id" required>
                                            <option value="">Entregador</option>
                                            <?php foreach ($entregadores as $entregador): ?>
                                                <option value="<?= (int) $entregador['id'] ?>" <?= (int) ($pedido['entregador_id'] ?? 0) === (int) $entregador['id'] ? 'selected' : '' ?>><?= e($entregador['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6"><input class="form-control form-control-sm" name="transportadora" placeholder="Transportadora" value="<?= e($pedido['transportadora'] ?? '') ?>"></div>
                                    <div class="col-6"><input class="form-control form-control-sm" name="servico" placeholder="Servico" value="<?= e($pedido['servico'] ?? '') ?>"></div>
                                    <div class="col-6"><input class="form-control form-control-sm" name="codigo_rastreio" placeholder="Rastreio" value="<?= e($pedido['entrega_rastreio']) ?>"></div>
                                    <div class="col-6"><input class="form-control form-control-sm" name="previsao_entrega" type="date" value="<?= e($pedido['previsao_entrega']) ?>"></div>
                                    <div class="col-12"><button class="btn btn-sm btn-brand"><i class="bi bi-truck"></i> Salvar entrega</button></div>
                                </form>

                                <?php if (!empty($pedido['entrega_id']) && !in_array($pedido['entrega_status'], ['entregue', 'cancelada'], true)): ?>
                                    <form method="post" class="mt-3" style="min-width: 380px">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="cancelar">
                                        <input type="hidden" name="entrega_id" value="<?= (int) $pedido['entrega_id'] ?>">
                                        <label class="form-label small">Cancelar entrega com justificativa</label>
                                        <textarea class="form-control form-control-sm mb-2" name="motivo_cancelamento" rows="2" placeholder="Informe o motivo considerando as tentativas de entrega"></textarea>
                                        <button class="btn btn-sm btn-outline-danger" data-confirm="Cancelar esta entrega?"><i class="bi bi-x-octagon"></i> Cancelar entrega</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($tentativas): ?>
                            <tr>
                                <td></td>
                                <td colspan="4">
                                    <div class="bg-light rounded-2 p-3">
                                        <strong class="d-block mb-2">Historico de tentativas do pedido #<?= (int) $pedido['id'] ?></strong>
                                        <?php foreach ($tentativas as $tentativa): ?>
                                            <div class="small border-bottom py-2">
                                                <span class="badge text-bg-light border"><?= e($tentativa['status']) ?></span>
                                                <span class="text-secondary"><?= e($tentativa['criado_em']) ?> por <?= e($tentativa['entregador'] ?: 'Entregador') ?></span><br>
                                                <?= e($tentativa['observacao']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$pedidos): ?><p class="text-secondary mb-0">Nenhum pedido localizado.</p><?php endif; ?>
        <?= pagination_links($pedidosPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
