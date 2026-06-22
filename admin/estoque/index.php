<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $produtoId = (int) $_POST['produto_id'];
    $quantidade = max(1, (int) $_POST['quantidade']);
    $operacao = (string) $_POST['operacao'];
    $motivo = (string) $_POST['motivo'];
    $origem = (string) ($_POST['origem'] ?? '');
    $observacao = (string) ($_POST['observacao'] ?? '');

    $produto = db_one("SELECT id, estoque FROM produtos WHERE id = :id", ['id' => $produtoId]);
    if (!$produto) {
        flash('danger', 'Produto nao encontrado.');
        redirect('admin/estoque/index.php');
    }

    $saldoAnterior = (int) $produto['estoque'];
    $tipo = 'ajuste';
    $saldoPosterior = $saldoAnterior;

    if ($operacao === 'entrada') {
        $tipo = 'entrada';
        $saldoPosterior = $saldoAnterior + $quantidade;
    } elseif ($operacao === 'saida_danificado' || $operacao === 'saida') {
        $tipo = 'saida';
        $saldoPosterior = max(0, $saldoAnterior - $quantidade);
    } elseif ($operacao === 'ajuste') {
        $tipo = 'ajuste';
        $saldoPosterior = max(0, (int) $_POST['saldo_ajustado']);
        $quantidade = abs($saldoPosterior - $saldoAnterior);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE produtos SET estoque = :estoque WHERE id = :id")->execute(['estoque' => $saldoPosterior, 'id' => $produtoId]);
        $pdo->prepare(
            "INSERT INTO movimentos_estoque
             (produto_id, usuario_id, tipo, quantidade, saldo_anterior, saldo_posterior, motivo, origem, observacao)
             VALUES (:produto_id, :usuario_id, :tipo, :quantidade, :saldo_anterior, :saldo_posterior, :motivo, :origem, :observacao)"
        )->execute([
            'produto_id' => $produtoId,
            'usuario_id' => current_user()['id'],
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'motivo' => $motivo,
            'origem' => $origem ?: $operacao,
            'observacao' => $observacao ?: null,
        ]);
        $pdo->commit();
        flash('success', 'Movimentacao registrada.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('danger', 'Nao foi possivel movimentar o estoque.');
    }

    redirect('admin/estoque/index.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$produtos = db_all("SELECT id, nome, sku, estoque FROM produtos WHERE ativo = 1 ORDER BY nome");
$movimentosPage = paginate_query(
    "SELECT me.*, p.nome AS produto, p.sku, u.nome AS usuario
     FROM movimentos_estoque me
     JOIN produtos p ON p.id = me.produto_id
     LEFT JOIN usuarios u ON u.id = me.usuario_id
     WHERE (:q = '' OR p.nome LIKE :like_q OR p.sku LIKE :like_q OR me.motivo LIKE :like_q OR u.nome LIKE :like_q)
     ORDER BY me.criado_em DESC",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$movimentos = $movimentosPage['rows'];

$pageTitle = 'Movimentacao de Estoque';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card bg-white p-4">
                <h1 class="h4 section-title">Movimentar estoque</h1>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12"><label class="form-label">Produto</label><select class="form-select" name="produto_id" required><option value="">Selecione</option><?php foreach ($produtos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?> - <?= e($p['sku']) ?> - Estoque: <?= (int) $p['estoque'] ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Operacao</label><select class="form-select" name="operacao" required><option value="entrada">Entrada para reabastecimento</option><option value="saida_danificado">Baixa por produto danificado</option><option value="saida">Saida manual</option><option value="ajuste">Ajuste de saldo</option></select></div>
                    <div class="col-md-6"><label class="form-label">Quantidade</label><input class="form-control" name="quantidade" type="number" min="1" value="1" required></div>
                    <div class="col-md-6"><label class="form-label">Saldo ajustado</label><input class="form-control" name="saldo_ajustado" type="number" min="0" value="0"></div>
                    <div class="col-12"><label class="form-label">Motivo</label><input class="form-control" name="motivo" required placeholder="Ex.: compra fornecedor, avaria, inventario"></div>
                    <div class="col-12"><label class="form-label">Origem/documento</label><input class="form-control" name="origem" placeholder="Ex.: NF compra 123, avaria interna"></div>
                    <div class="col-12"><label class="form-label">Observacao</label><textarea class="form-control" name="observacao" rows="3"></textarea></div>
                    <div class="col-12"><button class="btn btn-brand">Registrar movimentacao</button></div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel-card bg-white p-4">
                <h2 class="h4 section-title">Auditoria de estoque</h2>
                <form class="search-control mb-3" method="get"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Produto, SKU, motivo ou usuario"><button class="btn btn-brand">Buscar</button></div></form>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd</th><th>Saldo</th><th>Usuario</th></tr></thead><tbody><?php foreach ($movimentos as $m): ?><tr><td><?= e($m['criado_em']) ?></td><td><?= e($m['produto']) ?><br><small><?= e($m['motivo']) ?></small></td><td><?= e($m['tipo']) ?></td><td><?= (int) $m['quantidade'] ?></td><td><?= (int) $m['saldo_anterior'] ?> -> <?= (int) $m['saldo_posterior'] ?></td><td><?= e($m['usuario']) ?></td></tr><?php endforeach; ?></tbody></table></div>
                <?php if (!$movimentos): ?><p class="text-secondary mb-0">Nenhuma movimentacao localizada.</p><?php endif; ?>
                <?= pagination_links($movimentosPage) ?>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
