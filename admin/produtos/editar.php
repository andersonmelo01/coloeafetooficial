<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$produto = db_one("SELECT * FROM produtos WHERE id = :id", ['id' => $id]);
if (!$produto) {
    flash('danger', 'Produto nao encontrado.');
    redirect('admin/produtos/index.php');
}
$fiscalIssues = produto_fiscal_issues($produto);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? 'salvar');

    if ($acao === 'excluir_imagem') {
        if (product_delete_image((int) ($_POST['imagem_id'] ?? 0), $id)) {
            flash('success', 'Imagem removida.');
        } else {
            flash('warning', 'Imagem nao encontrada.');
        }
        redirect('admin/produtos/editar.php?id=' . $id);
    }

    if ($acao === 'principal_imagem') {
        product_set_main_image((int) ($_POST['imagem_id'] ?? 0), $id);
        flash('success', 'Imagem principal atualizada.');
        redirect('admin/produtos/editar.php?id=' . $id);
    }

    if ($acao === 'adicionar_imagens') {
        $imagensSalvas = product_upload_images($id);
        flash($imagensSalvas > 0 ? 'success' : 'warning', $imagensSalvas > 0 ? 'Imagens adicionadas: ' . $imagensSalvas . '.' : 'Nenhuma imagem valida foi enviada.');
        redirect('admin/produtos/editar.php?id=' . $id);
    }

    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $_POST['nome']), '-'));
    db()->prepare(
        "UPDATE produtos
         SET categoria_id = :categoria_id, grupo_id = :grupo_id, nome = :nome, slug = :slug, sku = :sku,
             descricao_curta = :descricao_curta, descricao = :descricao, ncm = :ncm, cest = :cest, cfop = :cfop,
             unidade = :unidade, origem_mercadoria = :origem_mercadoria, cst_icms = :cst_icms, csosn = :csosn,
             cst_pis = :cst_pis, cst_cofins = :cst_cofins, aliquota_icms = :aliquota_icms,
             aliquota_pis = :aliquota_pis, aliquota_cofins = :aliquota_cofins,
             cst_ibs_cbs = :cst_ibs_cbs, cclass_trib_ibs_cbs = :cclass_trib_ibs_cbs,
             aliquota_ibs_uf = :aliquota_ibs_uf, aliquota_ibs_municipal = :aliquota_ibs_municipal,
             aliquota_cbs = :aliquota_cbs, cst_is = :cst_is, cclass_trib_is = :cclass_trib_is,
             aliquota_is = :aliquota_is,
             peso_kg = :peso_kg, altura_cm = :altura_cm, largura_cm = :largura_cm,
             comprimento_cm = :comprimento_cm, preco = :preco, preco_promocional = :preco_promocional,
             custo = :custo, estoque = :estoque, ativo = :ativo, destaque = :destaque
         WHERE id = :id"
    )->execute([
        'id' => $id,
        'categoria_id' => $_POST['categoria_id'] ?: null,
        'grupo_id' => $_POST['grupo_id'] ?: null,
        'nome' => $_POST['nome'],
        'slug' => $slug,
        'sku' => $_POST['sku'],
        'descricao_curta' => $_POST['descricao_curta'],
        'descricao' => $_POST['descricao'],
        'ncm' => $_POST['ncm'],
        'cest' => $_POST['cest'] ?: null,
        'cfop' => $_POST['cfop'],
        'unidade' => $_POST['unidade'],
        'origem_mercadoria' => $_POST['origem_mercadoria'] ?: null,
        'cst_icms' => $_POST['cst_icms'] ?: null,
        'csosn' => $_POST['csosn'] ?: null,
        'cst_pis' => $_POST['cst_pis'] ?: null,
        'cst_cofins' => $_POST['cst_cofins'] ?: null,
        'aliquota_icms' => $_POST['aliquota_icms'] ?: 0,
        'aliquota_pis' => $_POST['aliquota_pis'] ?: 0,
        'aliquota_cofins' => $_POST['aliquota_cofins'] ?: 0,
        'cst_ibs_cbs' => $_POST['cst_ibs_cbs'] ?: null,
        'cclass_trib_ibs_cbs' => $_POST['cclass_trib_ibs_cbs'] ?: null,
        'aliquota_ibs_uf' => $_POST['aliquota_ibs_uf'] ?: 0,
        'aliquota_ibs_municipal' => $_POST['aliquota_ibs_municipal'] ?: 0,
        'aliquota_cbs' => $_POST['aliquota_cbs'] ?: 0,
        'cst_is' => $_POST['cst_is'] ?: null,
        'cclass_trib_is' => $_POST['cclass_trib_is'] ?: null,
        'aliquota_is' => $_POST['aliquota_is'] ?: 0,
        'peso_kg' => $_POST['peso_kg'] ?: 0,
        'altura_cm' => $_POST['altura_cm'] ?: 0,
        'largura_cm' => $_POST['largura_cm'] ?: 0,
        'comprimento_cm' => $_POST['comprimento_cm'] ?: 0,
        'preco' => $_POST['preco'],
        'preco_promocional' => $_POST['preco_promocional'] ?: null,
        'custo' => $_POST['custo'] ?: 0,
        'estoque' => $_POST['estoque'] ?: 0,
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
        'destaque' => isset($_POST['destaque']) ? 1 : 0,
    ]);
    flash('success', 'Produto atualizado.');
    redirect('admin/produtos/index.php');
}

$categorias = db_all("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome");
$grupos = db_all("SELECT id, nome FROM grupos_produtos WHERE ativo = 1 ORDER BY nome");
$imagens = product_images($id);
$pageTitle = 'Editar Produto';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><h1 class="h3 section-title">Editar produto</h1><form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $produto['id'] ?>"><input type="hidden" name="acao" value="salvar">
        <div class="col-md-8"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?= e($produto['nome']) ?>" required></div><div class="col-md-4"><label class="form-label">SKU</label><input class="form-control" name="sku" value="<?= e($produto['sku']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Categoria</label><select class="form-select" name="categoria_id"><option value="">Selecione</option><?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $produto['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Grupo</label><select class="form-select" name="grupo_id"><option value="">Selecione</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $produto['grupo_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Descricao curta</label><input class="form-control" name="descricao_curta" value="<?= e($produto['descricao_curta']) ?>"></div><div class="col-12"><label class="form-label">Descricao completa</label><textarea class="form-control" name="descricao" rows="4"><?= e($produto['descricao']) ?></textarea></div>
        <div class="col-12"><?php if ($fiscalIssues['errors']): ?><div class="alert alert-danger mb-0"><strong>Ajustar antes de emitir NF-e:</strong> <?= e(implode(' | ', $fiscalIssues['errors'])) ?></div><?php elseif ($fiscalIssues['warnings']): ?><div class="alert alert-warning mb-0"><strong>Revisao fiscal recomendada:</strong> <?= e(implode(' | ', $fiscalIssues['warnings'])) ?></div><?php else: ?><div class="alert alert-success mb-0"><strong>Cadastro fiscal OK:</strong> produto sem pendencias bloqueantes para preparacao da NF-e.</div><?php endif; ?></div>
        <div class="col-md-3"><label class="form-label">NCM</label><input class="form-control" name="ncm" value="<?= e($produto['ncm']) ?>" required></div><div class="col-md-3"><label class="form-label">CEST</label><input class="form-control" name="cest" value="<?= e($produto['cest']) ?>"></div><div class="col-md-3"><label class="form-label">CFOP</label><input class="form-control" name="cfop" value="<?= e($produto['cfop']) ?>" required></div><div class="col-md-3"><label class="form-label">Unidade</label><input class="form-control" name="unidade" value="<?= e($produto['unidade']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Origem da mercadoria</label><select class="form-select" name="origem_mercadoria" required><option value="">Selecione</option><?php foreach (['0' => '0 - Nacional', '1' => '1 - Estrangeira importacao direta', '2' => '2 - Estrangeira mercado interno', '3' => '3 - Nacional, importacao superior a 40%', '4' => '4 - Nacional conforme processos produtivos', '5' => '5 - Nacional, importacao inferior ou igual a 40%', '6' => '6 - Estrangeira importacao direta sem similar', '7' => '7 - Estrangeira mercado interno sem similar', '8' => '8 - Nacional, importacao superior a 70%'] as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) ($produto['origem_mercadoria'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">CST ICMS</label><input class="form-control" name="cst_icms" maxlength="4" value="<?= e($produto['cst_icms'] ?? '') ?>"></div><div class="col-md-2"><label class="form-label">CSOSN</label><input class="form-control" name="csosn" maxlength="4" value="<?= e($produto['csosn'] ?? '') ?>"></div><div class="col-md-2"><label class="form-label">CST PIS</label><input class="form-control" name="cst_pis" maxlength="4" value="<?= e($produto['cst_pis'] ?? '') ?>"></div><div class="col-md-2"><label class="form-label">CST COFINS</label><input class="form-control" name="cst_cofins" maxlength="4" value="<?= e($produto['cst_cofins'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Aliquota ICMS %</label><input class="form-control" name="aliquota_icms" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_icms'] ?? '0')) ?>"></div><div class="col-md-4"><label class="form-label">Aliquota PIS %</label><input class="form-control" name="aliquota_pis" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_pis'] ?? '0')) ?>"></div><div class="col-md-4"><label class="form-label">Aliquota COFINS %</label><input class="form-control" name="aliquota_cofins" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_cofins'] ?? '0')) ?>"></div>
        <div class="col-12"><h2 class="h5 mb-0">Reforma Tributaria - IBS/CBS/IS</h2><div class="form-text">Campos preparados para NT 2025.002 RTC. Confirme CST, classificacao tributaria e aliquotas com a contabilidade.</div></div>
        <div class="col-md-3"><label class="form-label">CST IBS/CBS</label><input class="form-control" name="cst_ibs_cbs" maxlength="6" value="<?= e($produto['cst_ibs_cbs'] ?? '') ?>"></div><div class="col-md-3"><label class="form-label">Class. trib. IBS/CBS</label><input class="form-control" name="cclass_trib_ibs_cbs" maxlength="12" value="<?= e($produto['cclass_trib_ibs_cbs'] ?? '') ?>"></div><div class="col-md-2"><label class="form-label">IBS UF %</label><input class="form-control" name="aliquota_ibs_uf" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_ibs_uf'] ?? '0')) ?>"></div><div class="col-md-2"><label class="form-label">IBS Mun. %</label><input class="form-control" name="aliquota_ibs_municipal" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_ibs_municipal'] ?? '0')) ?>"></div><div class="col-md-2"><label class="form-label">CBS %</label><input class="form-control" name="aliquota_cbs" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_cbs'] ?? '0')) ?>"></div>
        <div class="col-md-4"><label class="form-label">CST IS</label><input class="form-control" name="cst_is" maxlength="6" value="<?= e($produto['cst_is'] ?? '') ?>"></div><div class="col-md-4"><label class="form-label">Class. trib. IS</label><input class="form-control" name="cclass_trib_is" maxlength="12" value="<?= e($produto['cclass_trib_is'] ?? '') ?>"></div><div class="col-md-4"><label class="form-label">Aliquota IS %</label><input class="form-control" name="aliquota_is" type="number" step="0.0001" value="<?= e((string) ($produto['aliquota_is'] ?? '0')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Preco</label><input class="form-control" name="preco" type="number" step="0.01" value="<?= e((string) $produto['preco']) ?>" required></div><div class="col-md-3"><label class="form-label">Promocional</label><input class="form-control" name="preco_promocional" type="number" step="0.01" value="<?= e((string) $produto['preco_promocional']) ?>"></div><div class="col-md-3"><label class="form-label">Custo</label><input class="form-control" name="custo" type="number" step="0.01" value="<?= e((string) $produto['custo']) ?>"></div><div class="col-md-3"><label class="form-label">Estoque</label><input class="form-control" name="estoque" type="number" value="<?= (int) $produto['estoque'] ?>"></div>
        <div class="col-md-3"><label class="form-label">Peso kg</label><input class="form-control" name="peso_kg" type="number" step="0.001" value="<?= e((string) $produto['peso_kg']) ?>"></div><div class="col-md-3"><label class="form-label">Altura cm</label><input class="form-control" name="altura_cm" type="number" step="0.01" value="<?= e((string) $produto['altura_cm']) ?>"></div><div class="col-md-3"><label class="form-label">Largura cm</label><input class="form-control" name="largura_cm" type="number" step="0.01" value="<?= e((string) $produto['largura_cm']) ?>"></div><div class="col-md-3"><label class="form-label">Comprimento cm</label><input class="form-control" name="comprimento_cm" type="number" step="0.01" value="<?= e((string) $produto['comprimento_cm']) ?>"></div>
        <div class="col-12"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="ativo" <?= (int) $produto['ativo'] ? 'checked' : '' ?>><label class="form-check-label">Ativo</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="destaque" <?= (int) $produto['destaque'] ? 'checked' : '' ?>><label class="form-check-label">Destaque</label></div></div>
        <div class="col-12"><button class="btn btn-brand">Salvar produto</button></div>
    </form></div>
    <div class="panel-card bg-white p-4 mt-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h4 section-title mb-1">Imagens do produto</h2>
                <p class="text-secondary mb-0">A imagem principal aparece em destaque na loja e no inicio do carrossel.</p>
            </div>
            <form method="post" enctype="multipart/form-data" class="d-flex flex-column flex-md-row gap-2 align-items-md-end">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $produto['id'] ?>">
                <input type="hidden" name="acao" value="adicionar_imagens">
                <div><label class="form-label small">Adicionar imagens</label><input class="form-control" name="imagens[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple required></div>
                <button class="btn btn-brand"><i class="bi bi-images"></i> Enviar</button>
            </form>
        </div>
        <?php if ($imagens): ?>
            <div class="product-admin-gallery">
                <?php foreach ($imagens as $imagem): ?>
                    <div class="product-admin-thumb">
                        <img src="<?= e(base_url($imagem['caminho'])) ?>" alt="Imagem do produto <?= e($produto['nome']) ?>">
                        <?php if ((int) $imagem['principal']): ?><span class="badge text-bg-success">Principal</span><?php endif; ?>
                        <div class="d-flex gap-2 mt-2">
                            <?php if (!(int) $imagem['principal']): ?>
                                <form method="post" class="flex-fill"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $produto['id'] ?>"><input type="hidden" name="acao" value="principal_imagem"><input type="hidden" name="imagem_id" value="<?= (int) $imagem['id'] ?>"><button class="btn btn-sm btn-outline-brand w-100"><i class="bi bi-star"></i></button></form>
                            <?php endif; ?>
                            <form method="post" class="flex-fill"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $produto['id'] ?>"><input type="hidden" name="acao" value="excluir_imagem"><input type="hidden" name="imagem_id" value="<?= (int) $imagem['id'] ?>"><button class="btn btn-sm btn-outline-danger w-100" data-confirm="Excluir esta imagem?"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-secondary mb-0">Nenhuma imagem cadastrada para este produto.</p>
        <?php endif; ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
