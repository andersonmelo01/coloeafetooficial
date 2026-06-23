<?php
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'Colo e Afeto';
$active = $active ?? '';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?> | Colo e Afeto</title>
    <!--<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">-->
    <!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">-->
    <link rel="stylesheet" href="<?= e(asset_url('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('bootstrap-icons/bootstrap-icons.css')) ?>">
    <link href="<?= e(asset_url('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom soft-shadow site-navbar">
    <div class="container">
        <a class="navbar-brand fw-bold brand-mark d-flex align-items-center" href="<?= e(base_url('home.php')) ?>">
            <img class="brand-logo-img" src="<?= e(base_url('img/logo.jpeg')) ?>" alt="Colo e Afeto">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse main-nav-panel" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link <?= $active === 'home' ? 'active' : '' ?>" href="<?= e(base_url('home.php')) ?>">Home</a></li>
                <?php if ($active === 'home'): ?>
                    <li class="nav-item"><a class="nav-link" href="#servicos">Serviços</a></li>
                    <li class="nav-item"><a class="nav-link" href="#parceiros">Parceiros</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link <?= $active === 'loja' ? 'active' : '' ?>" href="<?= e(base_url('loja/index.php')) ?>">Loja</a></li>
                <?php if ((current_user()['tipo'] ?? '') === 'entregador'): ?>
                    <li class="nav-item"><a class="nav-link <?= $active === 'entregador' ? 'active' : '' ?>" href="<?= e(base_url('entregador/index.php')) ?>">Entregas</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= $active === 'cliente' ? 'active' : '' ?>" href="<?= e(base_url('cliente/index.php')) ?>">Area do cliente</a></li>
                <?php endif; ?>
                <!-- <li class="nav-item"><a class="nav-link <?= $active === 'admin' ? 'active' : '' ?>" href="<?= e(base_url('admin/index.php')) ?>">Gestor</a></li> -->
                <li class="nav-item">
                    <a class="btn btn-brand btn-sm ms-lg-2" href="<?= e(base_url('carrinho/index.php')) ?>">
                        <i class="bi bi-bag"></i> Carrinho
                        <?php if (cart_count() > 0): ?>
                            <span class="badge text-bg-light ms-1"><?= cart_count() ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if (current_user()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(base_url('auth/logout.php')) ?>">Sair</a></li>
                <?php else: ?>
                    <!--<li class="nav-item"><a class="nav-link" href="<?= e(base_url('auth/login.php')) ?>">Entrar</a></li>-->
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="page-main">
    <?php foreach (consume_flash() as $msg): ?>
        <div class="container">
            <div class="alert alert-<?= e($msg['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        </div>
    <?php endforeach; ?>
