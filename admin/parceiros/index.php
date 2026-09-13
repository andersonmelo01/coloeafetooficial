<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'status') {
        db()->prepare("UPDATE parceiros SET ativo = :ativo WHERE id = :id")->execute([
            'id' => (int) ($_POST['id'] ?? 0),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ]);
        flash('success', 'Parceiro atualizado.');
        redirect('admin/parceiros/index.php');
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $partner = db_one("SELECT * FROM parceiros WHERE id = :id", ['id' => $id]);
        if ($partner) {
            db()->prepare("DELETE FROM parceiros WHERE id = :id")->execute(['id' => $id]);

            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $partner['imagem']);
            $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path;
            $uploadsRoot = partner_upload_dir();
            $realUploads = realpath($uploadsRoot);
            $realFile = realpath($absolute);
            if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
                unlink($realFile);
            }
            flash('success', 'Parceiro excluído.');
        } else {
            flash('danger', 'Parceiro não encontrado.');
        }
        redirect('admin/parceiros/index.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$parceirosPage = paginate_query(
    "SELECT * FROM parceiros
     WHERE (:q = '' OR nome LIKE :like_q OR papel LIKE :like_q OR resumo LIKE :like_q)
     ORDER BY ordem ASC, nome ASC",
    ['q' => $q, 'like_q' => '%' . $q . '%']
);
$parceiros = $parceirosPage['rows'];

$pageTitle = 'Parceiros';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 section-title mb-1">Parceiros conveniados</h1>
            <p class="text-secondary mb-0">Cadastro usado no site (seção parceiros) e no chat.</p>
        </div>
        <a class="btn btn-brand" href="<?= e(base_url('admin/parceiros/novo.php')) ?>"><i class="bi bi-plus-lg"></i> Novo parceiro</a>
    </div>

    <div class="panel-card bg-white p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div class="form-text"><?= app_config('parceiros.habilitados', '0') === '1' ? 'Visibilidade no site: <strong>habilitada</strong>.' : 'Visibilidade no site: <strong>desabilitada</strong>.' ?> Ajuste em Configurações.</div>
            <form class="search-control" method="get">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nome, papel ou resumo">
                    <button class="btn btn-brand">Buscar</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Imagem</th><th>Nome / papel</th><th>WhatsApp</th><th>Ordem</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($parceiros as $p): ?>
                    <tr>
                        <td>
                            <?php
                            $img = (string) ($p['imagem'] ?? '');
                            if ($img !== '' && !preg_match('#^https?://#i', $img)) {
                                $img = base_url($img);
                            }
                            ?>
                            <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt="<?= e($p['nome']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:.5rem;"><?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($p['nome']) ?></div>
                            <div class="small text-secondary"><?= e($p['papel'] ?? '') ?></div>
                        </td>
                        <td><?= e($p['whatsapp_url'] ?? '') ?: '—' ?></td>
                        <td><?= (int) $p['ordem'] ?></td>
                        <td><?= (int) $p['ativo'] ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-brand" href="<?= e(base_url('admin/parceiros/editar.php?id=' . (int) $p['id'])) ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
                            <form method="post" class="d-inline" data-confirm="Excluir este parceiro? A imagem enviada também será removida.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash3"></i></button>
                            </form>
                            <form method="post" class="d-inline align-middle ms-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="status">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <label class="form-check form-switch mb-0" title="Ativar/inativar">
                                    <input class="form-check-input js-auto-submit" type="checkbox" name="ativo" <?= (int) $p['ativo'] ? 'checked' : '' ?>>
                                </label>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$parceiros): ?><tr><td colspan="6" class="text-center text-secondary py-4">Nenhum parceiro cadastrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($parceirosPage) ?>
    </div>
</div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>