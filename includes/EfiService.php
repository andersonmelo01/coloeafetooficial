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
