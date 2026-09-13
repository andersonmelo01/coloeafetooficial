<?php
/** @var array|null $partner Parceiro sendo editado, ou null para cadastro novo. */

$partner = $partner ?? null;
$acao = $acao ?? 'salvar';
$route = ($acao === 'criar') ? 'admin/parceiros/novo.php' : 'admin/parceiros/editar.php';

function partner_safe(array $data, string $key, string $default = ''): string
{
    return trim((string) ($data[$key] ?? $default));
}

function partner_field(string $name, ?array $partner, string $default = ''): string
{
    if (is_array($partner) && array_key_exists($name, $partner)) {
        return e((string) $partner[$name]);
    }
    return e($default);
}

$valorAtivo = is_array($partner) ? (int) ($partner['ativo'] ?? 1) : 1;
?>

<form method="post" enctype="multipart/form-data" class="row g-4">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="<?= e($acao) ?>">
    <?php if (is_array($partner)): ?>
        <input type="hidden" name="id" value="<?= (int) $partner['id'] ?>">
    <?php endif; ?>

    <div class="col-lg-8">
        <div class="panel-card bg-white p-4">
            <h2 class="h5 section-title">Dados do parceiro</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input class="form-control" name="nome" required maxlength="160" value="<?= partner_field('nome', $partner) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Papel / área de atuação</label>
                    <input class="form-control" name="papel" maxlength="120" value="<?= partner_field('papel', $partner) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Resumo (card da home / site)</label>
                    <textarea class="form-control" name="resumo" rows="3" maxlength="255"><?= partner_field('resumo', $partner) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição da página do perfil</label>
                    <textarea class="form-control" name="descricao" rows="5"><?= partner_field('descricao', $partner) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Palavras-chave (separadas por vírgula — usadas no chat)</label>
                    <input class="form-control" name="keywords" maxlength="255" value="<?= partner_field('keywords', $partner) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="panel-card bg-white p-4">
            <h2 class="h5 section-title">Imagem e contatos</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Imagem do parceiro</label>
                    <?php
                    $img = partner_safe(is_array($partner) ? $partner : [], 'imagem');
                    $imgUrl = $img;
                    if ($imgUrl !== '' && !preg_match('#^https?://#i', $imgUrl)) {
                        $imgUrl = base_url($imgUrl);
                    }
                    if ($imgUrl !== ''): ?>
                        <img src="<?= e($imgUrl) ?>" alt="" class="img-fluid rounded-4 mb-2" style="max-height:160px;object-fit:cover;">
                    <?php endif; ?>
                    <input class="form-control" type="file" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
                    <div class="form-text">JPG, PNG, WebP ou GIF (máx. 5 MB).</div>
                </div>

                <div class="col-12">
                    <label class="form-label">WhatsApp (URL wa.me)</label>
                    <input class="form-control" name="whatsapp_url" value="<?= partner_field('whatsapp_url', $partner) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Site / perfil público</label>
                    <input class="form-control" name="site_url" value="<?= partner_field('site_url', $partner) ?>" placeholder="https://... (em branco usa a página interna)">
                </div>
                <div class="col-12">
                    <label class="form-label">Instagram</label>
                    <input class="form-control" name="instagram_url" value="<?= partner_field('instagram_url', $partner) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Facebook</label>
                    <input class="form-control" name="facebook_url" value="<?= partner_field('facebook_url', $partner) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">LinkedIn</label>
                    <input class="form-control" name="linkedin_url" value="<?= partner_field('linkedin_url', $partner) ?>">
                </div>

                <div class="col-6">
                    <label class="form-label">Ordem de exibição</label>
                    <input class="form-control" name="ordem" type="number" min="0" value="<?= is_array($partner) ? (int) ($partner['ordem'] ?? 0) : '0' ?>">
                </div>
                <div class="col-6 d-flex align-items-end">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="ativo" <?= $valorAtivo ? 'checked' : '' ?>> Ativo
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/parceiros/index.php')) ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button class="btn btn-brand"><i class="bi bi-check2-circle"></i> Salvar parceiro</button>
        </div>
    </div>
</form>