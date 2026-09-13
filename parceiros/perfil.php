<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$partner = $slug !== ''
    ? db_one("SELECT * FROM parceiros WHERE slug = :slug AND ativo = 1", ['slug' => $slug])
    : null;

if (!$partner) {
    flash('warning', 'Parceiro não encontrado.');
    redirect('parceiros/index.php');
}

$imagem = (string) ($partner['imagem'] ?? '');
if ($imagem !== '' && !preg_match('#^https?://#i', $imagem)) {
    $imagem = base_url($imagem);
}

$whatsapp = (string) ($partner['whatsapp_url'] ?? '');
$site = (string) ($partner['site_url'] ?? '');
$instagram = (string) ($partner['instagram_url'] ?? '');
$facebook = (string) ($partner['facebook_url'] ?? '');
$linkedin = (string) ($partner['linkedin_url'] ?? '');

$pageTitle = (string) $partner['nome'];
$active = 'home';
$bodyClass = 'partner-page';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="afeto-section afeto-partner-detail">
    <div class="container p-0">
        <div class="row align-items-center g-5">
            <div class="col-10 col-md-6 col-lg-5 mx-auto text-center mb-4 mb-lg-0">
                <?php if ($imagem !== ''): ?>
                    <img src="<?= e($imagem) ?>" alt="<?= e($partner['nome']) ?>" class="img-fluid rounded-4 shadow-sm">
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-7">
                <div class="partner-contact-block mt-0 p-2 p-md-2 rounded-5 shadow-sm bg-white border-0">
                    <span class="afeto-section-tag">Parceiro(a)</span>
                    <div class="partner-contact-block mt-1 rounded-5 shadow-sm border-0 overflow-hidden position-relative">
                    <div class="partner-contact-glow"></div>
                    <div class="row g-4 align-items-center">
                        <h1><?= e($partner['nome']) ?></h1>
                        <?php if (trim((string) $partner['papel']) !== ''): ?>
                            <div class="font-mark" style="margin-top:-1rem;"><?= e($partner['papel']) ?></div>
                        <?php endif; ?>
                        <p class="afeto-service-intro"><?= e((string) ($partner['descricao'] !== '' ? $partner['descricao'] : $partner['resumo'])) ?></p>
                        <div class="col-md-7">
                            <h2>Fale direto com <?= e(strtok((string) $partner['nome'], '-')) ?></h2>
                            <p>Converse para tirar dúvidas, agendar seu atendimento ou saber mais sobre o serviço oferecido, com atendimento humanizado e acolhedor.</p>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <span class="partner-contact-pill">Atendimento parceiro</span>
                        </div>
                    </div>
                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                            <div class="partner-contact-card">
                                <span class="contact-card-title">Contato direto</span>
                                <?php $wa = $whatsapp !== '' ? $whatsapp : 'https://wa.me/'; ?>
                                <a href="<?= e($wa) ?>" target="_blank" class="btn btn-success rounded-pill px-4 py-2"><i class="bi bi-whatsapp"></i> Solicitar orçamento</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="partner-contact-card">
                                <span class="contact-card-title">Redes sociais</span>
                                <div class="partner-socials">
                                    <?php if ($whatsapp !== ''): ?><a href="<?= e($whatsapp) ?>" target="_blank" title="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
                                    <?php if ($site !== ''): ?><a href="<?= e($site) ?>" target="_blank" title="Site pessoal"><i class="bi bi-globe2"></i></a><?php endif; ?>
                                    <?php if ($instagram !== ''): ?><a href="<?= e($instagram) ?>" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a><?php endif; ?>
                                    <?php if ($facebook !== ''): ?><a href="<?= e($facebook) ?>" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a><?php endif; ?>
                                    <?php if ($linkedin !== ''): ?><a href="<?= e($linkedin) ?>" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>