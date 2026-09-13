<?php
require_once __DIR__ . '/_pdv.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';
require_once dirname(__DIR__, 2) . '/includes/EfiService.php';
require_login('admin');

function pdv_venda_com_pendentes(int $vendaId): ?array
{
    return db_one(
        "SELECT v.*, vendedor.nome AS vendedor, cliente.nome AS cliente
         FROM vendas v
         LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
         LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
         WHERE v.id = :id",
        ['id' => $vendaId]
    );
}

function pdv_confirmar_pagamento(int $pagamentoId, string $observacao, string $flashMessage): void
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $pagamento = db_one(
            "SELECT * FROM venda_pagamentos WHERE id = :id AND status = 'pendente' FOR UPDATE",
            ['id' => $pagamentoId]
        );
        if (!$pagamento) {
            throw new RuntimeException('Pagamento não encontrado ou já confirmado.');
        }

        $venda = db_one("SELECT * FROM vendas WHERE id = :id", ['id' => (int) $pagamento['venda_id']]);
        if (!$venda || !in_array($venda['status'], ['finalizada', 'pendente'], true)) {
            throw new RuntimeException('Venda não está em situação de recebimento.');
        }

        $pdo->prepare("UPDATE venda_pagamentos SET status = 'pago', pago_em = NOW() WHERE id = :id")->execute(['id' => $pagamentoId]);

        $caixa = pdv_caixa_aberto();
        if ($caixa) {
            $pdo->prepare(
                "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                 VALUES (:caixa_id, :venda_id, 'entrada', :metodo, :valor, :observacao, :usuario_id)"
            )->execute([
                'caixa_id' => (int) $caixa['id'],
                'venda_id' => (int) $pagamento['venda_id'],
                'metodo' => (string) $pagamento['metodo'],
                'valor' => (float) $pagamento['valor'],
                'observacao' => $observacao,
                'usuario_id' => (int) current_user()['id'],
            ]);
        }

        pdv_sync_venda_status((int) $pagamento['venda_id']);

        $pdo->commit();
        flash('success', $flashMessage . (!$caixa ? ' Atenção: não havia caixa aberto, o valor não foi lançado no caixa.' : ''));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Não foi possível confirmar o pagamento: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'confirmar') {
        $pagamentoId = (int) ($_POST['pagamento_id'] ?? 0);
        $venda = db_one(
            "SELECT numero FROM vendas WHERE id = (SELECT venda_id FROM venda_pagamentos WHERE id = :pagamento_id)",
            ['pagamento_id' => $pagamentoId]
        );
        pdv_confirmar_pagamento(
            $pagamentoId,
            'Confirmação online da venda ' . (string) ($venda['numero'] ?? '') . ' (PDV)',
            'Pagamento confirmado e lançado no caixa.'
        );
        redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
    }

    if ($acao === 'consultar') {
        $pagamentoId = (int) ($_POST['pagamento_id'] ?? 0);
        $pagamento = db_one("SELECT * FROM venda_pagamentos WHERE id = :id", ['id' => $pagamentoId]);
        if (!$pagamento || $pagamento['status'] !== 'pendente') {
            flash('warning', 'Pagamento não encontrado ou já confirmado.');
            redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
        }

        $venda = db_one("SELECT * FROM vendas WHERE id = :id", ['id' => (int) $pagamento['venda_id']]);
        if (!$venda) {
            flash('danger', 'Venda não localizada.');
            redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
        }

        $pago = false;
        $statusInfo = '';
        if ($pagamento['metodo'] === 'pix') {
            $resultado = efi_pix_status((string) ($venda['pix_txid'] ?? ''));
            if (!$resultado['ok']) {
                flash('warning', $resultado['message']);
                redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
            }
            $statusInfo = 'Pix status: ' . $resultado['status'];
            $pago = $resultado['status'] === 'CONCLUIDA';
        } elseif ($pagamento['metodo'] === 'cartao_credito') {
            $resultado = efi_card_status((string) ($venda['efi_charge_id'] ?? ''));
            if (!$resultado['ok']) {
                flash('warning', $resultado['message']);
                redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
            }
            $statusInfo = 'Cartão status: ' . $resultado['status'];
            $pago = in_array($resultado['status'], ['approved', 'settled', 'released', 'paid'], true);
        } else {
            flash('info', 'Esta forma de pagamento não é consultada na API. Confirme manualmente.');
            redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
        }

        if ($pago) {
            pdv_confirmar_pagamento(
                $pagamentoId,
                'Confirmação online da venda ' . $venda['numero'] . ' (PDV)',
                'Pagamento confirmado automaticamente pela consulta à Efi. ' . $statusInfo
            );
        } else {
            flash('info', 'Pagamento ainda não confirmado nessa consulta. (' . $statusInfo . ')');
        }
        redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
    }
}

$venda = null;
$pendentes = [];
if ($vendaId > 0) {
    $venda = pdv_venda_com_pendentes($vendaId);
    if ($venda) {
        $pendentes = db_all(
            "SELECT * FROM venda_pagamentos
             WHERE venda_id = :venda_id AND status = 'pendente' AND metodo IN ('pix', 'cartao_credito', 'cartao_debito')
             ORDER BY id",
            ['venda_id' => $vendaId]
        );
    }
}

$pageTitle = 'Confirmar pagamento';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4">
<div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
<div class="col-lg-9">
    <h1 class="h3 section-title">Confirmar pagamento online</h1>
    <?php pdv_portal_tabs('historico'); ?>

    <?php if (!$venda): ?>
        <div class="panel-card bg-white p-4"><p class="text-secondary mb-0">Venda não localizada.</p>
            <a class="btn btn-outline-brand mt-3" href="<?= e(base_url('admin/vendas/historico.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar às vendas</a>
        </div>
    <?php else: ?>
        <div class="panel-card bg-white p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 section-title mb-1"><?= e($venda['numero']) ?></h2>
                    <div class="small text-secondary">
                        <?= e($venda['finalizada_em'] ?? $venda['criado_em']) ?>
                        · Atendente: <?= e($venda['vendedor']) ?>
                        · Cliente: <?= e($venda['cliente'] ?? 'Venda sem cliente') ?>
                    </div>
                </div>
                <span class="badge text-bg-warning fs-6">Pagamento pendente</span>
                <a class="btn btn-outline-brand" href="<?= e(base_url('admin/vendas/historico.php?venda=' . (int) $venda['id'])) ?>"><i class="bi bi-arrow-left"></i> Ver venda</a>
            </div>

            <?php if ($pendentes): ?>
                <?php foreach ($pendentes as $pag): ?>
                    <div class="border rounded-4 p-4 mb-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold"><i class="bi bi-qr-code"></i> <?= e(pdv_payment_label($pag['metodo'])) ?></span>
                            <strong><?= money_br((float) $pag['valor']) ?></strong>
                        </div>

                        <?php if ($pag['metodo'] === 'pix'): ?>
                            <?php if (!empty($venda['pix_qrcode'])): ?>
                                <div class="text-center mb-3">
                                    <img src="data:image/png;base64,<?= e($venda['pix_qrcode']) ?>" alt="QR Code Pix" class="img-fluid" style="max-width:220px;">
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($venda['pix_copiaecola'])): ?>
                                <label class="form-label small mb-1">Pix copia e cola</label>
                                <div class="input-group mb-3">
                                    <input class="form-control form-control-sm" id="pixCopiaECola" readonly value="<?= e($venda['pix_copiaecola']) ?>">
                                    <button type="button" class="btn btn-outline-brand btn-sm" onclick="copyPixCopiaECola()"><i class="bi bi-clipboard"></i> Copiar</button>
                                </div>
                            <?php endif; ?>
                            <div class="small text-secondary mb-2">Confirme o recebimento no app/banco e registre aqui, ou consulte automaticamente na API.</div>
                        <?php else: ?>
                            <div class="small text-secondary mb-2">Cobrança de cartão criada. Confirme a aprovação recebida pela operadora ou consulte na API.</div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="confirmar">
                                <input type="hidden" name="pagamento_id" value="<?= (int) $pag['id'] ?>">
                                <button class="btn btn-brand" data-confirm="Confirmar o recebimento deste pagamento? Ele será lançado no caixa aberto."><i class="bi bi-check2-circle"></i> Confirmar recebimento</button>
                            </form>
                            <?php if (($pag['metodo'] === 'pix' && !empty($venda['pix_txid'])) || ($pag['metodo'] === 'cartao_credito' && !empty($venda['efi_charge_id']))): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="consultar">
                                    <input type="hidden" name="pagamento_id" value="<?= (int) $pag['id'] ?>">
                                    <button class="btn btn-outline-brand"><i class="bi bi-cloud-check"></i> Consultar status na API</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> Nenhum pagamento pendente para esta venda.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div></div></section>

<script>
function copyPixCopiaECola() {
    const input = document.getElementById('pixCopiaECola');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard?.writeText(input.value);
}
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>