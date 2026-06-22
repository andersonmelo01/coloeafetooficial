<?php
require_once dirname(__DIR__) . '/includes/functions.php';
validate_csrf();

$id = (int) ($_POST['id'] ?? 0);
unset($_SESSION['carrinho'][$id]);

flash('success', 'Item removido do carrinho.');
redirect('carrinho/index.php');
