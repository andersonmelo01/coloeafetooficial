<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$usuario = mobile_require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = api_body();
    $acao = (string) ($body['acao'] ?? '');
    $pdo = db();
    $caixa = pdv_caixa_aberto();

    try {
        if ($acao === 'abrir') {
            if ($caixa) {
                throw new RuntimeException('Ja existe um caixa aberto.');
            }
            $saldoInicial = max(0.0, mobile_money($body['saldo_inicial'] ?? 0));
            $pdo->prepare(
                "INSERT INTO caixas (usuario_id, saldo_inicial, status)
                 VALUES (:usuario_id, :saldo_inicial, 'aberto')"
            )->execute([
                'usuario_id' => (int) $usuario['id'],
                'saldo_inicial' => $saldoInicial,
            ]);
            api_json(['ok' => true, 'message' => 'Caixa aberto com sucesso.']);
        } elseif ($acao === 'sangria' || $acao === 'suprimento') {
            if (!$caixa) {
                throw new RuntimeException('Nenhum caixa aberto.');
            }
            $valor = max(0.01, mobile_money($body['valor'] ?? 0));
            $observacao = trim((string) ($body['observacao'] ?? ''));
            $tipo = $acao === 'sangria' ? 'sangria' : 'entrada';
            $obs = $acao === 'sangria' ? 'Sangria' : 'Suprimento';
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
                'usuario_id' => (int) $usuario['id'],
            ]);
            api_json(['ok' => true, 'message' => ucfirst($acao) . ' registrada.']);
        } elseif ($acao === 'fechar') {
            if (!$caixa) {
                throw new RuntimeException('Nenhum caixa aberto.');
            }
            $observacao = trim((string) ($body['observacao'] ?? ''));
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
            api_json([
                'ok' => true,
                'message' => 'Caixa fechado. Saldo final: ' . money_br($saldo),
                'saldo_final' => $saldo,
            ]);
        } else {
            throw new RuntimeException('Acao desconhecida.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_error('Nao foi possivel concluir a acao: ' . $e->getMessage(), 400);
    }
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

    $movimentos = array_map(static function (array $m) {
        return [
            'id' => (int) $m['id'],
            'caixa_id' => (int) $m['caixa_id'],
            'venda_id' => !empty($m['venda_id']) ? (int) $m['venda_id'] : null,
            'venda_numero' => (string) ($m['venda_numero'] ?? ''),
            'tipo' => (string) $m['tipo'],
            'metodo' => (string) $m['metodo'],
            'metodo_label' => pdv_payment_label((string) $m['metodo']),
            'valor' => (float) $m['valor'],
            'valor_formatado' => money_br((float) $m['valor']),
            'observacao' => (string) ($m['observacao'] ?? ''),
            'usuario' => (string) ($m['usuario'] ?? ''),
            'criado_em' => (string) $m['criado_em'],
        ];
    }, $movimentos);
}

$resumoArray = [];
foreach ($resumoVendasPorMetodo as $metodo => $valor) {
    $resumoArray[] = [
        'metodo' => (string) $metodo,
        'metodo_label' => pdv_payment_label((string) $metodo),
        'valor' => $valor,
        'valor_formatado' => money_br($valor),
    ];
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
$caixasAnteriores = array_map(static function (array $c) {
    return [
        'id' => (int) $c['id'],
        'aberto_em' => (string) $c['aberto_em'],
        'fechado_em' => isset($c['fechado_em']) ? (string) $c['fechado_em'] : null,
        'usuario' => (string) $c['usuario'],
        'saldo_inicial' => (float) $c['saldo_inicial'],
        'saldo_final' => isset($c['saldo_final']) ? (float) $c['saldo_final'] : null,
        'total_entradas' => (float) $c['total_entradas'],
        'total_saidas' => (float) $c['total_saidas'],
        'status' => (string) $c['status'],
        'observacao' => (string) ($c['observacao'] ?? ''),
    ];
}, $caixasAnteriores);

api_json([
    'ok' => true,
    'caixa' => $caixa ? [
        'id' => (int) $caixa['id'],
        'aberto_em' => (string) $caixa['aberto_em'],
        'observacao' => (string) ($caixa['observacao'] ?? ''),
    ] : null,
    'totais' => array_map(static fn (float $v) => round($v, 2) + 0, $totais),
    'totais_formatados' => array_map('money_br', $totais),
    'movimentos' => $movimentos,
    'resumo_por_metodo' => $resumoArray,
    'caixas_anteriores' => $caixasAnteriores,
]);