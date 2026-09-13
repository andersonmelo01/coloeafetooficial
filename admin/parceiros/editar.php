<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? 0);
$partner = db_one("SELECT * FROM parceiros WHERE id = :id", ['id' => $id]);

if (!$partner) {
    flash('danger', 'Parceiro não encontrado.');
    redirect('admin/parceiros/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($nome === '') {
        flash('danger', 'Informe o nome do parceiro.');
        redirect('admin/parceiros/editar.php?id=' . $id);
    }
    if ($slug === '') {
        $slug = partner_slugify($nome);
    }
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower(trim($slug)));
    $slug = trim((string) $slug, '-') ?: 'parceiro-' . date('YmdHis');

    $imagem = partner_upload_image('imagem');
    $novoImagem = $imagem !== null ? $imagem : (string) $partner['imagem'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    try {
        db()->prepare(
            "UPDATE parceiros SET
                slug = :slug, nome = :nome, papel = :papel, resumo = :resumo, descricao = :descricao,
                imagem = :imagem, whatsapp_url = :whatsapp_url, site_url = :site_url,
                instagram_url = :instagram_url, facebook_url = :facebook_url, linkedin_url = :linkedin_url,
                keywords = :keywords, ordem = :ordem, ativo = :ativo
             WHERE id = :id"
        )->execute([
            'slug' => $slug,
            'nome' => $nome,
            'papel' => trim((string) ($_POST['papel'] ?? '')),
            'resumo' => trim((string) ($_POST['resumo'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'imagem' => $novoImagem,
            'whatsapp_url' => trim((string) ($_POST['whatsapp_url'] ?? '')),
            'site_url' => trim((string) ($_POST['site_url'] ?? '')),
            'instagram_url' => trim((string) ($_POST['instagram_url'] ?? '')),
            'facebook_url' => trim((string) ($_POST['facebook_url'] ?? '')),
            'linkedin_url' => trim((string) ($_POST['linkedin_url'] ?? '')),
            'keywords' => trim((string) ($_POST['keywords'] ?? '')),
            'ordem' => (int) ($_POST['ordem'] ?? 0),
            'ativo' => $ativo,
            'id' => $id,
        ]);

        if ($imagem !== null) {
            $oldPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $partner['imagem']);
            $oldAbsolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $oldPath;
            $uploadsRoot = partner_upload_dir();
            $realUploads = realpath($uploadsRoot);
            $realFile = realpath($oldAbsolute);
            if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
                @unlink($realFile);
            }
        }

        flash('success', 'Parceiro atualizado.');
        redirect('admin/parceiros/index.php');
    } catch (Throwable $e) {
        flash('danger', 'Não foi possível salvar: ' . $e->getMessage());
    }

    $partner = db_one("SELECT * FROM parceiros WHERE id = :id", ['id' => $id]);
}

$pageTitle = 'Editar parceiro';
$active = 'admin';
$acao = 'salvar';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <h1 class="h3 section-title">Editar parceiro</h1>
    <p class="text-secondary mb-4">Atualize as informações exibidas no site e no chat do Bot.</p>
    <?php require __DIR__ . '/_form.php'; ?>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>