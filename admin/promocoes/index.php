<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    try {
        if (($_POST['acao'] ?? '') === 'excluir') {
            db()->prepare("DELETE FROM promocoes WHERE id = :id")->execute(['id' => (int) $_POST['id']]);
            flash('success', 'Promocao excluida.');
        } else {
            $stmt = db()->prepare(
                "INSERT INTO promocoes
                 (produto_id, titulo, descricao, preco_promocional, percentual_desconto, data_inicio, data_fim, ativo, destaque)
                 VALUES (:produto_id, :titulo, :descricao, :preco_promocional, :percentual_desconto, :data_inicio, :data_fim, :ativo, :destaque)"
            );
            $stmt->execute([
                'produto_id' => (int) $_POST['produto_id'],
                'titulo' => $_POST['titulo'],
                'descricao' => $_POST['descricao'] ?: null,
                'preco_promocional' => $_POST['preco_promocional'] ?: null,
                'percentual_desconto' => $_POST['percentual_desconto'] ?: null,
                'data_inicio' => $_POST['data_inicio'] ?: null,
                'data_fim' => $_POST['data_fim'] ?: null,
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'destaque' => isset($_POST['destaque']) ? 1 : 0,
            ]);
            flash('success', 'Promocao criada.');
        }
    } catch (Throwable $e) {
        flash('danger', 'Nao foi possivel processar a promocao.');
    }

    redirect('admin/promocoes/index.php');
}

$produtos = db_all("SELECT id, nome, preco FROM produtos WHERE ativo = 1 ORDER BY nome");
$promocoesPage = paginate_query(
    "SELECT pr.*, p.nome AS produto, p.preco
     FROM promocoes pr
     JOIN produtos p ON p.id = pr.produto_id
     ORDER BY pr.criado_em DESC"
);
$promocoes = $promocoesPage['rows'];

$pageTitle = 'Promocoes';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card bg-white p-4">
                <h1 class="h4 section-title">Nova promocao</h1>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12"><label class="form-label">Produto</label><select class="form-select" name="produto_id" required><option value="">Selecione</option><?php foreach ($produtos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?> - <?= money_br((float) $p['preco']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required></div>
                    <div class="col-12"><label class="form-label">Descricao</label><input class="form-control" name="descricao"></div>
                    <div class="col-md-6"><label class="form-label">Preco promocional</label><input class="form-control" name="preco_promocional" type="number" step="0.01"></div>
                    <div class="col-md-6"><label class="form-label">% desconto</label><input class="form-control" name="percentual_desconto" type="number" step="0.01"></div>
                    <div class="col-md-6"><label class="form-label">Inicio</label><input class="form-control" name="data_inicio" type="date"></div>
                    <div class="col-md-6"><label class="form-label">Fim</label><input class="form-control" name="data_fim" type="date"></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" checked> Ativa</label></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="destaque" checked> Destacar na loja</label></div>
                    <div class="col-12"><button class="btn btn-brand">Salvar promocao</button></div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel-card bg-white p-4">
                <h2 class="h4 section-title">Promocoes cadastradas</h2>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produto</th><th>Oferta</th><th>Status</th><th></th></tr></thead><tbody>
                    <?php foreach ($promocoes as $promo): ?>
                        <tr>
                            <td><?= e($promo['produto']) ?></td>
                            <td><strong><?= e($promo['titulo']) ?></strong><br><small><?= $promo['preco_promocional'] ? money_br((float) $promo['preco_promocional']) : e((string) $promo['percentual_desconto']) . '% OFF' ?></small></td>
                            <td><?= (int) $promo['ativo'] ? 'Ativa' : 'Inativa' ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/promocoes/editar.php?id=' . (int) $promo['id'])) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $promo['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Excluir promocao?"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php if (!$promocoes): ?><p class="text-secondary mb-0">Nenhuma promocao cadastrada.</p><?php endif; ?>
                <?= pagination_links($promocoesPage) ?>
            </div>
        </div>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
