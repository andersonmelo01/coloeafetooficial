<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$usuario = current_user();
if ($usuario && ($usuario['tipo'] ?? '') === 'admin') {
    redirect('admin/index.php');
}

$_SESSION['redirect_after_login'] = 'admin/index.php';
redirect('auth/login.php');