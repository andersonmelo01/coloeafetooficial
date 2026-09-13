<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/EmailService.php';

function efi_sdk_available(): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    return class_exists('Efi\\EfiPay') || class_exists('Gerencianet\\Gerencianet');
}

function efi_api(): ?object
{
    if (!efi_sdk_available()) {
        return null;
    }

    $options = [
        'client_id' => (string) app_config('efi.client_id', ''),
        'client_secret' => (string) app_config('efi.client_secret', ''),
        'certificate' => (string) app_config('efi.certificado', ''),
        'sandbox' => app_config('efi.ambiente', 'producao') === 'sandbox',
    ];

    try {
        if (class_exists('Efi\\EfiPay')) {
            return new \Efi\EfiPay($options);
        }
        return new \Gerencianet\Gerencianet($options);
    } catch (Throwable $e) {
        return null;
    }
}

function efi_charge_pix(float $amount, string $txid, array $debtor = []): array
{
    $ready = efi_can_charge_method('pix_qrcode');
    if (!$ready['ok']) {
        return ['ok' => false, 'message' => $ready['message']];
    }

    $api = efi_api();
    if (!$api) {
        return ['ok' => false, 'message' => 'Não foi possível instanciar o SDK Efi.'];
    }

    $payload = [
        'calendar' => ['expiration' => 3600],
        'value' => ['original' => number_format(max(0.01, $amount), 2, '.', '')],
        'key' => (string) app_config('efi.pix_chave', ''),
    ];
    if (trim((string) ($debtor['name'] ?? '')) !== '') {
        $payload['debtor']['name'] = (string) $debtor['name'];
        if (trim((string) ($debtor['cpf'] ?? '')) !== '') {
            $payload['debtor']['cpf'] = preg_replace('/\D/', '', (string) $debtor['cpf']);
        }
        if (trim((string) ($debtor['email'] ?? '')) !== '') {
            $payload['debtor']['email'] = (string) $debtor['email'];
        }
    }

    try {
        $charge = $api->pixCreateImmediateCharge([], $payload);
        $locId = (int) ($charge['loc']['id'] ?? 0);
        $qrcode = $locId > 0 ? $api->pixGenerateQRCode(['id' => $locId]) : [];

        return [
            'ok' => true,
            'message' => 'Cobrança Pix criada.',
            'txid' => (string) ($charge['txid'] ?? $txid),
            'charge_id' => (string) ($charge['loc']['id'] ?? ''),
            'copiaecola' => (string) ($qrcode['qrcode'] ?? ''),
            'qrcode_base64' => (string) ($qrcode['imagemQrcode'] ?? ''),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Falha ao criar cobrança Pix: ' . $e->getMessage()];
    }
}

function efi_charge_card(float $amount, string $paymentToken, int $parcelas = 1, array $customer = []): array
{
    $ready = efi_can_charge_method('cartao_credito');
    if (!$ready['ok']) {
        return ['ok' => false, 'message' => $ready['message']];
    }

    if (trim($paymentToken) === '') {
        return ['ok' => false, 'message' => 'Payment token de cartão não informado.'];
    }

    $api = efi_api();
    if (!$api) {
        return ['ok' => false, 'message' => 'Não foi possível instanciar o SDK Efi.'];
    }

    $parcelas = max(1, min(12, (int) $parcelas));
    $parcelaValor = round($amount / $parcelas, 2);
    if ((float) number_format($parcelaValor * $parcelas, 2, '.', '') !== (float) number_format($amount, 2, '.', '')) {
        $parcelaValor = round($amount - ($parcelaValor * ($parcelas - 1)), 2);
    }

    $items = [[
        'name' => 'Venda presencial PDV',
        'value' => (int) (round($amount, 2) * 100),
        'amount' => 1,
    ]];
    $customer = [
        'name' => (string) ($customer['name'] ?? 'Cliente Colo e Afeto'),
        'email' => (string) ($customer['email'] ?? 'nao-informado@coloafeto.local'),
    ];

    try {
        $body = [
            'items' => $items,
            'payment' => [
                'credit_card' => [
                    'installments' => $parcelas,
                    'payment_token' => $paymentToken,
                    'billing_address' => [],
                    'customer' => $customer,
                ],
            ],
        ];
        $charge = $api->createCharge([], $body);
        $chargeId = (string) ($charge['data']['charge_id'] ?? '');
        $chargeStatus = (string) ($charge['data']['status'] ?? '');

        return [
            'ok' => true,
            'message' => 'Cobrança de cartão criada.',
            'charge_id' => $chargeId,
            'status' => $chargeStatus,
            'link' => (string) ($charge['data']['payment_url'] ?? ''),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Falha ao criar cobrança de cartão: ' . $e->getMessage()];
    }
}

function efi_pix_status(string $txid): array
{
    if (trim($txid) === '') {
        return ['ok' => false, 'message' => 'Cobrança sem txid para consulta.'];
    }
    $api = efi_api();
    if (!$api) {
        return ['ok' => false, 'message' => 'Não foi possível instanciar o SDK Efi.'];
    }

    try {
        $result = method_exists($api, 'pixDetailCharge')
            ? $api->pixDetailCharge(['txid' => $txid])
            : $api->pixDetail($txid);
        $status = strtoupper((string) ($result['status'] ?? ''));
        return ['ok' => true, 'status' => $status, 'message' => 'Status consultado.', 'detail' => $result];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Falha ao consultar Pix: ' . $e->getMessage()];
    }
}

function efi_card_status(string $chargeId): array
{
    if (trim($chargeId) === '') {
        return ['ok' => false, 'message' => 'Cobrança sem identificador (charge_id).'];
    }
    $api = efi_api();
    if (!$api) {
        return ['ok' => false, 'message' => 'Não foi possível instanciar o SDK Efi.'];
    }

    try {
        $result = method_exists($api, 'detailCharge')
            ? $api->detailCharge(['id' => $chargeId])
            : $api->chargeDetail($chargeId);
        $status = strtolower((string) ($result['data']['status'] ?? ''));
        $link = (string) ($result['data']['payment_url'] ?? '');
        return ['ok' => true, 'status' => $status, 'message' => 'Status consultado.', 'detail' => $result, 'link' => $link];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Falha ao consultar cobrança: ' . $e->getMessage()];
    }
}

function pdv_efi_paid_statuses(): array
{
    return ['paid', 'approved', 'settled', 'released', 'authorized', 'new', 'processing', 'CONCLUIDA'];
}

function pdv_apply_efi_payment(int $vendaId, bool $isPaid, array $payload = []): void
{
    if (!$isPaid) {
        return;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $pagamentos = db_all(
            "SELECT * FROM venda_pagamentos WHERE venda_id = :venda_id AND status = 'pendente' ORDER BY id",
            ['venda_id' => $vendaId]
        );
        $aplicados = 0;
        foreach ($pagamentos as $pagamento) {
            if ($pagamento['metodo'] !== 'pix') {
                continue;
            }
            $jaConfirmado = (int) (db_one(
                "SELECT COUNT(*) AS total FROM caixa_movimentos WHERE venda_id = :venda_id AND metodo = 'pix' AND tipo = 'entrada'",
                ['venda_id' => $vendaId]
            )['total'] ?? 0);

            $pdo->prepare("UPDATE venda_pagamentos SET status = 'pago', pago_em = NOW() WHERE id = :id")->execute(['id' => (int) $pagamento['id']]);

            if ($jaConfirmado === 0) {
                $caixa = pdv_caixa_aberto();
                if ($caixa) {
                    $pdo->prepare(
                        "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                         VALUES (:caixa_id, :venda_id, 'entrada', 'pix', :valor, :observacao, :usuario_id)"
                    )->execute([
                        'caixa_id' => (int) $caixa['id'],
                        'venda_id' => $vendaId,
                        'valor' => (float) $pagamento['valor'],
                        'observacao' => 'Pix confirmado automaticamente pela Efi (webhook)',
                        'usuario_id' => null,
                    ]);
                }
            }
            $aplicados++;
        }

        if ($aplicados > 0) {
            pdv_sync_venda_status($vendaId);
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function efi_can_charge(): array
{
    if (!efi_enabled()) {
        return ['ok' => false, 'message' => 'Integracao Efi Bank desabilitada.'];
    }

    foreach (['efi.client_id', 'efi.client_secret', 'efi.certificado'] as $key) {
        if (trim((string) app_config($key, '')) === '') {
            return ['ok' => false, 'message' => 'Configuracao Efi pendente: ' . $key . '.'];
        }
    }

    if (!efi_sdk_available()) {
        return ['ok' => false, 'message' => 'SDK Efi nao instalado. Execute composer install.'];
    }

    return ['ok' => true, 'message' => 'Integracao Efi pronta para cobranca.'];
}

function efi_can_charge_method(string $method): array
{
    $base = efi_can_charge();
    if (!$base['ok']) {
        return $base;
    }

    if ($method === 'pix_qrcode') {
        if (!efi_pix_enabled()) {
            return ['ok' => false, 'message' => 'Pix Efi desabilitado nas configuracoes.'];
        }
        if (trim((string) app_config('efi.pix_chave', '')) === '') {
            return ['ok' => false, 'message' => 'Chave Pix Efi nao configurada.'];
        }
    }

    if ($method === 'cartao_credito' || $method === 'credit_card') {
        if (!efi_card_enabled()) {
            return ['ok' => false, 'message' => 'Cartao de credito Efi desabilitado nas configuracoes.'];
        }
        if (trim((string) app_config('efi.payee_code', '')) === '') {
            return ['ok' => false, 'message' => 'Identificador de conta/payee_code Efi nao configurado.'];
        }
    }

    return ['ok' => true, 'message' => 'Metodo de pagamento Efi pronto.'];
}

function efi_prepare_payment_payload(array $pedido, string $method): array
{
    return [
        'method' => $method,
        'environment' => app_config('efi.ambiente', 'sandbox'),
        'order_id' => (int) $pedido['id'],
        'amount' => (float) $pedido['total'],
        'pix_key' => app_config('efi.pix_chave', ''),
        'payee_code' => app_config('efi.payee_code', ''),
        'webhook_url' => app_config('efi.webhook_url', ''),
        'webhook_token' => app_config('efi.webhook_token', ''),
        'note' => 'Payload preparado para SDK Efi. Cartao exige payment_token gerado no front-end com payee_code.',
    ];
}

function efi_apply_paid_order(int $pedidoId, string $providerStatus, array $payload = []): void
{
    $paidStatuses = ['paid', 'approved', 'settled', 'CONCLUIDA', 'confirmado', 'pago'];
    $isPaid = in_array($providerStatus, $paidStatuses, true);

    db()->prepare(
        "UPDATE pedidos
         SET pagamento_status = :pagamento_status,
             status = CASE WHEN :is_paid = 1 THEN 'pago' ELSE status END,
             pagamento_payload = :payload
         WHERE id = :id"
    )->execute([
        'id' => $pedidoId,
        'pagamento_status' => $isPaid ? 'pago' : strtolower($providerStatus),
        'is_paid' => $isPaid ? 1 : 0,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    if ($isPaid) {
        db()->prepare("INSERT INTO pagamentos (pedido_id, provedor, metodo, status, transacao_id, valor, pago_em) SELECT id, 'efi', forma_pagamento, 'pago', :txid, total, NOW() FROM pedidos WHERE id = :id")->execute([
            'id' => $pedidoId,
            'txid' => (string) ($payload['txid'] ?? $payload['charge_id'] ?? $payload['id'] ?? ''),
        ]);
        pedido_send_status_email($pedidoId, 'pago', 'Pagamento confirmado automaticamente pela Efi Bank.');
    }
}
