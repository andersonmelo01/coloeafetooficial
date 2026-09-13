<?php
require_once __DIR__ . '/_pdv.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';
require_once dirname(__DIR__, 2) . '/includes/EfiService.php';
require_login('admin');

$cart = pdv_cart();
if (!$cart) {
    flash('warning', 'Nenhum item na venda em andamento.');
    redirect('admin/vendas/index.php');
}

$subtotal = pdv_cart_subtotal();
$erroPagamento = '';
$avisoIntegracao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $clienteId = $clienteId > 0 ? $clienteId : null;
    $emailRecibo = trim((string) ($_POST['email_recibo'] ?? ''));
    if ($emailRecibo !== '' && !filter_var($emailRecibo, FILTER_VALIDATE_EMAIL)) {
        $erroPagamento = 'E-mail do recibo inválido.';
    }
    $desconto = max(0.0, (float) str_replace(',', '.', (string) ($_POST['desconto'] ?? '0')));
    $observacao = trim((string) ($_POST['observacao'] ?? ''));
    $metodos = (array) ($_POST['pagamento_metodo'] ?? []);
    $valores = (array) ($_POST['pagamento_valor'] ?? []);
    $parcelasQtd = (array) ($_POST['pagamento_parcelas'] ?? []);
    $vencimentos = (array) ($_POST['pagamento_vencimento'] ?? []);
    $tokens = (array) ($_POST['pagamento_token'] ?? []);

    $desconto = min($desconto, $subtotal);
    $total = round($subtotal - $desconto, 2);

    $rowCount = max(count($metodos), count($valores));
    $pagamentos = [];

    for ($i = 0; $i < $rowCount; $i++) {
        $metodo = (string) ($metodos[$i] ?? '');
        if (!array_key_exists($metodo, pdv_payment_methods())) {
            continue;
        }
        $valor = round(max(0.0, (float) str_replace(',', '.', (string) ($valores[$i] ?? '0'))), 2);
        if ($valor <= 0) {
            continue;
        }
        $pagamentos[] = [
            'metodo' => $metodo,
            'valor' => $valor,
            'parcelas' => max(1, (int) ($parcelasQtd[$i] ?? 1)),
            'vencimento' => (string) ($vencimentos[$i] ?? ''),
            'token' => trim((string) ($tokens[$i] ?? '')),
        ];
    }

    foreach ($pagamentos as $pag) {
        if ($pag['metodo'] === 'a_prazo' && !$clienteId) {
            $erroPagamento = 'A venda a prazo exige a seleção de um cliente cadastrado.';
            break;
        }
    }

    if ($erroPagamento === '') {
        if (!$pagamentos) {
            $erroPagamento = 'Informe ao menos um pagamento válido.';
        } else {
            $soma = round(array_sum(array_column($pagamentos, 'valor')), 2);
            $temPrazo = false;
            foreach ($pagamentos as $pag) {
                if ($pag['metodo'] === 'a_prazo') {
                    $temPrazo = true;
                    break;
                }
            }
            if (!$temPrazo && $soma < $total) {
                $erroPagamento = 'O valor dos pagamentos (' . money_br($soma) . ') é menor que o total da venda (' . money_br($total) . ').';
            }
        }
    }

    if ($erroPagamento === '') {
        $pdo = db();
        $usuarioId = (int) current_user()['id'];
        $caixa = pdv_caixa_aberto();

        try {
            $pdo->beginTransaction();

            foreach ($cart as $item) {
                if (!pdv_stock_control()) {
                    continue;
                }
                $produto = db_one(
                    "SELECT estoque FROM produtos WHERE id = :id FOR UPDATE",
                    ['id' => (int) $item['produto_id']]
                );
                if ($produto && (int) $produto['estoque'] < (int) $item['quantidade']) {
                    throw new RuntimeException('Estoque insuficiente para "' . $item['nome'] . '".');
                }
            }

            $numero = pdv_next_numero();
            $stmt = $pdo->prepare(
                "INSERT INTO vendas (numero, usuario_id, cliente_id, email_recibo, caixa_id, status, subtotal, desconto, total, observacao, finalizada_em)
                 VALUES (:numero, :usuario_id, :cliente_id, :email_recibo, :caixa_id, 'finalizada', :subtotal, :desconto, :total, :observacao, NOW())"
            );
            $stmt->execute([
                'numero' => $numero,
                'usuario_id' => $usuarioId,
                'cliente_id' => $clienteId,
                'email_recibo' => $emailRecibo !== '' ? $emailRecibo : null,
                'caixa_id' => $caixa ? (int) $caixa['id'] : null,
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'total' => $total,
                'observacao' => $observacao !== '' ? $observacao : null,
            ]);
            $vendaId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                "INSERT INTO venda_itens (venda_id, produto_id, nome_produto, quantidade, preco_unitario, total)
                 VALUES (:venda_id, :produto_id, :nome_produto, :quantidade, :preco_unitario, :total)"
            );
            $insertMovimento = $pdo->prepare(
                "INSERT INTO movimentos_estoque (produto_id, usuario_id, tipo, quantidade, saldo_anterior, saldo_posterior, motivo, origem, observacao)
                 VALUES (:produto_id, :usuario_id, 'saida', :quantidade, :saldo_anterior, :saldo_posterior, :motivo, :origem, :observacao)"
            );
            $updateEstoque = $pdo->prepare("UPDATE produtos SET estoque = :estoque WHERE id = :id");

            foreach ($cart as $item) {
                $produtoId = (int) $item['produto_id'];
                $quantidade = (int) $item['quantidade'];
                $unitario = round((float) $item['preco'], 2);
                $itemTotal = round($unitario * $quantidade, 2);

                $insertItem->execute([
                    'venda_id' => $vendaId,
                    'produto_id' => $produtoId,
                    'nome_produto' => (string) $item['nome'],
                    'quantidade' => $quantidade,
                    'preco_unitario' => $unitario,
                    'total' => $itemTotal,
                ]);

                $saldoAtual = (int) (db_one("SELECT estoque FROM produtos WHERE id = :id", ['id' => $produtoId])['estoque'] ?? 0);
                $saldoNovo = max(0, $saldoAtual - $quantidade);
                if (pdv_stock_control()) {
                    $updateEstoque->execute(['estoque' => $saldoNovo, 'id' => $produtoId]);
                    $insertMovimento->execute([
                        'produto_id' => $produtoId,
                        'usuario_id' => $usuarioId,
                        'quantidade' => $quantidade,
                        'saldo_anterior' => $saldoAtual,
                        'saldo_posterior' => $saldoNovo,
                        'motivo' => 'Venda PDV ' . $numero,
                        'origem' => 'pdv',
                        'observacao' => $observacao !== '' ? $observacao : null,
                    ]);
                }
            }

            $insertPagamento = $pdo->prepare(
                "INSERT INTO venda_pagamentos (venda_id, metodo, valor, status, pago_em)
                 VALUES (:venda_id, :metodo, :valor, :status, :pago_em)"
            );
            $insertParcela = $pdo->prepare(
                "INSERT INTO venda_parcelas (venda_id, parcela, vencimento, valor, status, pago_em, pago_metodo)
                 VALUES (:venda_id, :parcela, :vencimento, :valor, :status, :pago_em, :pago_metodo)"
            );
            $insertCaixaMov = $pdo->prepare(
                "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                 VALUES (:caixa_id, :venda_id, 'entrada', :metodo, :valor, :observacao, :usuario_id)"
            );

            $now = date('Y-m-d H:i:s');
            $clienteRow = $clienteId ? db_one("SELECT nome, email, cpf FROM usuarios WHERE id = :id", ['id' => $clienteId]) : null;
            $pixStore = null;
            $integradoPendente = false;
            $efiChargeId = '';
            $efiChargeStatus = '';
            $somaPagos = round(array_sum(array_column($pagamentos, 'valor')), 2);
            $restanteAprazo = round(max(0.0, $total - $somaPagos), 2);

            foreach ($pagamentos as $pag) {
                $metodo = $pag['metodo'];
                $valor = $pag['valor'];

                if ($metodo === 'a_prazo') {
                    $entrada = round($valor, 2);
                    $futuro = $restanteAprazo;
                    $futuras = $futuro > 0 ? max(1, (int) $pag['parcelas']) : 0;
                    $base = $pag['vencimento'] !== '' ? $pag['vencimento'] : date('Y-m-d', strtotime('+30 days'));
                    $contadorParcela = 0;

                    if ($entrada > 0) {
                        $contadorParcela++;
                        $insertParcela->execute([
                            'venda_id' => $vendaId,
                            'parcela' => $contadorParcela,
                            'vencimento' => date('Y-m-d'),
                            'valor' => $entrada,
                            'status' => 'pago',
                            'pago_em' => $now,
                            'pago_metodo' => 'a_prazo',
                        ]);
                        $insertPagamento->execute([
                            'venda_id' => $vendaId,
                            'metodo' => 'a_prazo',
                            'valor' => $entrada,
                            'status' => 'pago',
                            'pago_em' => $now,
                        ]);

                        if ($caixa) {
                            $insertCaixaMov->execute([
                                'caixa_id' => (int) $caixa['id'],
                                'venda_id' => $vendaId,
                                'metodo' => 'a_prazo',
                                'valor' => $entrada,
                                'observacao' => 'Entrada de venda a prazo (PDV ' . $numero . ')',
                                'usuario_id' => $usuarioId,
                            ]);
                        }
                    }

                    if ($futuras > 0) {
                        $valorParcela = round($futuro / $futuras, 2);
                        $acumulado = 0.0;
                        for ($f = 1; $f <= $futuras; $f++) {
                            $contadorParcela++;
                            $valorFuturo = $valorParcela;
                            if ($f === $futuras) {
                                $valorFuturo = round($futuro - $acumulado, 2);
                            }
                            $acumulado += $valorFuturo;
                            $insertParcela->execute([
                                'venda_id' => $vendaId,
                                'parcela' => $contadorParcela,
                                'vencimento' => date('Y-m-d', strtotime($base . ' +' . ($f - 1) . ' months')),
                                'valor' => $valorFuturo,
                                'status' => 'pendente',
                                'pago_em' => null,
                                'pago_metodo' => null,
                            ]);
                        }
                    }
                    continue;
                }

                $integracao = null;
                if ($metodo === 'pix') {
                    $ready = efi_can_charge_method('pix_qrcode');
                    if ($ready['ok']) {
                        $integracao = efi_charge_pix($valor, 'PDV' . $vendaId . bin2hex(random_bytes(3)), [
                            'name' => (string) ($clienteRow['nome'] ?? '') !== '' ? (string) $clienteRow['nome'] : 'Cliente Colo e Afeto',
                            'email' => (string) ($clienteRow['email'] ?? ''),
                            'cpf' => (string) ($clienteRow['cpf'] ?? ''),
                        ]);
                    }
                } elseif ($metodo === 'cartao_credito' && $pag['token'] !== '') {
                    $ready = efi_can_charge_method('cartao_credito');
                    if ($ready['ok']) {
                        $integracao = efi_charge_card($valor, $pag['token'], $pag['parcelas'], [
                            'name' => (string) ($clienteRow['nome'] ?? '') !== '' ? (string) $clienteRow['nome'] : 'Cliente Colo e Afeto',
                            'email' => (string) ($clienteRow['email'] ?? '') !== '' ? (string) $clienteRow['email'] : 'nao-informado@coloafeto.local',
                        ]);
                    }
                }

                if ($integracao && $integracao['ok']) {
                    $insertPagamento->execute([
                        'venda_id' => $vendaId,
                        'metodo' => $metodo,
                        'valor' => $valor,
                        'status' => 'pendente',
                        'pago_em' => null,
                    ]);

                    if ((string) ($integracao['charge_id'] ?? '') !== '') {
                        $efiChargeId = (string) $integracao['charge_id'];
                        $efiChargeStatus = (string) ($integracao['status'] ?? '');
                    }

                    if ($metodo === 'pix') {
                        $pixStore = [
                            'txid' => (string) ($integracao['txid'] ?? ''),
                            'copiaecola' => (string) ($integracao['copiaecola'] ?? ''),
                            'qrcode' => (string) ($integracao['qrcode_base64'] ?? ''),
                        ];
                    }
                    $integradoPendente = true;
                    continue;
                }

                if ($integracao && !$integracao['ok']) {
                    $avisoIntegracao = $metodo === 'pix'
                        ? 'Pix automático indisponível: ' . $integracao['message'] . ' Pagamento registrado como manual.'
                        : 'Cartão online indisponível: ' . $integracao['message'] . ' Pagamento registrado como manual.';
                }

                $insertPagamento->execute([
                    'venda_id' => $vendaId,
                    'metodo' => $metodo,
                    'valor' => $valor,
                    'status' => 'pago',
                    'pago_em' => $now,
                ]);

                if ($caixa) {
                    $insertCaixaMov->execute([
                        'caixa_id' => (int) $caixa['id'],
                        'venda_id' => $vendaId,
                        'metodo' => $metodo,
                        'valor' => $valor,
                        'observacao' => 'Venda PDV ' . $numero,
                        'usuario_id' => $usuarioId,
                    ]);
                }
            }

            if ($pixStore) {
                $pdo->prepare(
                    "UPDATE vendas SET pix_txid = :pix_txid, pix_copiaecola = :pix_copiaecola, pix_qrcode = :pix_qrcode WHERE id = :id"
                )->execute([
                    'pix_txid' => $pixStore['txid'],
                    'pix_copiaecola' => $pixStore['copiaecola'],
                    'pix_qrcode' => $pixStore['qrcode'],
                    'id' => $vendaId,
                ]);
            }

            if ($efiChargeId !== '') {
                $pdo->prepare(
                    "UPDATE vendas SET efi_charge_id = :efi_charge_id, efi_charge_status = :efi_charge_status WHERE id = :id"
                )->execute([
                    'efi_charge_id' => $efiChargeId,
                    'efi_charge_status' => $efiChargeStatus !== '' ? $efiChargeStatus : null,
                    'id' => $vendaId,
                ]);
            }

            pdv_sync_venda_status($vendaId);
            pdv_ensure_cupom_no($vendaId);
            if (pdv_fiscal_mode()) {
                pdv_ensure_nfce_chave($vendaId);
            }

            $pdo->commit();
            pdv_cart_clear();
            if ($integradoPendente) {
                flash('success', "Venda {$numero} concluída. Há pagamento Pix/cartão aguardando confirmação online.");
                redirect('admin/vendas/confirmar.php?venda=' . $vendaId);
            }
            if ($avisoIntegracao !== '') {
                flash('warning', $avisoIntegracao);
            }
            if ($restanteAprazo > 0) {
                flash('warning', "Venda {$numero} registrada como pendente. Saldo a receber: " . money_br($restanteAprazo) . '.');
            } else {
                flash('success', "Venda {$numero} concluída. Total: " . money_br($total) . '.');
            }
            redirect('admin/vendas/historico.php?venda=' . $vendaId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroPagamento = 'Não foi possível concluir a venda: ' . $e->getMessage();
        }
    }
}

$clientes = pdv_clientes();
$caixa = pdv_caixa_aberto();

$pageTitle = 'Finalizar venda';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4">
<div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
<div class="col-lg-9">
    <h1 class="h3 section-title">Finalizar venda</h1>
    <?php pdv_portal_tabs('index'); ?>

    <?php if (!$caixa): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Não há caixa aberto. A venda poderá ser concluída, mas os pagamentos à vista não serão lançados no caixa.</div>
    <?php endif; ?>

    <?php if ($erroPagamento !== ''): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= e($erroPagamento) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel-card bg-white p-4">
                    <h2 class="h5 section-title">Itens da venda</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Item</th><th class="text-end">Qtd</th><th class="text-end">Preço</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($cart as $item): ?>
                                <tr>
                                    <td><?= e($item['nome']) ?></td>
                                    <td class="text-end"><?= (int) $item['quantidade'] ?></td>
                                    <td class="text-end"><?= money_br((float) $item['preco']) ?></td>
                                    <td class="text-end"><?= money_br((float) $item['preco'] * (int) $item['quantidade']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between"><span>Subtotal</span><strong><?= money_br($subtotal) ?></strong></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="col-form-label" for="desconto">Desconto (R$)</label>
                        <input class="form-control text-end" style="max-width: 140px" id="desconto" name="desconto" type="number" step="0.01" min="0" value="<?= e((string) ($_POST['desconto'] ?? '0')) ?>">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fs-5 fw-bold">Total a receber</span>
                        <strong class="fs-4" id="totalAReceber"><?= money_br(round($subtotal - (float) str_replace(',', '.', (string) ($_POST['desconto'] ?? '0')), 2)) ?></strong>
                    </div>

                    <label class="form-label mt-3">Cliente (obrigatório para venda a prazo)</label>
                    <select class="form-select" name="cliente_id">
                        <option value="">Venda sem cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= (int) $cliente['id'] ?>" <?= ((int) ($_POST['cliente_id'] ?? 0) === (int) $cliente['id']) ? 'selected' : '' ?>><?= e(pdv_cliente_label($cliente)) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label mt-3">E-mail do recibo (envio do cupom)</label>
                    <input class="form-control" name="email_recibo" type="email" placeholder="opcional — para enviar o cupom" value="<?= e((string) ($_POST['email_recibo'] ?? '')) ?>">
                    <div class="form-text">Usado quando a venda é sem cliente ou não for possível enviar ao cliente cadastrado.</div>

                    <label class="form-label mt-3">Observação</label>
                    <textarea class="form-control" name="observacao" rows="2" placeholder="Opcional"><?= e((string) ($_POST['observacao'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="panel-card bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 section-title mb-0">Pagamento</h2>
                        <button type="button" class="btn btn-outline-brand btn-sm" id="btnAddPagamento"><i class="bi bi-plus-lg"></i> Forma adicional</button>
                    </div>

                    <div id="pagamentos"></div>
                    <template id="tplPagamento">
                        <div class="row g-2 mb-2 payment-row">
                            <div class="col-5">
                                <select class="form-select payment-metodo" name="pagamento_metodo[]">
                                    <?php foreach (pdv_payment_methods() as $slug => $label): ?>
                                        <option value="<?= e($slug) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="payment-prazo-hint text-danger" style="display:none">Informe aqui o valor pago agora (entrada/1ª parcela). O restante vira parcela(s) automaticamente — indique quantas ao lado ("parcelas futuras").</small>
                            </div>
                            <div class="col-3">
                                <input class="form-control payment-valor" name="pagamento_valor[]" type="number" step="0.01" min="0" placeholder="R$ 0,00">
                            </div>
                            <div class="col-3 payment-parcelas" style="display:none">
                                <input class="form-control" name="pagamento_parcelas[]" type="number" min="1" value="1" title="Quantidade de parcelas">
                            </div>
                            <div class="col-3 payment-vencimento" style="display:none">
                                <input class="form-control" name="pagamento_vencimento[]" type="date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>" title="Primeiro vencimento">
                            </div>
                            <div class="col-12 payment-token" style="display:none">
                                <input class="form-control" name="pagamento_token[]" placeholder="Payment token do cartão (opcional para integração Efi)">
                            </div>
                            <div class="col-1">
                                <button type="button" class="btn btn-sm btn-outline-danger payment-remove" title="Remover forma"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </template>

                    <div class="d-flex justify-content-between border-top pt-3">
                        <span class="fs-5">Total dos pagamentos</span>
                        <strong class="fs-4" id="totalPagamentos"><?= money_br(0.0) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span>Troco estimado</span>
                        <strong class="text-success" id="trocoEstimado"><?= money_br(0.0) ?></strong>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/vendas/index.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
                        <button class="btn btn-brand btn-lg flex-grow-1" type="submit"><i class="bi bi-check2-circle"></i> Confirmar venda</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
</div></div></section>

<script>
(function () {
    const tpl = document.getElementById('tplPagamento');
    const container = document.getElementById('pagamentos');

    function moneyToFloat(text) {
        let s = String(text).trim();
        if (!s) return 0;
        const negative = s.startsWith('-');
        s = s.replace(/[^\d.,]/g, '');
        if (s.includes(',') && s.includes('.')) {
            const lastComma = s.lastIndexOf(',');
            const lastDot = s.lastIndexOf('.');
            s = lastComma > lastDot ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
        } else if (s.includes(',')) {
            s = s.replace(/\./g, '').replace(',', '.');
        }
        const n = parseFloat(s);
        return (isNaN(n) ? 0 : n) * (negative ? -1 : 1);
    }

    function round2(value) {
        return Math.round(value * 100) / 100;
    }

    function toggleParcelas(row) {
        const metodo = row.querySelector('.payment-metodo').value;
        const isPrazo = metodo === 'a_prazo';
        const isCard = metodo === 'cartao_credito';
        const parcelasEl = row.querySelector('.payment-parcelas');
        const vencimentoEl = row.querySelector('.payment-vencimento');
        const tokenEl = row.querySelector('.payment-token');
        const prazoEl = row.querySelector('.payment-prazo-hint');
        parcelasEl.style.display = isPrazo || isCard ? '' : 'none';
        vencimentoEl.style.display = isPrazo ? '' : 'none';
        tokenEl.style.display = isCard ? '' : 'none';
        prazoEl.style.display = isPrazo ? '' : 'none';
        if (isPrazo && parcelasEl.querySelector('input')) {
            const parcelasInput = parcelasEl.querySelector('input');
            parcelasInput.placeholder = 'parcelas futuras';
            if (!parcelasInput.value || Number(parcelasInput.value) < 1) {
                parcelasInput.value = '1';
            }
        }
    }

    function addRow(metodo, valor, parcelas, vencimento, token) {
        const frag = tpl.content.cloneNode(true);
        const dom = frag.querySelector('.payment-row');
        if (metodo) {
            dom.querySelector('.payment-metodo').value = metodo;
        }
        if (valor !== undefined && valor !== null && valor !== '') {
            dom.querySelector('.payment-valor').value = valor;
        }
        if (parcelas) {
            dom.querySelector('.payment-parcelas input').value = parcelas;
        }
        if (vencimento && vencimento !== '') {
            dom.querySelector('.payment-vencimento input').value = vencimento;
        }
        if (token) {
            dom.querySelector('.payment-token input').value = token;
        }
        dom.querySelector('.payment-metodo').addEventListener('change', () => toggleParcelas(dom));
        dom.querySelector('.payment-remove').addEventListener('click', () => dom.remove());
        dom.querySelector('.payment-valor').addEventListener('input', updateTotals);
        container.appendChild(dom);
        toggleParcelas(dom);
        updateTotals();
    }

    function updateTotals() {
        const aReceber = moneyToFloat(document.getElementById('totalAReceber').textContent);
        let soma = 0;
        container.querySelectorAll('.payment-row').forEach((row) => {
            soma += moneyToFloat(row.querySelector('.payment-valor').value);
        });
        document.getElementById('totalPagamentos').textContent = 'R$ ' + soma.toFixed(2).replace('.', ',');
        const troco = soma - aReceber;
        const trocoEl = document.getElementById('trocoEstimado');
        trocoEl.textContent = 'R$ ' + troco.toFixed(2).replace('.', ',');
        trocoEl.classList.toggle('text-success', troco >= 0);
        trocoEl.classList.toggle('text-danger', troco < 0);
    }

    document.getElementById('desconto').addEventListener('input', () => {
        const subtotal = <?= json_encode(round($subtotal, 2)) ?>;
        const desconto = Math.min(Math.max(parseFloat(document.getElementById('desconto').value) || 0, 0), subtotal);
        document.getElementById('totalAReceber').textContent = 'R$ ' + (subtotal - desconto).toFixed(2).replace('.', ',');
        updateTotals();
    });

    document.getElementById('btnAddPagamento').addEventListener('click', () => addRow());

    document.getElementById('pagamentos').closest('form').addEventListener('submit', (event) => {
        const aReceber = moneyToFloat(document.getElementById('totalAReceber').textContent);
        let soma = 0;
        const rows = container.querySelectorAll('.payment-row');
        rows.forEach((row) => {
            soma += moneyToFloat(row.querySelector('.payment-valor').value);
        });
        let faltante = round2(aReceber - soma);
        if (faltante <= 0) return;
        rows.forEach((row) => {
            if (round2(faltante) <= 0) return;
            if (row.querySelector('.payment-metodo').value === 'a_prazo') return;
            const input = row.querySelector('.payment-valor');
            if (moneyToFloat(input.value) <= 0) {
                input.value = round2(faltante).toFixed(2);
                faltante = 0;
            }
        });
        updateTotals();
    });

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($metodos, $valores)): ?>
        <?php foreach ($metodos as $i => $metodo): ?>
            addRow(<?= json_encode((string) $metodo) ?>, <?= json_encode((string) ($valores[$i] ?? '')) ?>, <?= json_encode((string) ($parcelasQtd[$i] ?? '1')) ?>, <?= json_encode((string) ($vencimentos[$i] ?? '')) ?>, <?= json_encode((string) ($tokens[$i] ?? '')) ?>);
        <?php endforeach; ?>
    <?php else: ?>
        addRow('dinheiro', '');
    <?php endif; ?>
    updateTotals();
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>