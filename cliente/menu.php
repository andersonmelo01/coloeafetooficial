<?php require_once dirname(__DIR__) . '/includes/functions.php'; ?>
<div class="panel-card bg-white p-3 customer-shell-menu">
    <a class="sidebar-link" href="<?= e(base_url('cliente/index.php')) ?>"><i class="bi bi-grid"></i> Dashboard</a>
    <a class="sidebar-link" href="<?= e(base_url('cliente/pedidos.php')) ?>"><i class="bi bi-bag-check"></i> Historico de compras</a>
    <a class="sidebar-link" href="<?= e(base_url('cliente/notas.php')) ?>"><i class="bi bi-receipt"></i> Baixar NF-e</a>
    <a class="sidebar-link" href="<?= e(base_url('cliente/chamados.php')) ?>"><i class="bi bi-headset"></i> Chamados</a>
</div>
