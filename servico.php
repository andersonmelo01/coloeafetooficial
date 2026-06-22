<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$services = [
    'atendimento-materno' => [
        'title' => 'Atendimento materno',
        'subtitle' => 'Escuta acolhedora e orientação prática para você e sua família.',
        'icon' => 'bi-chat-heart',
        'description' => [
            'O atendimento materno na Colo & Afeto foi desenhado para ouvir sua história, suas dúvidas e sua rotina.',
            'Cada encontro valoriza a sua experiência, trazendo apoio emocional, conhecimentos sobre a maternidade e estratégias concretas para aliviar a sobrecarga.',
            'A partir da escuta individual, construímos um plano de cuidado que respeita seu tempo, suas escolhas e o ritmo da sua família.',
        ],
        'highlights' => [
            'Escuta individual com atenção aos seus desafios.',
            'Plano de ação personalizado para rotinas maternas.',
            'Apoio prático em amamentação, sono e cuidados do bebê.',
        ],
    ],
    'amamentacao-pos-parto' => [
        'title' => 'Amamentação e Pós-Parto',
        'subtitle' => 'Apoio completo para o momento da amamentação e a recuperação do pós-parto.',
        'icon' => 'bi-droplet-half',
        'description' => [
            'A amamentação e o pós-parto são fases de grandes mudanças e sensações intensas.',
            'Oferecemos orientação sobre pega, postura, rotina do bebê e estratégias para reduzir desconfortos.',
            'Além disso, abordamos a transição para a nova rotina familiar e como preservar seu bem-estar físico e emocional.',
        ],
        'highlights' => [
            'Ajuda com pega e conforto do bebê.',
            'Dicas para gestão de rotinas e autocuidado.',
            'Apoio para lidar com inseguranças e dúvidas pós-parto.',
        ],
    ],
    'taping-pos-parto' => [
        'title' => 'Taping Pós-Parto',
        'subtitle' => 'Aplicações suaves para suporte corporal e conforto na recuperação.',
        'icon' => 'bi-patch-check-fill',
        'description' => [
            'O taping pós-parto é uma técnica carinhosa que ajuda a reduzir tensões e melhorar a sensação de suporte no corpo.',
            'Realizamos aplicações específicas para aliviar desconfortos, apoiar a postura e promover mais conforto durante os primeiros meses.',
            'O procedimento é indicado com foco no seu bem-estar e no cuidado da sua rotina com o bebê.',
        ],
        'highlights' => [
            'Técnicas de taping adaptadas ao seu corpo.',
            'Alívio de tensões e suporte postural.',
            'Cuidados pensados para o pós-parto imediato e a rotina materna.',
        ],
    ],
    'furinho-humanizado' => [
        'title' => 'Furinho Humanizado',
        'subtitle' => 'Cuidados suaves e seguros para o umbigo do bebê.',
        'icon' => 'bi-heart-pulse',
        'description' => [
            'O cuidado com o furinho do bebê merece atenção e delicadeza desde os primeiros dias.',
            'Compartilhamos práticas seguras, orientações sobre higiene e sinais de atenção para o umbigo do seu pequeno.',
            'Nosso foco é oferecer segurança para os pais, reduzindo as dúvidas comuns e trazendo mais tranquilidade para esse momento.',
        ],
        'highlights' => [
            'Orientação clara sobre higiene e curativos.',
            'Identificação de sinais de alerta com calma e segurança.',
            'Apoio para os primeiros dias com o recém-nascido.',
        ],
    ],
    'doula' => [
        'title' => 'Doula',
        'subtitle' => 'Acolhimento emocional e suporte prático antes, durante e depois do parto.',
        'icon' => 'bi-people-fill',
        'description' => [
            'A doula oferece um apoio afetivo e prático que fortalece sua confiança durante a maternidade.',
            'Este serviço contempla orientação pré-natal, apoio no parto e acompanhamento pós-parto, sempre com escuta sensível.',
            'A presença da doula ajuda a tornar os próximos passos mais seguros e menos solitários.',
        ],
        'highlights' => [
            'Apoio emocional antes, durante e após o parto.',
            'Práticas de conforto, comunicação e tomada de decisão.',
            'Orientação para a família e cuidados com o recém-nascido.',
        ],
    ],
];

$service = $services[$slug] ?? null;

if (!$service) {
    header('Location: ' . base_url('home.php') . '#servicos');
    exit;
}

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
                    <a class="btn btn-whatsapp btn-lg" href="https://wa.me/5521982846871" target="_blank"><i class="bi bi-whatsapp"></i> Falar pelo WhatsApp</a>
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

<?php require_once __DIR__ . '/includes/footer.php';
