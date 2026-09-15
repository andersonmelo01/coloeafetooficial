<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$usuario = mobile_require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Metodo nao permitido.', 405);
}

$body = api_body();
$id = (int) ($body['venda_id'] ?? 0);
$motivo = trim((string) ($body['motivo_cancelamento'] ?? ''));

if ($id <= 0) {
    api_error('Venda invalida.');
}

$pdo = db();

try {
    $pdo->beginTransaction();
    $venda = db_one("SELECT * FROM vendas WHERE id = :id FOR UPDATE", ['id' => $id]);
    if (!$venda || !in_array($venda['status'], ['finalizada', 'pendente'], true)) {
        throw new RuntimeException('Venda nao esta em situacao de cancelamento.');
    }

    $pdo->prepare(
        "UPDATE vendas
         SET status = 'cancelada', cancelada_em = NOW(), cancelada_por = :cancelada_por, motivo_cancelamento = :motivo
         WHERE id = :id"
    )->execute([
        'cancelada_por' => (int) $usuario['id'],
        'motivo' => $motivo !== '' ? $motivo : null,
        'id' => $id,
    ]);

    $insertMovimento = $pdo->prepare(
        "INSERT INTO movimentos_estoque (produto_id, usuario_id, tipo, quantidade, saldo_anterior, saldo_posterior, motivo, origem, observacao)
         VALUES (:produto_id, :usuario_id, 'entrada', :quantidade, :saldo_anterior, :saldo_posterior, :motivo, :origem, :observacao)"
    );
    $updateEstoque = $pdo->prepare("UPDATE produtos SET estoque = :estoque WHERE id = :id");

    foreach (db_all("SELECT * FROM venda_itens WHERE venda_id = :venda_id", ['venda_id' => $id]) as $item) {
        $saldoAtual = (int) (db_one("SELECT estoque FROM produtos WHERE id = :id", ['id' => (int) $item['produto_id']])['estoque'] ?? 0);
        $quantidade = (int) $item['quantidade'];
        $saldoNovo = $saldoAtual + $quantidade;
        $updateEstoque->execute(['estoque' => $saldoNovo, 'id' => (int) $item['produto_id']]);
        $insertMovimento->execute([
            'produto_id' => (int) $item['produto_id'],
            'usuario_id' => (int) $usuario['id'],
            'quantidade' => $quantidade,
            'saldo_anterior' => $saldoAtual,
            'saldo_posterior' => $saldoNovo,
            'motivo' => 'Estorno da venda ' . $venda['numero'],
            'origem' => 'pdv',
            'observacao' => $motivo !== '' ? $motivo : null,
        ]);
    }

    $pdo->prepare("UPDATE venda_pagamentos SET status = 'cancelado' WHERE venda_id = :venda_id AND status IN ('pago', 'pendente')")->execute(['venda_id' => $id]);
    $pdo->prepare("UPDATE venda_parcelas SET status = 'cancelado' WHERE venda_id = :venda_id AND status IN ('pago', 'pendente')")->execute(['venda_id' => $id]);

    $cancelaPagamento = "INSERT INTO caixa_movimentos (caixa_id, venda_id, tipo, metodo, valor, observacao, usuario_id)
                         SELECT cm.caixa_id, cm.venda_id, 'saida', cm.metodo, cm.valor, :observacao, :usuario_id
                         FROM caixa_movimentos cm
                         WHERE cm.venda_id = :venda_id AND cm.tipo = 'entrada'";
    $pdo->prepare($cancelaPagamento)->execute([
        'observacao' => 'Estorno da venda ' . $venda['numero'],
        'usuario_id' => (int) $usuario['id'],
        'venda_id' => $id,
    ]);

    $pdo->commit();

    api_json(['ok' => true, 'message' => "Venda {$venda['numero']} cancelada. Estoque e caixa ajustados."]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error('Nao foi possivel cancelar a venda: ' . $e->getMessage(), 400);
}