<?php
require_once dirname(__DIR__) . '/includes/functions.php';
validate_csrf();

foreach (($_POST['quantidades'] ?? []) as $id => $quantidade) {
    $id = (int) $id;
    $quantidade = max(0, (int) $quantidade);

    if ($quantidade === 0) {
        unset($_SESSION['carrinho'][$id]);
        continue;
    }

    if (isset($_SESSION['carrinho'][$id])) {
        $_SESSION['carrinho'][$id]['quantidade'] = $quantidade;
    }
}

flash('success', 'Carrinho atualizado.');
redirect('carrinho/index.php');
