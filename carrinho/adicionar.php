<?php
require_once dirname(__DIR__) . '/includes/functions.php';
validate_csrf();

$voltar = (string) ($_POST['voltar'] ?? base_url('loja/index.php'));
if ($voltar === '' || !str_starts_with($voltar, '/') || str_starts_with($voltar, '//')) {
    $voltar = base_url('loja/index.php');
}

if (!loja_vendas_enabled()) {
    flash('warning', loja_catalog_message());
    header('Location: ' . $voltar);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$produto = null;

if ($id > 0) {
    $produto = db_one(
        "SELECT id, nome, preco, preco_promocional, estoque FROM produtos WHERE id = :id AND ativo = 1",
        ['id' => $id]
    );
}

$nome = $produto['nome'] ?? (string) ($_POST['nome'] ?? '');
$preco = (float) (($produto['preco_promocional'] ?? null) ?: ($produto['preco'] ?? ($_POST['preco'] ?? 0)));
$quantidade = max(1, (int) ($_POST['quantidade'] ?? 1));
$estoque = (int) ($produto['estoque'] ?? 0);

if ($id <= 0 || $nome === '' || $preco <= 0) {
    flash('danger', 'Produto invalido.');
    header('Location: ' . $voltar);
    exit;
}

$_SESSION['carrinho'][$id] ??= [
    'id' => $id,
    'nome' => $nome,
    'preco' => $preco,
    'quantidade' => 0,
];

$_SESSION['carrinho'][$id]['quantidade'] += $quantidade;
if ($estoque > 0) {
    $_SESSION['carrinho'][$id]['quantidade'] = min($_SESSION['carrinho'][$id]['quantidade'], $estoque);
}
flash('success', 'Produto adicionado ao carrinho.');
header('Location: ' . $voltar);
exit;
