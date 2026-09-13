<?php
require_once __DIR__ . '/_pdv.php';
require_login('admin');

$caixa = pdv_caixa_aberto();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');
    $pdo = db();

    try {
        if ($acao === 'abrir') {
            if ($caixa) {
                throw new RuntimeException('Já existe um caixa aberto.');
            }
            $saldoInicial = max(0.0, (float) str_replace(',', '.', (string) ($_POST['saldo_inicial'] ?? '0')));
            $pdo->prepare(
                "INSERT INTO caixas (usuario_id, saldo_inicial, status) VALUES (:usuario_id, :saldo_inicial, 'aberto')"
            )->execute([
                'usuario_id' => (int) current_user()['id'],
                'saldo_inicial' => $saldoInicial,
            ]);
            flash('success', 'Caixa aberto com sucesso.');
        } elseif ($acao === 'sangria' || $acao === 'suprimento') {
            if (!$caixa) {
                throw new RuntimeException('Nenhum caixa aberto.');
            }
            $valor = max(0.01, (float) str_replace(',', '.', (string) ($_POST['valor'] ?? '0')));
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            $tipo = $acao === 'sangria' ? 'sangria' : 'entrada';
            $obs = ($acao === 'sangria' ? 'Sangria' : 'Suprimento');
            if ($observacao !== '') {
                $obs .= ': ' . $observacao;
            }
            $pdo->prepare(
                "INSERT INTO caixa_movimentos (caixa_id, tipo, metodo, valor, observacao, usuario_id)
                 VALUES (:caixa_id, :tipo, 'dinheiro', :valor, :observacao, :usuario_id)"
            )->execute([
                'caixa_id' => (int) $caixa['id'],
                'tipo' => $tipo,
                'valor' => $valor,
                'observacao' => $obs,
                'usuario_id' => (int) current_user()['id'],
            ]);
            flash('success', ucfirst($acao) . ' registrada.');
        } elseif ($acao === 'fechar') {
            if (!$caixa) {
                throw new RuntimeException('Nenhum caixa aberto.');
            }
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            $saldo = pdv_caixa_saldo($caixa);
            $pdo->prepare(
                "UPDATE caixas
                 SET status = 'fechado', saldo_final = :saldo_final, fechado_em = NOW(), observacao = :observacao
                 WHERE id = :id AND status = 'aberto'"
            )->execute([
                'saldo_final' => $saldo,
                'observacao' => $observacao !== '' ? $observacao : null,
                'id' => (int) $caixa['id'],
            ]);
            flash('success', 'Caixa fechado. Saldo final: ' . money_br($saldo));
        } else {
            throw new RuntimeException('Ação desconhecida.');
        }
    } catch (Throwable $e) {
        flash('danger', 'Não foi possível concluir a ação: ' . $e->getMessage());
    }

    redirect('admin/vendas/caixa.php');
}

$caixa = pdv_caixa_aberto();

$movimentos = [];
$resumoVendasPorMetodo = [];
$totais = ['saldo_inicial' => 0.0, 'vendas' => 0.0, 'entradas' => 0.0, 'sangrias' => 0.0, 'saidas' => 0.0, 'saldo' => 0.0];

if ($caixa) {
    $movimentos = db_all(
        "SELECT cm.*, u.nome AS usuario, v.numero AS venda_numero
         FROM caixa_movimentos cm
         LEFT JOIN usuarios u ON u.id = cm.usuario_id
         LEFT JOIN vendas v ON v.id = cm.venda_id
         WHERE cm.caixa_id = :caixa_id
         ORDER BY cm.id DESC
         LIMIT 100",
        ['caixa_id' => (int) $caixa['id']]
    );

    $totais['saldo_inicial'] = (float) $caixa['saldo_inicial'];
    foreach (db_all("SELECT tipo, metodo, valor, venda_id FROM caixa_movimentos WHERE caixa_id = :caixa_id", ['caixa_id' => (int) $caixa['id']]) as $mov) {
        $valor = (float) $mov['valor'];
        if ($mov['tipo'] === 'entrada') {
            if (!empty($mov['venda_id'])) {
                $totais['vendas'] += $valor;
                $resumoVendasPorMetodo[$mov['metodo']] = ($resumoVendasPorMetodo[$mov['metodo']] ?? 0) + $valor;
            } else {
                $totais['entradas'] += $valor;
            }
        } elseif ($mov['tipo'] === 'sangria') {
            $totais['sangrias'] += $valor;
        } else {
            $totais['saidas'] += $valor;
        }
    }
    $totais['saldo'] = pdv_caixa_saldo($caixa);
}

$caixasAnteriores = db_all(
    "SELECT c.*, u.nome AS usuario,
            (SELECT COALESCE(SUM(valor), 0) FROM caixa_movimentos cm WHERE cm.caixa_id = c.id AND cm.tipo = 'entrada') AS total_entradas,
            (SELECT COALESCE(SUM(valor), 0) FROM caixa_movimentos cm WHERE cm.caixa_id = c.id AND cm.tipo IN ('saida', 'sangria')) AS total_saidas
     FROM caixas c
     JOIN usuarios u ON u.id = c.usuario_id
     ORDER BY c.id DESC
     LIMIT 8"
);

$pageTitle = 'Caixa PDV';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4">
<div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
<div class="col-lg-9">
    <h1 class="h3 section-title">Caixa do PDV</h1>
    <?php pdv_portal_tabs('caixa'); ?>

    <?php if (!$caixa): ?>
        <div class="panel-card bg-white p-4">
            <h2 class="h5 section-title">Abrir caixa</h2>
            <p class="text-secondary">Abra um caixa para registrar as vendas presenciais do dia.</p>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="abrir">
                <div class="col-md-4"><label class="form-label">Saldo inicial (R$)</label><input class="form-control" name="saldo_inicial" type="number" step="0.01" min="0" value="0"></div>
                <div class="col-12"><button class="btn btn-brand"><i class="bi bi-cash-stack"></i> Abrir caixa</button></div>
            </form>
        </div>
    <?php else: ?>
        <div class="panel-card bg-white p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 section-title mb-1">Caixa #<?= (int) $caixa['id'] ?> · Aberto em <?= e($caixa['aberto_em']) ?></h2>
                    <div class="small text-secondary"><?= e($caixa['observacao'] ?? '') ?></div>
                </div>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalFechar"><i class="bi bi-box-arrow-right"></i> Fechar caixa</button>
            </div>

            <div class="row g-3">
                <div class="col-6 col-md-2"><div class="metric-label">Saldo inicial</div><strong><?= money_br($totais['saldo_inicial']) ?></strong></div>
                <div class="col-6 col-md-2"><div class="metric-label">Vendas</div><strong><?= money_br($totais['vendas']) ?></strong></div>
                <div class="col-6 col-md-2"><div class="metric-label">Suprimentos</div><strong><?= money_br($totais['entradas']) ?></strong></div>
                <div class="col-6 col-md-2"><div class="metric-label">Sangrias</div><strong><?= money_br($totais['sangrias']) ?></strong></div>
                <div class="col-6 col-md-2"><div class="metric-label">Outras saídas</div><strong><?= money_br($totais['saidas']) ?></strong></div>
                <div class="col-6 col-md-2"><div class="metric-label">Saldo em caixa</div><strong class="text-brand-price"><?= money_br($totais['saldo']) ?></strong></div>
            </div>

            <?php if ($resumoVendasPorMetodo): ?>
                <div class="mt-3">
                    <div class="metric-label mb-1">Vendas por forma de pagamento</div>
                    <?php foreach ($resumoVendasPorMetodo as $metodo => $valor): ?>
                        <span class="badge text-bg-light me-2"><?= e(pdv_payment_label((string) $metodo)) ?>: <?= money_br($valor) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row g-2 mt-3">
                <div class="col-md-5">
                    <div class="panel-card bg-white border p-3">
                        <h3 class="h6 fw-semibold">Sangria / Suprimento</h3>
                        <form method="post" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-6"><select class="form-select" name="acao"><option value="sangria">Sangria (retirada)</option><option value="suprimento">Suprimento (entrada)</option></select></div>
                            <div class="col-6"><input class="form-control" name="valor" type="number" step="0.01" min="0.01" placeholder="R$ 0,00" required></div>
                            <div class="col-12"><input class="form-control" name="observacao" placeholder="Motivo (ex.: troco, depósito)"></div>
                            <div class="col-12"><button class="btn btn-outline-brand btn-sm">Registrar</button></div>
                        </form>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="panel-card bg-white border p-3">
                        <h3 class="h6 fw-semibold">Últimos lançamentos</h3>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Data</th><th>Descrição</th><th class="text-end">Entrada</th><th class="text-end">Saída</th></tr></thead>
                                <tbody>
                                <?php foreach ($movimentos as $mov): ?>
                                    <tr>
                                        <td class="text-secondary small"><?= e($mov['criado_em']) ?></td>
                                        <td>
                                            <?php if (!empty($mov['venda_numero'])): ?>
                                                <a href="<?= e(base_url('admin/vendas/historico.php?venda=' . (int) $mov['venda_id'])) ?>"><?= e($mov['venda_numero']) ?></a>
                                            <?php else: ?>
                                                <?= e($mov['observacao']) ?>
                                            <?php endif; ?>
                                            <span class="badge text-bg-light ms-1"><?= e(pdv_payment_label((string) $mov['metodo'])) ?></span>
                                        </td>
                                        <td class="text-end"><?= in_array($mov['tipo'], ['entrada'], true) ? money_br((float) $mov['valor']) : '' ?></td>
                                        <td class="text-end"><?= in_array($mov['tipo'], ['saida', 'sangria'], true) ? money_br((float) $mov['valor']) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$movimentos): ?><tr><td colspan="4" class="text-center text-secondary py-3">Sem lançamentos neste caixa.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalFechar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content">
                    <?= csrf_field() ?>
                    <div class="modal-header"><h5 class="modal-title">Fechar caixa #<?= (int) $caixa['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                    <div class="modal-body">
                        <p class="text-secondary">Saldo esperado: <strong><?= money_br($totais['saldo']) ?></strong>. Após o fechamento, novas vendas ficarão bloqueadas até abrir outro caixa.</p>
                        <input type="hidden" name="acao" value="fechar">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" name="observacao" rows="2"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button class="btn btn-danger" type="submit"><i class="bi bi-box-arrow-right"></i> Confirmar fechamento</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($caixasAnteriores): ?>
        <div class="panel-card bg-white p-4 mt-4">
            <h2 class="h5 section-title">Caixas anteriores</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Caixa</th><th>Data</th><th>Operador</th><th class="text-end">Entradas</th><th class="text-end">Saídas</th><th class="text-end">Saldo final</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($caixasAnteriores as $c): ?>
                        <tr>
                            <td>#<?= (int) $c['id'] ?></td>
                            <td><?= e($c['aberto_em']) ?></td>
                            <td><?= e($c['usuario']) ?></td>
                            <td class="text-end"><?= money_br((float) $c['total_entradas']) ?></td>
                            <td class="text-end"><?= money_br((float) $c['total_saidas']) ?></td>
                            <td class="text-end"><?= ($c['status'] === 'fechado' && $c['saldo_final'] !== null) ? money_br((float) $c['saldo_final']) : '—' ?></td>
                            <td><?= $c['status'] === 'fechado' ? '<span class="badge text-bg-secondary">Fechado</span>' : '<span class="badge text-bg-success">Aberto</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>