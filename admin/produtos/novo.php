<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    try {
        $stmt = db()->prepare(
            "INSERT INTO produtos
             (categoria_id, grupo_id, nome, slug, sku, descricao_curta, descricao, ncm, cest, cfop, unidade, origem_mercadoria, cst_icms, csosn, cst_pis, cst_cofins, aliquota_icms, aliquota_pis, aliquota_cofins, cst_ibs_cbs, cclass_trib_ibs_cbs, aliquota_ibs_uf, aliquota_ibs_municipal, aliquota_cbs, cst_is, cclass_trib_is, aliquota_is, peso_kg, altura_cm, largura_cm, comprimento_cm, preco, preco_promocional, custo, estoque, ativo, destaque)
             VALUES (:categoria_id, :grupo_id, :nome, :slug, :sku, :descricao_curta, :descricao, :ncm, :cest, :cfop, :unidade, :origem_mercadoria, :cst_icms, :csosn, :cst_pis, :cst_cofins, :aliquota_icms, :aliquota_pis, :aliquota_cofins, :cst_ibs_cbs, :cclass_trib_ibs_cbs, :aliquota_ibs_uf, :aliquota_ibs_municipal, :aliquota_cbs, :cst_is, :cclass_trib_is, :aliquota_is, :peso_kg, :altura_cm, :largura_cm, :comprimento_cm, :preco, :preco_promocional, :custo, :estoque, :ativo, :destaque)"
        );
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $_POST['nome']), '-'));
        $stmt->execute([
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
        $produtoId = (int) db()->lastInsertId();
        $imagensSalvas = product_upload_images($produtoId);
        flash('success', 'Produto cadastrado.' . ($imagensSalvas > 0 ? ' Imagens adicionadas: ' . $imagensSalvas . '.' : ''));
        redirect('admin/produtos/index.php');
    } catch (Throwable $e) {
        flash('danger', 'Nao foi possivel cadastrar o produto.');
        redirect('admin/produtos/novo.php');
    }
}

$categorias = db_all("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome");
$grupos = db_all("SELECT id, nome FROM grupos_produtos WHERE ativo = 1 ORDER BY nome");
$pageTitle = 'Novo Produto';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="panel-card bg-white p-4"><h1 class="h3 section-title">Novo produto</h1><form method="post" enctype="multipart/form-data" class="row g-3"><?= csrf_field() ?>
        <div class="col-md-8"><label class="form-label">Nome</label><input class="form-control" name="nome" required></div><div class="col-md-4"><label class="form-label">SKU</label><input class="form-control" name="sku" required></div>
        <div class="col-md-6"><label class="form-label">Categoria</label><select class="form-select" name="categoria_id"><option value="">Selecione</option><?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Grupo</label><select class="form-select" name="grupo_id"><option value="">Selecione</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Descricao curta</label><input class="form-control" name="descricao_curta"></div><div class="col-12"><label class="form-label">Descricao completa</label><textarea class="form-control" name="descricao" rows="4"></textarea></div>
        <div class="col-12"><label class="form-label">Imagens do produto</label><input class="form-control" name="imagens[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple><div class="form-text">Envie fotos em JPG, PNG, WEBP ou GIF. A primeira imagem sera usada como destaque.</div></div>
        <div class="col-12"><div class="alert alert-warning mb-0"><strong>Atencao fiscal:</strong> preencha NCM, CFOP, origem, CST/CSOSN e aliquotas conforme o regime tributario da empresa e regras vigentes para RJ antes de emitir NF-e.</div></div>
        <div class="col-md-3"><label class="form-label">NCM</label><input class="form-control" name="ncm" required></div><div class="col-md-3"><label class="form-label">CEST</label><input class="form-control" name="cest"></div><div class="col-md-3"><label class="form-label">CFOP</label><input class="form-control" name="cfop" value="5102" required></div><div class="col-md-3"><label class="form-label">Unidade</label><input class="form-control" name="unidade" value="UN" required></div>
        <div class="col-md-4"><label class="form-label">Origem da mercadoria</label><select class="form-select" name="origem_mercadoria" required><option value="">Selecione</option><option value="0">0 - Nacional</option><option value="1">1 - Estrangeira importacao direta</option><option value="2">2 - Estrangeira mercado interno</option><option value="3">3 - Nacional, importacao superior a 40%</option><option value="4">4 - Nacional conforme processos produtivos</option><option value="5">5 - Nacional, importacao inferior ou igual a 40%</option><option value="6">6 - Estrangeira importacao direta sem similar</option><option value="7">7 - Estrangeira mercado interno sem similar</option><option value="8">8 - Nacional, importacao superior a 70%</option></select></div><div class="col-md-2"><label class="form-label">CST ICMS</label><input class="form-control" name="cst_icms" maxlength="4"></div><div class="col-md-2"><label class="form-label">CSOSN</label><input class="form-control" name="csosn" maxlength="4"></div><div class="col-md-2"><label class="form-label">CST PIS</label><input class="form-control" name="cst_pis" maxlength="4"></div><div class="col-md-2"><label class="form-label">CST COFINS</label><input class="form-control" name="cst_cofins" maxlength="4"></div>
        <div class="col-md-4"><label class="form-label">Aliquota ICMS %</label><input class="form-control" name="aliquota_icms" type="number" step="0.0001" value="0"></div><div class="col-md-4"><label class="form-label">Aliquota PIS %</label><input class="form-control" name="aliquota_pis" type="number" step="0.0001" value="0"></div><div class="col-md-4"><label class="form-label">Aliquota COFINS %</label><input class="form-control" name="aliquota_cofins" type="number" step="0.0001" value="0"></div>
        <div class="col-12"><h2 class="h5 mb-0">Reforma Tributaria - IBS/CBS/IS</h2><div class="form-text">Campos preparados para NT 2025.002 RTC. Confirme CST, classificacao tributaria e aliquotas com a contabilidade.</div></div>
        <div class="col-md-3"><label class="form-label">CST IBS/CBS</label><input class="form-control" name="cst_ibs_cbs" maxlength="6"></div><div class="col-md-3"><label class="form-label">Class. trib. IBS/CBS</label><input class="form-control" name="cclass_trib_ibs_cbs" maxlength="12"></div><div class="col-md-2"><label class="form-label">IBS UF %</label><input class="form-control" name="aliquota_ibs_uf" type="number" step="0.0001" value="0"></div><div class="col-md-2"><label class="form-label">IBS Mun. %</label><input class="form-control" name="aliquota_ibs_municipal" type="number" step="0.0001" value="0"></div><div class="col-md-2"><label class="form-label">CBS %</label><input class="form-control" name="aliquota_cbs" type="number" step="0.0001" value="0"></div>
        <div class="col-md-4"><label class="form-label">CST IS</label><input class="form-control" name="cst_is" maxlength="6"></div><div class="col-md-4"><label class="form-label">Class. trib. IS</label><input class="form-control" name="cclass_trib_is" maxlength="12"></div><div class="col-md-4"><label class="form-label">Aliquota IS %</label><input class="form-control" name="aliquota_is" type="number" step="0.0001" value="0"></div>
        <div class="col-md-3"><label class="form-label">Preco</label><input class="form-control" name="preco" type="number" step="0.01" required></div><div class="col-md-3"><label class="form-label">Promocional</label><input class="form-control" name="preco_promocional" type="number" step="0.01"></div><div class="col-md-3"><label class="form-label">Custo</label><input class="form-control" name="custo" type="number" step="0.01"></div><div class="col-md-3"><label class="form-label">Estoque</label><input class="form-control" name="estoque" type="number"></div>
        <div class="col-md-3"><label class="form-label">Peso kg</label><input class="form-control" name="peso_kg" type="number" step="0.001"></div><div class="col-md-3"><label class="form-label">Altura cm</label><input class="form-control" name="altura_cm" type="number" step="0.01"></div><div class="col-md-3"><label class="form-label">Largura cm</label><input class="form-control" name="largura_cm" type="number" step="0.01"></div><div class="col-md-3"><label class="form-label">Comprimento cm</label><input class="form-control" name="comprimento_cm" type="number" step="0.01"></div>
        <div class="col-12"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="ativo" checked><label class="form-check-label">Ativo</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="destaque"><label class="form-check-label">Destaque</label></div></div>
        <div class="col-12"><button class="btn btn-brand">Salvar produto</button></div>
    </form></div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
