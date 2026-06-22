<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function email_phpmailer_available(): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

function email_log(?int $pedidoId, string $toEmail, string $toName, string $subject, string $status, ?string $error = null): void
{
    try {
        db()->prepare(
            "INSERT INTO emails_envios (pedido_id, destinatario_email, destinatario_nome, assunto, status, erro)
             VALUES (:pedido_id, :email, :nome, :assunto, :status, :erro)"
        )->execute([
            'pedido_id' => $pedidoId,
            'email' => $toEmail,
            'nome' => $toName,
            'assunto' => $subject,
            'status' => $status,
            'erro' => $error,
        ]);
    } catch (Throwable $e) {
        // E-mail nunca deve impedir a operacao principal do pedido.
    }
}

function email_send(?int $pedidoId, string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool
{
    if (app_config('email.habilitado', '0') !== '1') {
        email_log($pedidoId, $toEmail, $toName, $subject, 'ignorado', 'Envio de e-mail desabilitado nas configuracoes.');
        return false;
    }

    if (!email_phpmailer_available()) {
        email_log($pedidoId, $toEmail, $toName, $subject, 'erro', 'PHPMailer nao instalado. Execute composer install.');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $host = trim((string) app_config('email.smtp_host', ''));
        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) app_config('email.smtp_port', '587');
            $mail->SMTPAuth = trim((string) app_config('email.smtp_usuario', '')) !== '';
            $mail->Username = (string) app_config('email.smtp_usuario', '');
            $mail->Password = (string) app_config('email.smtp_senha', '');
            $secure = trim((string) app_config('email.smtp_secure', 'tls'));
            if ($secure !== '' && $secure !== 'none') {
                $mail->SMTPSecure = $secure;
            }
        } else {
            $mail->isMail();
        }

        $fromEmail = trim((string) app_config('email.remetente_email', ''));
        if ($fromEmail === '') {
            $fromEmail = 'nao-responda@coloafeto.local';
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, (string) app_config('email.remetente_nome', 'Colo e Afeto'));
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text !== '' ? $text : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        $mail->send();
        email_log($pedidoId, $toEmail, $toName, $subject, 'enviado');
        return true;
    } catch (Throwable $e) {
        email_log($pedidoId, $toEmail, $toName, $subject, 'erro', $e->getMessage());
        return false;
    }
}

function pedido_status_labels(): array
{
    return [
        'novo' => 'Novo',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'pago' => 'Pago',
        'separacao' => 'Em separacao',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];
}

function pedido_status_label(string $status): string
{
    return pedido_status_labels()[$status] ?? $status;
}

function pedido_email_context(int $pedidoId): ?array
{
    $pedido = db_one(
        "SELECT p.*, u.nome AS cliente, u.email,
                e.cep, e.logradouro, e.numero, e.complemento, e.bairro, e.cidade, e.uf
         FROM pedidos p
         JOIN usuarios u ON u.id = p.usuario_id
         LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
         WHERE p.id = :id",
        ['id' => $pedidoId]
    );

    if (!$pedido) {
        return null;
    }

    $pedido['itens'] = db_all("SELECT * FROM pedido_itens WHERE pedido_id = :id", ['id' => $pedidoId]);
    return $pedido;
}

function pedido_send_confirmation_email(int $pedidoId): void
{
    $pedido = pedido_email_context($pedidoId);
    if (!$pedido) {
        return;
    }

    $items = '';
    foreach ($pedido['itens'] as $item) {
        $items .= '<li>' . e($item['nome_produto']) . ' x' . (int) $item['quantidade'] . ' - ' . money_br((float) $item['total']) . '</li>';
    }

    $html = '<h2>Pedido #' . (int) $pedido['id'] . ' recebido</h2>'
        . '<p>Ola, ' . e($pedido['cliente']) . '. Recebemos seu pedido e ele sera revisado pelo gestor.</p>'
        . '<ul>' . $items . '</ul>'
        . '<p><strong>Total:</strong> ' . money_br((float) $pedido['total']) . '</p>'
        . '<p><strong>Status atual:</strong> ' . e(pedido_status_label($pedido['status'])) . '</p>';

    email_send((int) $pedido['id'], (string) $pedido['email'], (string) $pedido['cliente'], 'Confirmacao do pedido #' . (int) $pedido['id'], $html);
}

function pedido_send_status_email(int $pedidoId, string $status, string $extra = ''): void
{
    $pedido = pedido_email_context($pedidoId);
    if (!$pedido) {
        return;
    }

    $html = '<h2>Status do pedido #' . (int) $pedido['id'] . '</h2>'
        . '<p>Ola, ' . e($pedido['cliente']) . '. O status do seu pedido foi atualizado.</p>'
        . '<p><strong>Novo status:</strong> ' . e(pedido_status_label($status)) . '</p>'
        . ($extra !== '' ? '<p>' . nl2br(e($extra)) . '</p>' : '')
        . '<p><strong>Total:</strong> ' . money_br((float) $pedido['total']) . '</p>';

    email_send((int) $pedido['id'], (string) $pedido['email'], (string) $pedido['cliente'], 'Atualizacao do pedido #' . (int) $pedido['id'], $html);
}

function pedido_send_delivery_assigned_email(int $pedidoId, int $entregaId): void
{
    $pedido = pedido_email_context($pedidoId);
    $entrega = db_one(
        "SELECT en.*, u.nome AS entregador, u.email AS entregador_email
         FROM entregas en
         LEFT JOIN usuarios u ON u.id = en.entregador_id
         WHERE en.id = :id",
        ['id' => $entregaId]
    );

    if (!$pedido || !$entrega) {
        return;
    }

    $html = '<h2>Entrega do pedido #' . (int) $pedido['id'] . '</h2>'
        . '<p>Seu pedido foi vinculado ao entregador.</p>'
        . '<p><strong>Entregador:</strong> ' . e($entrega['entregador'] ?: 'A definir') . '</p>'
        . '<p><strong>Transportadora/servico:</strong> ' . e(trim((string) ($entrega['transportadora'] ?? '') . ' ' . (string) ($entrega['servico'] ?? ''))) . '</p>'
        . '<p><strong>Codigo de rastreio:</strong> ' . e($entrega['codigo_rastreio'] ?: 'Nao informado') . '</p>';

    email_send((int) $pedido['id'], (string) $pedido['email'], (string) $pedido['cliente'], 'Entrega do pedido #' . (int) $pedido['id'], $html);
}

function pedido_send_delivery_completed_email(int $pedidoId, int $entregaId): void
{
    $pedido = pedido_email_context($pedidoId);
    $entrega = db_one(
        "SELECT en.*, u.nome AS entregador, u.email AS entregador_email
         FROM entregas en
         LEFT JOIN usuarios u ON u.id = en.entregador_id
         WHERE en.id = :id",
        ['id' => $entregaId]
    );

    if (!$pedido || !$entrega) {
        return;
    }

    $when = $entrega['entregue_em'] ?: date('Y-m-d H:i:s');
    $html = '<h2>Pedido #' . (int) $pedido['id'] . ' entregue</h2>'
        . '<p>Ola, ' . e($pedido['cliente']) . '. Sua entrega foi confirmada no sistema.</p>'
        . '<p><strong>Entregador:</strong> ' . e($entrega['entregador'] ?: 'Nao informado') . '</p>'
        . '<p><strong>Data e hora da entrega:</strong> ' . e($when) . '</p>'
        . '<p><strong>Observacao:</strong> ' . e($entrega['observacao_entregador'] ?: 'Sem observacao') . '</p>';

    email_send((int) $pedido['id'], (string) $pedido['email'], (string) $pedido['cliente'], 'Pedido #' . (int) $pedido['id'] . ' entregue', $html);
}
