<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$services = service_catalog();
$selectedSlug = (string) ($_GET['servico'] ?? $_POST['servico_slug'] ?? array_key_first($services));

if (!isset($services[$selectedSlug])) {
    $selectedSlug = (string) array_key_first($services);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $selectedSlug = (string) ($_POST['servico_slug'] ?? $selectedSlug);
    if (!isset($services[$selectedSlug])) {
        flash('danger', 'Serviço inválido.');
        redirect('admin/servicos/index.php');
    }

    $action = (string) ($_POST['acao'] ?? '');

    if ($action === 'adicionar_imagem') {
        $title = trim((string) ($_POST['titulo'] ?? ''));
        $description = trim((string) ($_POST['descricao'] ?? ''));

        if ($title === '') {
            flash('warning', 'Informe um título para a foto.');
            redirect('admin/servicos/index.php?servico=' . urlencode($selectedSlug));
        }

        $saved = service_upload_gallery_image($selectedSlug, $title, $description);
        flash($saved ? 'success' : 'warning', $saved ? 'Foto adicionada à galeria.' : 'Nenhuma imagem válida foi enviada.');
        redirect('admin/servicos/index.php?servico=' . urlencode($selectedSlug));
    }

    if ($action === 'atualizar_imagem') {
        $updated = service_update_gallery_image(
            (int) ($_POST['imagem_id'] ?? 0),
            $selectedSlug,
            trim((string) ($_POST['titulo'] ?? '')),
            trim((string) ($_POST['descricao'] ?? '')),
            (int) ($_POST['ordem'] ?? 0),
            isset($_POST['ativo'])
        );
        flash($updated ? 'success' : 'warning', $updated ? 'Foto atualizada.' : 'Foto não encontrada.');
        redirect('admin/servicos/index.php?servico=' . urlencode($selectedSlug));
    }

    if ($action === 'excluir_imagem') {
        $deleted = service_delete_gallery_image((int) ($_POST['imagem_id'] ?? 0), $selectedSlug);
        flash($deleted ? 'success' : 'warning', $deleted ? 'Foto removida.' : 'Foto não encontrada.');
        redirect('admin/servicos/index.php?servico=' . urlencode($selectedSlug));
    }

    redirect('admin/servicos/index.php?servico=' . urlencode($selectedSlug));
}

$selectedService = $services[$selectedSlug];
$images = service_gallery_images($selectedSlug);
$pageTitle = 'Galerias de Serviços';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div>
            <div class="col-lg-9">
                <div class="panel-card bg-white p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                        <div>
                            <h1 class="h3 section-title mb-1">Galerias de serviços</h1>
                            <p class="text-secondary mb-0">Adicione fotos com título e breve descrição para cada serviço exibido no site.</p>
                        </div>
                        <form method="get" class="service-admin-select">
                            <label class="form-label small" for="servico">Serviço</label>
                            <select class="form-select js-auto-submit" id="servico" name="servico">
                                <?php foreach ($services as $slug => $service): ?>
                                    <option value="<?= e($slug) ?>" <?= $slug === $selectedSlug ? 'selected' : '' ?>><?= e($service['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <div class="service-admin-current mb-4">
                        <span><i class="bi <?= e($selectedService['icon']) ?>"></i></span>
                        <div>
                            <h2 class="h5 mb-1"><?= e($selectedService['title']) ?></h2>
                            <p class="mb-0"><?= e($selectedService['subtitle']) ?></p>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="adicionar_imagem">
                        <input type="hidden" name="servico_slug" value="<?= e($selectedSlug) ?>">
                        <div class="col-md-6">
                            <label class="form-label">Título da foto</label>
                            <input class="form-control" name="titulo" maxlength="160" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Imagem</label>
                            <input class="form-control" name="imagem" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Breve descrição</label>
                            <textarea class="form-control" name="descricao" rows="3" maxlength="255"></textarea>
                            <div class="form-text">Use até 255 caracteres. Formatos aceitos: JPG, PNG, WEBP ou GIF, com até 5 MB.</div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-brand"><i class="bi bi-cloud-arrow-up"></i> Adicionar foto</button>
                        </div>
                    </form>
                </div>

                <div class="panel-card bg-white p-4 mt-4">
                    <h2 class="h4 section-title">Fotos cadastradas</h2>
                    <?php if ($images): ?>
                        <div class="service-admin-gallery mt-4">
                            <?php foreach ($images as $image): ?>
                                <article class="service-admin-card">
                                    <img src="<?= e(base_url($image['caminho'])) ?>" alt="<?= e($image['titulo']) ?>">
                                    <form method="post" class="row g-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="atualizar_imagem">
                                        <input type="hidden" name="servico_slug" value="<?= e($selectedSlug) ?>">
                                        <input type="hidden" name="imagem_id" value="<?= (int) $image['id'] ?>">
                                        <div class="col-12">
                                            <label class="form-label small">Título</label>
                                            <input class="form-control" name="titulo" value="<?= e($image['titulo']) ?>" maxlength="160" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small">Descrição</label>
                                            <textarea class="form-control" name="descricao" rows="3" maxlength="255"><?= e($image['descricao']) ?></textarea>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">Ordem</label>
                                            <input class="form-control" name="ordem" type="number" value="<?= (int) $image['ordem'] ?>">
                                        </div>
                                        <div class="col-6 d-flex align-items-end">
                                            <label class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="ativo" <?= (int) $image['ativo'] ? 'checked' : '' ?>>
                                                <span class="form-check-label">Ativa</span>
                                            </label>
                                        </div>
                                        <div class="col-12 d-grid gap-2">
                                            <button class="btn btn-outline-brand btn-sm"><i class="bi bi-save"></i> Salvar</button>
                                        </div>
                                    </form>
                                    <form method="post" class="mt-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="excluir_imagem">
                                        <input type="hidden" name="servico_slug" value="<?= e($selectedSlug) ?>">
                                        <input type="hidden" name="imagem_id" value="<?= (int) $image['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm w-100" data-confirm="Excluir esta foto?"><i class="bi bi-trash"></i> Excluir</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary mb-0">Nenhuma foto cadastrada para este serviço.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
