<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$adminMenuItems = [
    ['dashboard', 'admin/index.php', 'bi-speedometer2', 'Dashboard'],
    ['servicos', 'admin/servicos/index.php', 'bi-images', 'Serviços'],
    ['produtos', 'admin/produtos/index.php', 'bi-box-seam', 'Produtos'],
    ['estoque', 'admin/estoque/index.php', 'bi-arrow-left-right', 'Estoque'],
    ['promocoes', 'admin/promocoes/index.php', 'bi-megaphone', 'Promoções'],
    ['categorias', 'admin/categorias/index.php', 'bi-tags', 'Categorias'],
    ['grupos', 'admin/grupos/index.php', 'bi-collection', 'Grupos'],
    ['pedidos', 'admin/pedidos/index.php', 'bi-bag-check', 'Pedidos'],
    ['entregas', 'admin/entregas/index.php', 'bi-truck', 'Entregas'],
    ['entregadores', 'admin/entregadores/index.php', 'bi-person-badge', 'Entregadores'],
    ['clientes', 'admin/clientes/index.php', 'bi-people', 'Clientes'],
    ['notas', 'admin/notas/index.php', 'bi-receipt', 'NF-e'],
    ['faturamento', 'admin/faturamento/index.php', 'bi-file-earmark-check', 'Faturamento'],
    ['pagamentos', 'admin/pagamentos/index.php', 'bi-credit-card', 'Pagamentos'],
    ['chamados', 'admin/chamados/index.php', 'bi-headset', 'Chamados'],
    ['relatorios', 'admin/relatorios/index.php', 'bi-graph-up', 'Relatórios'],
    ['perfis', 'admin/perfis/index.php', 'bi-shield-lock', 'Perfis'],
    ['gestores', 'admin/gestores/index.php', 'bi-person-gear', 'Gestores'],
    ['configuracoes', 'admin/configuracoes/index.php', 'bi-sliders', 'Configurações'],
];
?>
<div class="panel-card bg-white p-3">
    <?php foreach ($adminMenuItems as [$permission, $path, $icon, $label]): ?>
        <?php if (admin_can($permission)): ?>
            <a class="sidebar-link" href="<?= e(base_url($path)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
