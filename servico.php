<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$services = service_catalog();
$service = $services[$slug] ?? null;

if (!$service) {
    header('Location: ' . base_url('home.php') . '#servicos');
    exit;
}

$gallery = service_gallery_images($slug, true);
$pageTitle = $service['title'];
$active = 'home';
$bodyClass = 'service-page';

require_once __DIR__ . '/includes/header.php';
?>

<section class="afeto-section afeto-service-detail">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="afeto-section-tag text-orange">Serviço</span>
                <h1><?= e($service['title']) ?></h1>
                <p class="afeto-service-intro"><?= e($service['subtitle']) ?></p>
                <?php foreach ($service['description'] as $paragraph): ?>
                    <p><?= e($paragraph) ?></p>
                <?php endforeach; ?>
                <ul class="afeto-service-highlights list-unstyled">
                    <?php foreach ($service['highlights'] as $item): ?>
                        <li><i class="bi bi-check2-circle text-orange"></i> <?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                    <?php if ($slug === 'doula'): ?>
                        <a class="btn btn-whatsapp btn-lg" href="https://wa.me/5522988441463" target="_blank"><i class="bi bi-whatsapp"></i> Falar pelo WhatsApp</a>
                    <?php else: ?>
                        <a class="btn btn-whatsapp btn-lg" href="https://wa.me/5521982846871" target="_blank"><i class="bi bi-whatsapp"></i> Falar pelo WhatsApp</a>    
                    <?php endif; ?>
                    <a class="btn btn-outline-brand btn-lg" href="<?= e(base_url('home.php#servicos')) ?>">Voltar aos serviços</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="afeto-service-box p-4">
                    <div class="afeto-service-icon bg-light rounded-circle mb-4">
                        <i class="bi <?= e($service['icon']) ?>"></i>
                    </div>
                    <div class="afeto-service-summary">
                        <h2>Por que escolher este serviço?</h2>
                        <p>O serviço foi pensado para combinar acolhimento, informações práticas e suporte direto, com muita escuta e respeito pela sua jornada.</p>
                        <p>É ideal para quem deseja um cuidado materno em que a confiança e a organização caminham juntas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($gallery): ?>
    <section class="afeto-section afeto-service-gallery">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                <div>
                    <span class="afeto-section-tag text-orange">Galeria</span>
                    <h2>Momentos do serviço</h2>
                </div>
                <p class="afeto-gallery-lead mb-0">Fotos cadastradas pela equipe para apresentar detalhes, ambientes e registros desse cuidado.</p>
            </div>
            <div class="service-gallery-grid">
                <?php foreach ($gallery as $image): ?>
                    <article class="service-gallery-card">
                        <img src="<?= e(base_url($image['caminho'])) ?>" alt="<?= e($image['titulo']) ?>">
                        <div>
                            <h3><?= e($image['titulo']) ?></h3>
                            <?php if (!empty($image['descricao'])): ?>
                                <p><?= e($image['descricao']) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php';
