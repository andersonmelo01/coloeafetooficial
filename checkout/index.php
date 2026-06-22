<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/EmailService.php';
require_login('cliente');

if (!loja_vendas_enabled()) {
    flash('warning', loja_catalog_message());
    redirect('loja/index.php');
}

if (!cart_items()) {
    flash('warning', 'Adicione produtos ao carrinho antes de finalizar.');
    redirect('loja/index.php');
}

$endereco = db_one("SELECT * FROM enderecos WHERE usuario_id = :id AND principal = 1", ['id' => current_user()['id']]);
$paymentOptions = [
    'boleto' => 'Boleto',
];
if (efi_pix_enabled()) {
    $paymentOptions = ['pix_qrcode' => 'PIX com QR Code Efi Bank'] + $paymentOptions;
}
if (efi_card_enabled()) {
    $paymentOptions = ['cartao_credito' => 'Cartao de credito Efi Bank'] + $paymentOptions;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    try {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, status, fiscal_status, subtotal, frete, total, forma_pagamento, pagamento_payload) VALUES (:usuario_id, 'novo', 'pendente', :subtotal, 0, :total, :forma, :pagamento_payload)");
        $stmt->execute([
            'usuario_id' => current_user()['id'],
            'subtotal' => cart_total(),
            'total' => cart_total(),
            'forma' => $_POST['forma_pagamento'],
            'pagamento_payload' => json_encode([
                'payment_token' => $_POST['payment_token'] ?? '',
                'card_mask' => $_POST['card_mask'] ?? '',
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $pedidoId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO pedido_itens
             (pedido_id, produto_id, nome_produto, ncm, cfop, unidade, cst_ibs_cbs, cclass_trib_ibs_cbs,
              aliquota_ibs_uf, aliquota_ibs_municipal, aliquota_cbs, cst_is, cclass_trib_is, aliquota_is,
              quantidade, preco_unitario, total)
             VALUES
             (:pedido_id, :produto_id, :nome, :ncm, :cfop, :unidade, :cst_ibs_cbs, :cclass_trib_ibs_cbs,
              :aliquota_ibs_uf, :aliquota_ibs_municipal, :aliquota_cbs, :cst_is, :cclass_trib_is, :aliquota_is,
              :quantidade, :preco, :total)"
        );
        foreach (cart_items() as $item) {
            $produtoFiscal = db_one(
                "SELECT ncm, cfop, unidade, cst_ibs_cbs, cclass_trib_ibs_cbs, aliquota_ibs_uf,
                        aliquota_ibs_municipal, aliquota_cbs, cst_is, cclass_trib_is, aliquota_is
                 FROM produtos
                 WHERE id = :id",
                ['id' => (int) $item['id']]
            ) ?? [];
            $stmt->execute([
                'pedido_id' => $pedidoId,
                'produto_id' => $item['id'],
                'nome' => $item['nome'],
                'ncm' => $produtoFiscal['ncm'] ?? null,
                'cfop' => $produtoFiscal['cfop'] ?? null,
                'unidade' => $produtoFiscal['unidade'] ?? null,
                'cst_ibs_cbs' => $produtoFiscal['cst_ibs_cbs'] ?? null,
                'cclass_trib_ibs_cbs' => $produtoFiscal['cclass_trib_ibs_cbs'] ?? null,
                'aliquota_ibs_uf' => $produtoFiscal['aliquota_ibs_uf'] ?? 0,
                'aliquota_ibs_municipal' => $produtoFiscal['aliquota_ibs_municipal'] ?? 0,
                'aliquota_cbs' => $produtoFiscal['aliquota_cbs'] ?? 0,
                'cst_is' => $produtoFiscal['cst_is'] ?? null,
                'cclass_trib_is' => $produtoFiscal['cclass_trib_is'] ?? null,
                'aliquota_is' => $produtoFiscal['aliquota_is'] ?? 0,
                'quantidade' => $item['quantidade'],
                'preco' => $item['preco'],
                'total' => $item['preco'] * $item['quantidade'],
            ]);
        }

        $pdo->commit();
        $_SESSION['carrinho'] = [];
        pedido_send_confirmation_email($pedidoId);
        flash('success', 'Pedido recebido. Ele sera revisado pelo gestor antes da confirmacao final.');
        redirect('cliente/pedidos.php');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Nao foi possivel finalizar. Verifique o banco de dados.');
        redirect('checkout/index.php');
    }
}

$pageTitle = 'Checkout';
$active = 'loja';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="panel-card bg-white p-4">
                    <h1 class="h3 section-title">Checkout</h1>
                    <p class="text-secondary">Confirme os dados de entrega e escolha a forma de pagamento. O pedido entra como novo e sera confirmado pelo gestor apos revisao.</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-4 p-3 bg-light rounded-2">
                            <strong>Endereco principal</strong>
                            <p class="mb-0 text-secondary">
                                <?= $endereco ? e($endereco['logradouro'] . ', ' . $endereco['numero'] . ' - ' . $endereco['cidade'] . '/' . $endereco['uf']) : 'Nenhum endereco encontrado no cadastro.' ?>
                            </p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Forma de pagamento</label>
                            <select class="form-select" name="forma_pagamento" id="formaPagamento" required>
                                <?php foreach ($paymentOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="cardTokenBox" style="display:none">
                            <label class="form-label">Payment token do cartao</label>
                            <input class="form-control" name="payment_token" placeholder="Gerado pela biblioteca JS da Efi com o payee_code">
                            <input type="hidden" name="card_mask" value="">
                            <div class="form-text">Em producao, o token deve ser gerado no navegador com o Identificador de conta/payee_code da Efi. O sistema nao deve armazenar numero de cartao.</div>
                        </div>
                        <button class="btn btn-brand" type="submit">Confirmar pedido</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="panel-card bg-white p-4">
                    <h2 class="h5">Resumo do pedido</h2>
                    <?php foreach (cart_items() as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2 small">
                            <span><?= e($item['nome']) ?> x<?= (int) $item['quantidade'] ?></span>
                            <strong><?= money_br($item['preco'] * $item['quantidade']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between pt-3 fs-5">
                        <span>Total</span><strong><?= money_br(cart_total()) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
