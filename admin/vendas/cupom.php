<?php
require_once __DIR__ . '/_pdv.php';
require_once dirname(__DIR__, 2) . '/includes/CupomService.php';
require_login('admin');

$vendaId = (int) ($_GET['venda'] ?? 0);
if ($vendaId <= 0) {
    flash('warning', 'Informe a venda para imprimir o cupom.');
    redirect('admin/vendas/historico.php');
}

$venda = db_one("SELECT * FROM vendas WHERE id = :id", ['id' => $vendaId]);
if (!$venda || !in_array($venda['status'], ['finalizada', 'pendente'], true)) {
    flash('danger', 'Venda não encontrada ou sem pagamentos.');
    redirect('admin/vendas/historico.php');
}

$fiscal = pdv_fiscal_mode();
$autoPrint = isset($_GET['imprimir']) && $_GET['imprimir'] === '1';

$pageTitle = ($fiscal ? 'Cupom fiscal (NFC-e)' : 'Cupom não fiscal') . ' ' . $venda['numero'];
$active = 'admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(base_url('img/logo.jpeg')) ?>">
<link href="<?= e(asset_url('bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(asset_url('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="cupom-page">
<main class="py-4">
    <div class="container cupom-wrap">
        <?= pdv_cupom_html($vendaId, true) ?>
    </div>
</main>
<?php if ($autoPrint): ?>
<script>window.addEventListener('DOMContentLoaded', () => setTimeout(() => window.print(), 250));</script>
<?php endif; ?>
</body>
</html>