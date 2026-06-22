<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');
$q = trim((string) ($_GET['q'] ?? ''));
$notasPage = paginate_query(
    "SELECT nf.*, p.id AS pedido_id, u.nome AS cliente
     FROM notas_fiscais nf
     JOIN pedidos p ON p.id = nf.pedido_id
     LEFT JOIN usuarios u ON u.id = p.usuario_id
     WHERE (:q = '' OR nf.numero LIKE :like_q OR nf.chave_acesso LIKE :like_q OR u.nome LIKE :like_q)
     ORDER BY nf.emitida_em DESC",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$notas = $notasPage['rows'];
$pageTitle = 'NF-e';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><h1 class="h3 section-title">NF-e</h1><p class="text-secondary">Modulo preparado para armazenar XML, DANFE, chave de acesso, protocolo, cancelamento e carta de correcao.</p><form class="search-control mb-4" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Numero, chave ou cliente"><button class="btn btn-brand">Buscar</button></div></form><div class="table-responsive"><table class="table align-middle"><thead><tr><th>NF-e</th><th>Pedido</th><th>Cliente</th><th>Status</th><th>Chave</th><th></th></tr></thead><tbody><?php foreach ($notas as $n): ?><tr><td><?= e($n['numero'] ?: 'Pendente') ?></td><td>#<?= (int) $n['pedido_id'] ?></td><td><?= e($n['cliente']) ?></td><td><?= e($n['status']) ?></td><td class="small"><?= e($n['chave_acesso']) ?></td><td class="text-end"><?php if (!empty($n['xml_path']) || !empty($n['xml_url'])): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(base_url('admin/faturamento/xml.php?acao=abrir&nota_id=' . (int) $n['id'])) ?>"><i class="bi bi-folder2-open"></i> XML</a><?php endif; ?> <a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/faturamento/nota.php?id=' . (int) $n['id'])) ?>"><i class="bi bi-gear"></i> Gerenciar</a></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$notas): ?><p class="text-secondary mb-0">Nenhuma NF-e localizada.</p><?php endif; ?><?= pagination_links($notasPage) ?></div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
