<?php
require_once __DIR__ . '/_pdv.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'receber') {
        $parcelaId = (int) ($_POST['parcela_id'] ?? 0);
        $metodo = (string) ($_POST['pago_metodo'] ?? '');
        if (!array_key_exists($metodo, pdv_payment_methods()) || $metodo === 'a_prazo') {
            $metodo = 'dinheiro';
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $parcela = db_one("SELECT * FROM venda_parcelas WHERE id = :id FOR UPDATE", ['id' => $parcelaId]);
            if (!$parcela || $parcela['status'] !== 'pendente') {
                throw new RuntimeException('Parcela não está pendente.');
            }

            $pdo->prepare(
                "UPDATE venda_parcelas SET status = 'pago', pago_em = NOW(), pago_metodo = :metodo WHERE id = :id"
            )->execute(['metodo' => $metodo, 'id' => $parcelaId]);

            $restantes = (int) (db_one(
                "SELECT COUNT(*) AS total FROM venda_parcelas WHERE venda_id = :venda_id AND status = 'pendente'",
                ['venda_id' => (int) $parcela['venda_id']]
            )['total'] ?? 0);
            if ($restantes === 0) {
                $pdo->prepare("UPDATE venda_pagamentos SET status = 'pago', pago_em = NOW() WHERE venda_id = :venda_id AND metodo = 'a_prazo' AND status = 'pendente'")
                    ->execute(['venda_id' => (int) $parcela['venda_id']]);
            }

            $caixa = pdv_caixa_aberto();
            if ($caixa) {
                $pdo->prepare(
                    "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                     VALUES (:caixa_id, :venda_id, 'entrada', :metodo, :valor, :observacao, :usuario_id)"
                )->execute([
                    'caixa_id' => (int) $caixa['id'],
                    'venda_id' => (int) $parcela['venda_id'],
                    'metodo' => $metodo,
                    'valor' => (float) $parcela['valor'],
                    'observacao' => 'Recebimento de parcela (venda PDV)',
                    'usuario_id' => (int) current_user()['id'],
                ]);
            }

            $pdo->commit();
            pdv_sync_venda_status((int) $parcela['venda_id']);
            flash('success', 'Parcela de ' . money_br((float) $parcela['valor']) . ' recebida.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Não foi possível registrar o recebimento: ' . $e->getMessage());
        }

        redirect('admin/vendas/receber.php');
    }

    if ($acao === 'receber_caixa') {
        $vendaId = (int) ($_POST['venda_id'] ?? 0);
        $metodos = (array) ($_POST['pagamento_metodo'] ?? []);
        $valores = (array) ($_POST['pagamento_valor'] ?? []);

        $pagamentos = [];
        foreach ($metodos as $i => $m) {
            $m = trim((string) $m);
            $v = pdv_money_to_float((string) ($valores[$i] ?? ''));
            if (!array_key_exists($m, pdv_payment_methods()) || $m === 'a_prazo') {
                continue;
            }
            if ($v > 0) {
                $pagamentos[] = ['metodo' => $m, 'valor' => round($v, 2)];
            }
        }

        $pdo = db();
        try {
            if ($vendaId <= 0) {
                throw new RuntimeException('Informe a venda.');
            }
            $venda = db_one("SELECT * FROM vendas WHERE id = :id", ['id' => $vendaId]);
            if (!$venda || $venda['status'] === 'cancelada') {
                throw new RuntimeException('Venda não encontrada.');
            }
            if (!$pagamentos) {
                throw new RuntimeException('Informe ao menos um pagamento válido.');
            }

            $pdo->beginTransaction();

            $pendentes = db_all(
                "SELECT * FROM venda_parcelas WHERE venda_id = :venda_id AND status = 'pendente'
                 ORDER BY vencimento ASC, id ASC",
                ['venda_id' => $vendaId]
            );
            $devido = round(array_sum(array_column($pendentes, 'valor')), 2);
            if ($devido <= 0) {
                throw new RuntimeException('Esta venda não possui valores em aberto.');
            }

            $pago = round(array_sum(array_column($pagamentos, 'valor')), 2);
            $aplicado = round(min($pago, $devido), 2);
            $troco = round($pago - $aplicado, 2);
            $restante = round($devido - $aplicado, 2);

            $updateParcela = $pdo->prepare(
                "UPDATE venda_parcelas SET valor = :valor, status = 'pago', pago_em = NOW(), pago_metodo = :metodo WHERE id = :id"
            );
            $insertParcela = $pdo->prepare(
                "INSERT INTO venda_parcelas (venda_id, parcela, vencimento, valor, status)
                 VALUES (:venda_id, :parcela, :vencimento, :valor, 'pendente')"
            );

            $sobra = $aplicado;
            $metodoPrincipal = $pagamentos[0]['metodo'];
            foreach ($pendentes as $par) {
                if ($sobra <= 0.0001) {
                    break;
                }
                $valorParcela = round((float) $par['valor'], 2);
                if ($valorParcela <= $sobra + 0.0001) {
                    $updateParcela->execute([
                        'valor' => $valorParcela,
                        'metodo' => $metodoPrincipal,
                        'id' => (int) $par['id'],
                    ]);
                    $sobra = round($sobra - $valorParcela, 2);
                } else {
                    $updateParcela->execute([
                        'valor' => $sobra,
                        'metodo' => $metodoPrincipal,
                        'id' => (int) $par['id'],
                    ]);
                    $proximoNro = (int) (db_one(
                        "SELECT COALESCE(MAX(parcela), 0) + 1 AS nro FROM venda_parcelas WHERE venda_id = :venda_id",
                        ['venda_id' => $vendaId]
                    )['nro'] ?? 1);
                    $insertParcela->execute([
                        'venda_id' => $vendaId,
                        'parcela' => $proximoNro,
                        'vencimento' => $par['vencimento'],
                        'valor' => round($valorParcela - $sobra, 2),
                    ]);
                    $sobra = 0.0;
                }
            }

            $futuras = (int) ($_POST['parcelas_futuras'] ?? 0);
            if ($restante > 0 && $futuras > 0) {
                $pdo->prepare("UPDATE venda_parcelas SET status = 'cancelado' WHERE venda_id = :venda_id AND status = 'pendente'")
                    ->execute(['venda_id' => $vendaId]);

                $base = trim((string) ($_POST['vencimento_base'] ?? ''));
                if ($base === '') {
                    $base = date('Y-m-d', strtotime('+30 days'));
                }
                $proximoNro = (int) (db_one(
                    "SELECT COALESCE(MAX(parcela), 0) + 1 AS nro FROM venda_parcelas WHERE venda_id = :venda_id",
                    ['venda_id' => $vendaId]
                )['nro'] ?? 1);
                $valorParcela = round($restante / $futuras, 2);
                $acumulado = 0.0;
                for ($f = 1; $f <= $futuras; $f++) {
                    $valorFuturo = $valorParcela;
                    if ($f === $futuras) {
                        $valorFuturo = round($restante - $acumulado, 2);
                    }
                    $acumulado += $valorFuturo;
                    $insertParcela->execute([
                        'venda_id' => $vendaId,
                        'parcela' => $proximoNro++,
                        'vencimento' => date('Y-m-d', strtotime($base . ' +' . ($f - 1) . ' months')),
                        'valor' => $valorFuturo,
                    ]);
                }
            }

            $insertPagamento = $pdo->prepare(
                "INSERT INTO venda_pagamentos (venda_id, metodo, valor, status, pago_em)
                 VALUES (:venda_id, :metodo, :valor, 'pago', NOW())"
            );
            foreach ($pagamentos as $pg) {
                $insertPagamento->execute([
                    'venda_id' => $vendaId,
                    'metodo' => $pg['metodo'],
                    'valor' => $pg['valor'],
                ]);
            }

            if ($restante <= 0) {
                $pdo->prepare("UPDATE venda_pagamentos SET status = 'pago', pago_em = NOW() WHERE venda_id = :venda_id AND metodo = 'a_prazo' AND status = 'pendente'")
                    ->execute(['venda_id' => $vendaId]);
            }

            $caixa = pdv_caixa_aberto();
            if ($caixa) {
                $movCaixa = $pdo->prepare(
                    "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                     VALUES (:caixa_id, :venda_id, :tipo, :metodo, :valor, :observacao, :usuario_id)"
                );
                foreach ($pagamentos as $pg) {
                    $movCaixa->execute([
                        'caixa_id' => (int) $caixa['id'],
                        'venda_id' => $vendaId,
                        'tipo' => 'entrada',
                        'metodo' => $pg['metodo'],
                        'valor' => $pg['valor'],
                        'observacao' => $restante > 0 ? 'Recebimento parcial de venda ' . $venda['numero'] : 'Quitação de venda ' . $venda['numero'],
                        'usuario_id' => (int) current_user()['id'],
                    ]);
                }
                if ($troco > 0) {
                    $movCaixa->execute([
                        'caixa_id' => (int) $caixa['id'],
                        'venda_id' => $vendaId,
                        'tipo' => 'saida',
                        'metodo' => 'dinheiro',
                        'valor' => $troco,
                        'observacao' => 'Troco do recebimento ' . $venda['numero'],
                        'usuario_id' => (int) current_user()['id'],
                    ]);
                }
            }

            $pdo->commit();
            pdv_sync_venda_status((int) $venda['id']);

            $mensagem = 'Recebimento de ' . money_br($aplicado) . ' registrado.';
            if ($restante > 0) {
                $mensagem .= ' Saldo a receber: ' . money_br($restante) . '.';
            }
            if ($troco > 0) {
                $mensagem .= ' Troco: ' . money_br($troco) . '.';
            }
            flash('success', $mensagem);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Não foi possível registrar o recebimento: ' . $e->getMessage());
        }

        redirect('admin/vendas/cupom.php?venda=' . $vendaId);
    }
}

$statusFiltro = trim((string) ($_GET['status'] ?? ''));
if (!in_array($statusFiltro, ['pendente', 'pago'], true)) {
    $statusFiltro = 'pendente';
}

$parcelasPage = paginate_query(
    "SELECT par.*, v.numero, v.total AS venda_total, cliente.nome AS cliente,
            (par.vencimento < CURDATE() AND par.status = 'pendente') AS vencida
     FROM venda_parcelas par
     JOIN vendas v ON v.id = par.venda_id
     LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
     WHERE v.status <> 'cancelada' AND (:status = '' OR par.status = :status)
     ORDER BY par.status = 'pendente' DESC, par.vencimento ASC, par.id ASC",
    ['status' => $statusFiltro],
    null,
    20
);
$parcelas = $parcelasPage['rows'];

$resumo = [
    'aberto' => (float) (db_one("SELECT COALESCE(SUM(valor), 0) AS total FROM venda_parcelas par JOIN vendas v ON v.id = par.venda_id WHERE v.status <> 'cancelada' AND par.status = 'pendente'")['total'] ?? 0),
    'vencido' => (float) (db_one("SELECT COALESCE(SUM(valor), 0) AS total FROM venda_parcelas par JOIN vendas v ON v.id = par.venda_id WHERE v.status <> 'cancelada' AND par.status = 'pendente' AND par.vencimento < CURDATE()")['total'] ?? 0),
];

$vendasAberto = db_all(
    "SELECT v.id, v.numero, v.total, cliente.nome AS cliente,
            (SELECT COALESCE(SUM(par.valor), 0) FROM venda_parcelas par WHERE par.venda_id = v.id AND par.status = 'pendente') AS aberto
     FROM vendas v
     LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
     WHERE v.status <> 'cancelada'
       AND EXISTS (SELECT 1 FROM venda_parcelas par WHERE par.venda_id = v.id AND par.status = 'pendente')
     ORDER BY v.id DESC"
);

$pageTitle = 'A receber';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4">
<div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
<div class="col-lg-9">
    <h1 class="h3 section-title">Contas a receber</h1>
    <?php pdv_portal_tabs('receber'); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="panel-card bg-white p-4"><span class="text-secondary d-block">Total a receber</span><strong class="fs-4"><?= money_br($resumo['aberto']) ?></strong></div></div>
        <div class="col-md-4"><div class="panel-card bg-white p-4"><span class="text-secondary d-block">Vencidas</span><strong class="fs-4 text-danger"><?= money_br($resumo['vencido']) ?></strong></div></div>
        <div class="col-md-4"><div class="panel-card bg-white p-4"><span class="text-secondary d-block">Em dia</span><strong class="fs-4 text-success"><?= money_br($resumo['aberto'] - $resumo['vencido']) ?></strong></div></div>
    </div>

    <div class="panel-card bg-white p-4 mb-4">
        <h2 class="h6 mb-3"><i class="bi bi-cash-coin text-success"></i> Recebimento — igual ao caixa</h2>
        <form method="post" id="receberCaixaForm">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="receber_caixa">
            <input type="hidden" name="venda_id" id="recVendaId" value="">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Selecione a venda (saldo em aberto)</label>
                    <select class="form-select" id="recVenda" required>
                        <option value="">— Escolha uma venda —</option>
                        <?php foreach ($vendasAberto as $va): ?>
                            <option value="<?= (int) $va['id'] ?>" data-aberto="<?= e((string) round((float) $va['aberto'], 2)) ?>">
                                <?= e($va['numero']) ?> · <?= e($va['cliente'] ?? 'Cliente avulso') ?> · aberto <?= money_br((float) $va['aberto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$vendasAberto): ?><div class="small text-secondary mt-1">Nenhuma venda com parcelas em aberto.</div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor em aberto</label>
                    <div class="form-control fw-bold" id="recDue" style="background:#fdf3f5;">R$ 0,00</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Troco (calculado)</label>
                    <div class="form-control" id="recTroco">R$ 0,00</div>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Pagamentos</label>
                <div id="recRows"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="recAddRow"><i class="bi bi-plus-lg"></i> Incluir pagamento</button>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label class="form-label" for="recFuturas">Parcelas futuras (restante)</label>
                    <input type="number" class="form-control" name="parcelas_futuras" id="recFuturas" min="0" max="999" value="0" title="Quantas parcelas dividem o valor que sobrar">
                    <div class="form-text">0 = mantém as parcelas atuais</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="recVenc">1º vencimento (parcela)</label>
                    <input type="date" class="form-control" name="vencimento_base" id="recVenc">
                    <div class="form-text">Vazio = 30 dias</div>
                </div>
            </div>

            <div class="mt-3 d-flex align-items-center gap-3">
                <button class="btn btn-brand" id="recConfirm" disabled><i class="bi bi-check2-circle"></i> Confirmar recebimento</button>
                <span class="small text-secondary">Após confirmar, o cupom não fiscal é gerado já com as parcelas em aberto.</span>
            </div>
        </form>
    </div>

    <div class="panel-card bg-white p-4">
        <form class="search-control mb-3" method="get">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-funnel"></i></span>
                <select class="form-select" name="status">
                    <option value="pendente" <?= $statusFiltro === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="pago" <?= $statusFiltro === 'pago' ? 'selected' : '' ?>>Pagas</option>
                    <option value="" <?= $statusFiltro === '' ? 'selected' : '' ?>>Todas</option>
                </select>
                <button class="btn btn-brand">Filtrar</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Venda</th><th>Cliente</th><th>Parcela</th><th>Vencimento</th><th class="text-end">Valor</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($parcelas as $par): ?>
                    <tr>
                        <td><a href="<?= e(base_url('admin/vendas/historico.php?venda=' . (int) $par['venda_id'])) ?>"><?= e($par['numero']) ?></a></td>
                        <td><?= e($par['cliente'] ?? '—') ?></td>
                        <td><?= (int) $par['parcela'] ?>ª parcela</td>
                        <td><?= e(date('d/m/Y', strtotime($par['vencimento']))) ?></td>
                        <td class="text-end"><?= money_br((float) $par['valor']) ?></td>
                        <td>
                            <?php if ($par['status'] === 'pago'): ?>
                                <span class="badge text-bg-success">Pago <?= e($par['pago_em'] ? date('d/m/Y', strtotime($par['pago_em'])) : '') ?></span>
                            <?php elseif (!empty($par['vencida'])): ?>
                                <span class="badge text-bg-danger">Vencida</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Em aberto</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($par['status'] === 'pendente'): ?>
                                <button class="btn btn-sm btn-outline-brand" data-bs-toggle="modal" data-bs-target="#modalReceber<?= (int) $par['id'] ?>"><i class="bi bi-check2-circle"></i> Receber</button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($par['status'] === 'pendente'): ?>
                    <div class="modal fade" id="modalReceber<?= (int) $par['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <?= csrf_field() ?>
                                <div class="modal-header"><h5 class="modal-title">Receber <?= (int) $par['parcela'] ?>ª parcela</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                                <div class="modal-body">
                                    <p>Venda <?= e($par['numero']) ?> · Valor: <strong><?= money_br((float) $par['valor']) ?></strong></p>
                                    <input type="hidden" name="acao" value="receber">
                                    <input type="hidden" name="parcela_id" value="<?= (int) $par['id'] ?>">
                                    <label class="form-label">Receber via</label>
                                    <select class="form-select" name="pago_metodo">
                                        <?php foreach (pdv_payment_methods() as $slug => $label): ?>
                                            <?php if ($slug === 'a_prazo') { continue; } ?>
                                            <option value="<?= e($slug) ?>"><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                                    <button class="btn btn-brand" type="submit"><i class="bi bi-check2-circle"></i> Confirmar recebimento</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$parcelas): ?><tr><td colspan="7" class="text-center text-secondary py-4">Nenhuma parcela localizada.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($parcelasPage) ?>
    </div>
</div>
</div></div></section>

<script>
(function () {
    const methods = [
        { slug: 'dinheiro', label: 'Dinheiro' },
        { slug: 'pix', label: 'Pix' },
        { slug: 'cartao_credito', label: 'Cartão de crédito' },
        { slug: 'cartao_debito', label: 'Cartão de débito' }
    ];
    const rows = document.getElementById('recRows');
    const vendaSelect = document.getElementById('recVenda');
    const vendaIdInput = document.getElementById('recVendaId');
    const dueEl = document.getElementById('recDue');
    const trocoEl = document.getElementById('recTroco');
    const confirmBtn = document.getElementById('recConfirm');

    function moneyToFloat(text) {
        if (text === undefined || text === null) return 0;
        let v = String(text).trim();
        if (!v) return 0;
        v = v.replace(/[R$\s.]/g, '');
        if (v.indexOf(',') > -1) v = v.replace(',', '.');
        const f = parseFloat(v);
        return isNaN(f) ? 0 : f;
    }
    function round2(value) { return Math.round((value + Number.EPSILON) * 100) / 100; }
    function fmtMoney(value) {
        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function currentValor() {
        return vendaSelect.selectedIndex > 0 ? moneyToFloat(vendaSelect.options[vendaSelect.selectedIndex].dataset.aberto) : 0;
    }

    function addRow() {
        const div = document.createElement('div');
        div.className = 'row-pay input-group mb-2';
        const sel = document.createElement('select');
        sel.className = 'form-select pay-metodo';
        sel.name = 'pagamento_metodo[]';
        methods.forEach(function (m) {
            const opt = document.createElement('option');
            opt.value = m.slug;
            opt.textContent = m.label;
            sel.appendChild(opt);
        });
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control pay-valor text-end';
        input.name = 'pagamento_valor[]';
        input.inputMode = 'decimal';
        input.placeholder = '0,00';
        input.addEventListener('input', recalc);
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-outline-secondary';
        del.innerHTML = '<i class="bi bi-x-lg"></i>';
        del.addEventListener('click', function () {
            div.remove();
            recalc();
        });
        div.appendChild(sel);
        div.appendChild(input);
        div.appendChild(del);
        rows.appendChild(div);
        input.focus();
    }

    function recalc() {
        const due = round2(currentValor());
        let paid = 0;
        rows.querySelectorAll('.row-pay').forEach(function (row) {
            paid = round2(paid + moneyToFloat(row.querySelector('.pay-valor').value));
        });
        const troco = paid > due ? round2(paid - due) : 0;
        dueEl.textContent = 'R$ ' + fmtMoney(due);
        trocoEl.textContent = 'R$ ' + fmtMoney(troco);
        trocoEl.classList.toggle('text-success', troco > 0);
        confirmBtn.disabled = due <= 0 || paid <= 0;
    }

    vendaSelect.addEventListener('change', function () {
        vendaIdInput.value = vendaSelect.value;
        rows.innerHTML = '';
        addRow();
        recalc();
    });

    document.getElementById('recAddRow').addEventListener('click', function () {
        if (vendaSelect.value === '') return;
        rows.querySelectorAll('.row-pay').forEach(function (row) {
            const input = row.querySelector('.pay-valor');
            if (moneyToFloat(input.value) <= 0) input.remove();
        });
        addRow();
        recalc();
    });

    vendaSelect.dispatchEvent(new Event('change'));
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>