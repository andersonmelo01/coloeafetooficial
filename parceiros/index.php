<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$parceiros = partner_catalog();

$pageTitle = 'Parceiros';
$active = 'home';
$bodyClass = 'partners-page';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="afeto-section afeto-parceiros afeto-partners-listing">
    <div class="container">
        <div class="text-center mb-5">
            <span class="afeto-section-tag">Parcerias</span>
            <h1>Parceiros conveniados e credenciados</h1>
            <p class="txt_global mx-auto" style="max-width: 640px;">Indicamos profissionais e empresas confiáveis para acompanhar cada fase da sua jornada materna.</p>
        </div>

        <?php if ($parceiros): ?>
            <div class="row g-4 align-items-stretch">
                <?php foreach ($parceiros as $partner): ?>
                    <div class="col-sm-12 col-md-6 col-lg-4 d-flex">
                        <article class="afeto-testimonial h-100 partner-card w-100">
                            <h2 class="h4"><a class="partner-name" href="<?= e($partner['profile_url']) ?>"><?= e($partner['name']) ?></a></h2>
                            <div class="afeto-stars">★★★★★</div>
                            <img src="<?= e($partner['image']) ?>" alt="<?= e($partner['name']) ?>" class="brand-parceiros-img">
                            <p class="txt_global"><?= e($partner['summary']) ?></p>
                            <div class="partner-actions">
                                <a class="btn btn-outline-brand btn-sm" href="<?= e($partner['profile_url']) ?>">Ver perfil</a>
                                <?php if (!empty($partner['whatsapp_url'])): ?>
                                    <a class="btn btn-whatsapp btn-sm" href="<?= e($partner['whatsapp_url']) ?>" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center py-5">Em breve mais parceiros por aqui.</div>
        <?php endif; ?>

        <div class="text-center mt-5">
            <a class="btn btn-brand" href="<?= e(base_url('home.php#contato')) ?>"><i class="bi bi-chat-heart"></i> Quero ser parceiro</a>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>