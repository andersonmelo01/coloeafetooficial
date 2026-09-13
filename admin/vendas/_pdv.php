<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/functions.php';

function pdv_payment_methods(): array
{
    return [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito' => 'Cartão de débito',
        'a_prazo' => 'A prazo (crediário)',
    ];
}

function pdv_stock_control(): bool
{
    return controle_estoque_habilitado();
}

function pdv_payment_label(string $metodo): string
{
    return pdv_payment_methods()[$metodo] ?? $metodo;
}

function pdv_money_to_float(string $value): float
{
    $v = trim($value);
    if ($v === '') {
        return 0.0;
    }
    // Formato brasileiro: 1.234,56
    if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d{2}$/', $v)) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return (float) $v;
    }
    // Formato com vírgula simples: 1234,56
    if (preg_match('/^-?\d+,\d{1,2}$/', $v)) {
        return (float) str_replace(',', '.', $v);
    }
    // Formato americano / ponto simples
    return (float) $v;
}

function pdv_caixa_aberto(): ?array
{
    return db_one("SELECT * FROM caixas WHERE status = 'aberto' ORDER BY id DESC LIMIT 1");
}

function pdv_sync_venda_status(int $vendaId): void
{
    $pendentes = 0;
    $parcela = db_one(
        "SELECT COUNT(*) AS total FROM venda_parcelas WHERE venda_id = :venda_id AND status = 'pendente'",
        ['venda_id' => $vendaId]
    );
    if ($parcela) {
        $pendentes += (int) $parcela['total'];
    }
    $pagamento = db_one(
        "SELECT COUNT(*) AS total FROM venda_pagamentos WHERE venda_id = :venda_id AND status = 'pendente'",
        ['venda_id' => $vendaId]
    );
    if ($pagamento) {
        $pendentes += (int) $pagamento['total'];
    }

    $novoStatus = $pendentes > 0 ? 'pendente' : 'finalizada';
    db()->prepare("UPDATE vendas SET status = :status WHERE id = :id")->execute([
        'status' => $novoStatus,
        'id' => $vendaId,
    ]);
}

function pdv_caixa_saldo(array $caixa): float
{
    $saldo = (float) $caixa['saldo_inicial'];
    foreach (db_all("SELECT tipo, valor FROM caixa_movimentos WHERE caixa_id = :caixa_id", ['caixa_id' => (int) $caixa['id']]) as $mov) {
        $valor = (float) $mov['valor'];
        if ($mov['tipo'] === 'entrada') {
            $saldo += $valor;
        } else {
            $saldo -= $valor;
        }
    }
    return $saldo;
}

function pdv_cart(): array
{
    $cart = $_SESSION['pdv_carrinho'] ?? [];
    return is_array($cart) ? array_values($cart) : [];
}

function pdv_cart_count(): int
{
    return array_sum(array_column(pdv_cart(), 'quantidade'));
}

function pdv_cart_subtotal(): float
{
    return array_reduce(pdv_cart(), static fn (float $sum, array $item): float => $sum + ((float) $item['preco'] * (int) $item['quantidade']), 0.0);
}

function pdv_cart_find(int $produtoId): int
{
    foreach (pdv_cart() as $index => $item) {
        if ((int) $item['produto_id'] === $produtoId) {
            return $index;
        }
    }
    return -1;
}

function pdv_cart_add(int $produtoId, int $quantidade = 1): void
{
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
        return;
    }

    $cart = pdv_cart();
    $index = pdv_cart_find($produtoId);
    if ($index >= 0) {
        $cart[$index]['quantidade'] += $quantidade;
    } else {
        $cart[] = [
            'produto_id' => (int) $produtoId,
            'nome' => (string) $produto['nome'],
            'preco' => (float) promotion_price($produto),
            'quantidade' => $quantidade,
        ];
    }
    $_SESSION['pdv_carrinho'] = $cart;
}

function pdv_cart_set_quantidade(int $index, int $quantidade): void
{
    $cart = pdv_cart();
    if (!isset($cart[$index])) {
        return;
    }
    $cart[$index]['quantidade'] = max(1, $quantidade);
    $_SESSION['pdv_carrinho'] = $cart;
}

function pdv_cart_remove(int $index): void
{
    $cart = pdv_cart();
    if (!isset($cart[$index])) {
        return;
    }
    array_splice($cart, $index, 1);
    $_SESSION['pdv_carrinho'] = $cart;
}

function pdv_cart_clear(): void
{
    unset($_SESSION['pdv_carrinho']);
}

function pdv_next_numero(): string
{
    $next = (int) (db_one(
        "SELECT AUTO_INCREMENT AS next
         FROM information_schema.tables
         WHERE table_schema = :db AND table_name = 'vendas'",
        ['db' => DB_NAME]
    )['next'] ?? 1);

    return 'PDV-' . date('Ymd') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function pdv_clientes(): array
{
    return db_all(
        "SELECT id, nome, email
         FROM usuarios
         WHERE tipo = 'cliente' AND ativo = 1
         ORDER BY nome"
    );
}

function pdv_cliente_label(array $cliente): string
{
    $label = trim((string) ($cliente['nome'] ?? ''));
    $email = trim((string) ($cliente['email'] ?? ''));
    if ($email !== '') {
        $label .= $label !== '' ? ' · ' . $email : $email;
    }
    return $label !== '' ? $label : 'Cliente #' . (int) $cliente['id'];
}

function pdv_portal_tabs(string $current): void
{
    $tabs = [
        'index' => ['admin/vendas/index.php', 'Frente de caixa', 'bi-shop'],
        'historico' => ['admin/vendas/historico.php', 'Vendas', 'bi-bag-check'],
        'receber' => ['admin/vendas/receber.php', 'A receber', 'bi-clock-history'],
        'caixa' => ['admin/vendas/caixa.php', 'Caixa', 'bi-cash-stack'],
    ];
    echo '<div class="panel-card bg-white p-3 mb-4"><div class="d-flex flex-wrap gap-2">';
    foreach ($tabs as $key => [$path, $label, $icon]) {
        $class = $key === $current ? 'btn btn-brand' : 'btn btn-outline-brand';
        echo '<a class="' . $class . '" href="' . e(base_url($path)) . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</a>';
    }
    echo '</div></div>';
}

function pdv_metodo_badge(string $metodo): string
{
    return '<span class="badge text-bg-light">' . e(pdv_payment_label($metodo)) . '</span>';
}