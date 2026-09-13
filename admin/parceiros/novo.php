<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($nome === '') {
        flash('danger', 'Informe o nome do parceiro.');
        redirect('admin/parceiros/novo.php');
    }
    if ($slug === '') {
        $slug = partner_slugify($nome);
    }

    $imagem = partner_upload_image('imagem');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    try {
        db()->prepare(
            "INSERT INTO parceiros (slug, nome, papel, resumo, descricao, imagem, whatsapp_url, site_url, instagram_url, facebook_url, linkedin_url, keywords, ordem, ativo)
             VALUES (:slug, :nome, :papel, :resumo, :descricao, :imagem, :whatsapp_url, :site_url, :instagram_url, :facebook_url, :linkedin_url, :keywords, :ordem, :ativo)"
        )->execute([
            'slug' => $slug,
            'nome' => $nome,
            'papel' => trim((string) ($_POST['papel'] ?? '')),
            'resumo' => trim((string) ($_POST['resumo'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'imagem' => $imagem,
            'whatsapp_url' => trim((string) ($_POST['whatsapp_url'] ?? '')),
            'site_url' => trim((string) ($_POST['site_url'] ?? '')),
            'instagram_url' => trim((string) ($_POST['instagram_url'] ?? '')),
            'facebook_url' => trim((string) ($_POST['facebook_url'] ?? '')),
            'linkedin_url' => trim((string) ($_POST['linkedin_url'] ?? '')),
            'keywords' => trim((string) ($_POST['keywords'] ?? '')),
            'ordem' => (int) ($_POST['ordem'] ?? 0),
            'ativo' => $ativo,
        ]);
        flash('success', 'Parceiro cadastrado.');
        redirect('admin/parceiros/index.php');
    } catch (Throwable $e) {
        flash('danger', 'Não foi possível cadastrar: ' . $e->getMessage());
    }
}

$pageTitle = 'Novo parceiro';
$active = 'admin';
$partner = null;
$acao = 'criar';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <h1 class="h3 section-title">Novo parceiro</h1>
    <p class="text-secondary mb-4">Cadastre parceiros conveniados para exibição no site e no chat do Bot.</p>
    <?php require __DIR__ . '/_form.php'; ?>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>