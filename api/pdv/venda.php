<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';
require_once dirname(__DIR__, 2) . '/includes/EfiService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Metodo nao permitido.', 405);
}

$usuario = mobile_require_user();
$body = api_body();

$clienteId = max(0, (int) ($body['cliente_id'] ?? 0));
$clienteId = $clienteId > 0 ? $clienteId : null;
$emailRecibo = trim((string) ($body['email_recibo'] ?? ''));
if ($emailRecibo !== '' && !filter_var($emailRecibo, FILTER_VALIDATE_EMAIL)) {
    api_error('E-mail do recibo invalido.');
}
$desconto = max(0.0, mobile_money($body['desconto'] ?? 0));
$observacao = trim((string) ($body['observacao'] ?? ''));

$itensRequisicao = (array) ($body['itens'] ?? []);
$pagamentosRequisicao = (array) ($body['pagamentos'] ?? []);

/*
 * Construcao do carrinho com os precos SEMPRE vindos do banco.
 * Nunca confiamos em precos/valores enviados pelo dispositivo.
 */
$cart = [];
foreach ($itensRequisicao as $rawItem) {
    $produtoId = max(1, (int) (is_array($rawItem) ? ($rawItem['produto_id'] ?? 0) : 0));
    $quantidade = max(1, (int) (is_array($rawItem) ? ($rawItem['quantidade'] ?? 1) : 1));
    if ($produtoId <= 0) {
        continue;
    }
    $produto = db_one(
        "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
                pr.preco_promocional AS promo_preco,
                pr.percentual_desconto AS promo_percentual
         FROM produtos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN grupos_produtos g ON g.id = p.grupo_id
         LEFT JOIN promocoes pr ON pr.produto_id = p.id
            AND pr.ativo = 1
            AND (pr.data_inicio IS NULL OR pr.data_inicio <= CURDATE())
            AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())
            AND pr.id = (
                SELECT pr2.id FROM promocoes pr2
                WHERE pr2.produto_id = p.id AND pr2.ativo = 1
                ORDER BY pr2.id DESC LIMIT 1
            )
         WHERE p.id = :id AND p.ativo = 1",
        ['id' => $produtoId]
    );
    if (!$produto) {
        continue;
    }
    if (pdv_stock_control() && (int) $produto['estoque'] > 0) {
        $quantidade = min($quantidade, (int) $produto['estoque']);
    }
    $cart[] = [
        'produto_id' => $produtoId,
        'nome' => (string) $produto['nome'],
        'preco' => promotion_price($produto),
        'quantidade' => $quantidade,
    ];
}

if (!$cart) {
    api_error('Nenhum item valido na venda.');
}

$subtotal = array_reduce($cart, static fn (float $sum, array $item): float => $sum + ((float) $item['preco'] * (int) $item['quantidade']), 0.0);

$desconto = min($desconto, $subtotal);
$total = round($subtotal - $desconto, 2);

$pagamentos = [];
foreach ($pagamentosRequisicao as $rawPag) {
    if (!is_array($rawPag)) {
        continue;
    }
    $metodo = (string) ($rawPag['metodo'] ?? '');
    if (!array_key_exists($metodo, pdv_payment_methods())) {
        continue;
    }
    $valor = round(max(0.0, mobile_money($rawPag['valor'] ?? 0)), 2);
    if ($valor <= 0) {
        continue;
    }
    $pagamentos[] = [
        'metodo' => $metodo,
        'valor' => $valor,
        'parcelas' => max(1, (int) ($rawPag['parcelas'] ?? 1)),
        'vencimento' => mobile_date_mysql((string) ($rawPag['vencimento'] ?? '')),
        'token' => trim((string) ($rawPag['token'] ?? '')),
    ];
}

$erro = '';
foreach ($pagamentos as $pag) {
    if ($pag['metodo'] === 'a_prazo' && !$clienteId) {
        $erro = 'A venda a prazo exige a selecao de um cliente cadastrado.';
        break;
    }
}

if ($erro === '') {
    if (!$pagamentos) {
        $erro = 'Informe ao menos um pagamento valido.';
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
            $erro = sprintf(
                'O valor dos pagamentos (%s) e menor que o total da venda (%s).',
                money_br($soma),
                money_br($total)
            );
        }
    }
}

if ($erro !== '') {
    api_error($erro);
}

$pdo = db();
$usuarioId = (int) $usuario['id'];
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
    $clienteRow = $clienteId ? db_one("SELECT nome, email FROM usuarios WHERE id = :id", ['id' => $clienteId]) : null;
    $pixStore = null;
    $integradoPendente = false;
    $efiChargeId = '';
    $efiChargeStatus = '';
    $somaPagos = round(array_sum(array_column($pagamentos, 'valor')), 2);
    $restanteAprazo = round(max(0.0, $total - $somaPagos), 2);

    $avisoIntegracao = '';

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
                    'name' => trim((string) ($clienteRow['nome'] ?? '')) !== '' ? (string) $clienteRow['nome'] : 'Cliente Colo e Afeto',
                    'email' => (string) ($clienteRow['email'] ?? ''),
                    'cpf' => (string) ($clienteRow['cpf'] ?? ''),
                ]);
            }
        } elseif ($metodo === 'cartao_credito' && $pag['token'] !== '') {
            $ready = efi_can_charge_method('cartao_credito');
            if ($ready['ok']) {
                $integracao = efi_charge_card($valor, $pag['token'], $pag['parcelas'], [
                    'name' => trim((string) ($clienteRow['nome'] ?? '')) !== '' ? (string) $clienteRow['nome'] : 'Cliente Colo e Afeto',
                    'email' => trim((string) ($clienteRow['email'] ?? '')) !== '' ? (string) $clienteRow['email'] : 'nao-informado@coloafeto.local',
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

            if (trim((string) ($integracao['charge_id'] ?? '')) !== '') {
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
                ? 'Pix automatico indisponivel: ' . $integracao['message'] . ' Pagamento registrado como manual.'
                : 'Cartao online indisponivel: ' . $integracao['message'] . ' Pagamento registrado como manual.';
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

    $statusFinal = 'finalizada';
    if ($integradoPendente) {
        $statusFinal = 'pendente';
    } elseif ($restanteAprazo > 0) {
        $statusFinal = 'pendente';
    }

    $mensagens = [];
    if ($avisoIntegracao !== '') {
        $mensagens[] = ['tipo' => 'warning', 'texto' => $avisoIntegracao];
    }
    if ($restanteAprazo > 0) {
        $mensagens[] = [
            'tipo' => 'warning',
            'texto' => 'Venda ' . $numero . ' registrada como pendente. Saldo a receber: ' . money_br($restanteAprazo) . '.',
        ];
    } else {
        $mensagens[] = ['tipo' => 'success', 'texto' => 'Venda ' . $numero . ' concluida. Total: ' . money_br($total) . '.'];
    }

    api_json([
        'ok' => true,
        'venda' => [
            'id' => $vendaId,
            'numero' => $numero,
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'total' => $total,
            'subtotal_formatado' => money_br($subtotal),
            'desconto_formatado' => money_br($desconto),
            'total_formatado' => money_br($total),
            'status' => $statusFinal,
            'restante_aprazo' => $restanteAprazo,
            'restante_aprazo_formatado' => money_br($restanteAprazo),
            'observacao' => $observacao,
            'finalizada_em' => $now,
        ],
        'pagamento_pendente_online' => $integradoPendente,
        'aviso_integracao' => $avisoIntegracao,
        'pix' => $pixStore ? [
            'copiaecola' => $pixStore['copiaecola'],
            'qrcode_base64' => $pixStore['qrcode'],
        ] : null,
        'sem_caixa' => !(bool) $caixa,
        'mensagens' => $mensagens,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error('Nao foi possivel concluir a venda: ' . $e->getMessage(), 400);
}