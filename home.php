<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
$active = 'home';
$bodyClass = 'home-page';
$vendasHabilitadas = loja_vendas_enabled();
$homeProdutos = db_all(
    "SELECT p.*, c.nome AS categoria, g.nome AS grupo,
            pr.titulo AS promo_titulo,
            pr.preco_promocional AS promo_preco,
            pr.percentual_desconto AS promo_percentual,
            (SELECT pi.caminho
             FROM produto_imagens pi
             WHERE pi.produto_id = p.id
             ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
             LIMIT 1) AS imagem_principal
     FROM produtos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     LEFT JOIN grupos_produtos g ON g.id = p.grupo_id
     LEFT JOIN promocoes pr ON pr.produto_id = p.id
        AND pr.ativo = 1
        AND pr.destaque = 1
        AND (pr.data_inicio IS NULL OR pr.data_inicio <= CURDATE())
        AND (pr.data_fim IS NULL OR pr.data_fim >= CURDATE())
        AND pr.id = (
            SELECT pr2.id
            FROM promocoes pr2
            WHERE pr2.produto_id = p.id
              AND pr2.ativo = 1
              AND pr2.destaque = 1
              AND (pr2.data_inicio IS NULL OR pr2.data_inicio <= CURDATE())
              AND (pr2.data_fim IS NULL OR pr2.data_fim >= CURDATE())
            ORDER BY pr2.criado_em DESC
            LIMIT 1
        )
     WHERE p.ativo = 1
     ORDER BY pr.destaque DESC, p.destaque DESC, p.criado_em DESC
     LIMIT 3"
);

if (!$homeProdutos) {
    $homeProdutos = sample_products();
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="afeto-hero">
    <div class="afeto-hero-bg"></div>
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="afeto-tag"><i class="bi bi-heart-pulse"></i> consultoria materna e pós-parto</span>
                <h1 class="afeto-title mt-4">
                    Acolhimento profissional para uma maternidade com <span>mais calma e segurança</span>.
                </h1>
                <p class="afeto-subtitle">
                    Orientação humanizada em amamentação, pós-parto e cuidados com o bebê, com uma curadoria de produtos pensada para apoiar cada fase da sua rotina.
                </p>
                <?php if (!$vendasHabilitadas): ?>
                    <div class="alert alert-warning mb-4"><i class="bi bi-info-circle"></i> <?= e(loja_catalog_message()) ?></div>
                <?php endif; ?>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <!-- <a class="btn btn-whatsapp btn-lg" href="#contato"><i class="bi bi-whatsapp"></i> Agendar atendimento</a> -->
                    <a class="btn btn-outline-brand btn-lg" href="<?= e(base_url('loja/index.php')) ?>"><i class="bi bi-shop"></i> Ver produtos</a>
                </div>
                <div class="afeto-stats">
                    <div><strong>01</strong><span>escuta individual</span></div>
                    <div><strong>02</strong><span>orientação prática</span></div>
                    <div><strong>03</strong><span>apoio no pós-venda</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="afeto-visual" aria-label="Identidade visual Colo e Afeto">
                    <div class="afeto-blob">
                        <div class="afeto-logo-mark">
                            <span>Colo</span>
                            <strong>&</strong>
                            <span>Afeto</span>
                        </div>
                        <p>Thais Rocha</p>
                    </div>
                    <div class="afeto-float afeto-float-1"><i class="bi bi-flower1"></i> Aplicação Taping</div>
                    <div class="afeto-float afeto-float-2"><i class="bi bi-droplet"></i> Amamentação</div>
                    <div class="afeto-float afeto-float-3"><i class="bi bi-bag-heart"></i> Furinho Humanizado</div>
                    <div class="afeto-float afeto-float-4"><i class="bi bi-bag-heart"></i> Doula</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="afeto-section afeto-founder" id="fundadora">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 order-lg-2">
                <div class="founder-photo-box rounded-4 shadow-lg overflow-hidden">
                    <img src="<?= e(base_url('img/thais_perfil.jpeg')) ?>" alt="Thais Rocha" class="img-fluid w-100">
                </div>
            </div>
            <div class="col-lg-6 order-lg-1">
                <span class="afeto-section-tag">fundadora</span>
                <h2>Thais Rocha — olhar humano e profissional para a maternidade</h2>
                <p>Com formação dedicada ao cuidado materno, Thais estruturou a Colo & Afeto para oferecer escuta, orientação e acolhimento real às mães em cada fase da jornada.</p>
                <p>Seu trabalho traz atenção pessoal ao pós-parto, à amamentação e ao suporte emocional, criando caminhos mais seguros e tranquilos para a rotina familiar.</p>
                <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                    <a class="btn btn-brand btn-lg" href="https://wa.me/5521986518591" target="_blank"><i class="bi bi-whatsapp"></i> Falar com Thais</a>
                    <a class="btn btn-outline-brand btn-lg" href="#sobre">Conheça o cuidado</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="afeto-section afeto-about" id="sobre">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <div class="afeto-ring">
                    <div><i class="bi bi-person-hearts"></i></div>
                </div>
            </div>
            <div class="col-lg-7">
                <span class="afeto-section-tag">sobre o cuidado</span>
                <h2>Um atendimento pensado para a mãe, o bebê e a rotina real da família.</h2>
                <p>
                    A Colo & Afeto une informação segura, acolhimento e orientação prática para reduzir dúvidas em momentos que costumam trazer ansiedade.
                </p>
                <p>
                    Além do atendimento, você encontra uma curadoria de itens para amamentação, pós-parto e cuidados do bebê, com produtos organizados para facilitar a escolha.
                </p>
                <div class="afeto-about-metrics">
                    <div><strong>01</strong><span>acolher</span></div>
                    <div><strong>02</strong><span>orientar</span></div>
                    <div><strong>03</strong><span>acompanhar</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="afeto-section afeto-services" id="servicos">
    <div class="container position-relative">
        <div class="text-center mb-5">
            <span class="afeto-section-tag text-orange">como posso ajudar</span>
            <h2>Cuidado claro, acolhedor e organizado.</h2>
        </div>
        <div class="row g-4 align-items-stretch">
            <div class="col-sm-6 col-lg-4 d-flex">
                <article class="afeto-service-card h-100 d-flex flex-column justify-content-between w-100">
                    <span>01</span>
                    <div class="service-icon"><i class="bi bi-chat-heart"></i></div>
                    <div class="service-copy">
                        <h3>Atendimento materno</h3>
                        <p>Escuta individual para entender sua fase, suas dúvidas e criar um caminho mais leve para a maternidade.</p>
                        <small>acolhimento · orientação · plano</small>
                    </div>
                    <div class="afeto-service-actions">
                        <a class="btn btn-brand btn-sm" href="<?= e(base_url('servico.php?slug=atendimento-materno')) ?>">Mais detalhes</a>
                        <a class="btn btn-whatsapp btn-sm" href="https://wa.me/5521986518591" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </article>
            </div>
            <div class="col-sm-6 col-lg-4 d-flex">
                <article class="afeto-service-card h-100 d-flex flex-column justify-content-between w-100">
                    <span>02</span>
                    <div class="service-icon"><i class="bi bi-droplet-half"></i></div>
                    <div class="service-copy">
                        <h3>Amamentação e Pós-Parto</h3>
                        <p>Apoio para pega, conforto e organização do cuidado com o bebê e a recuperação da mãe.</p>
                        <small>amamentação · conforto · rotina</small>
                    </div>
                    <div class="afeto-service-actions">
                        <a class="btn btn-brand btn-sm" href="<?= e(base_url('servico.php?slug=amamentacao-pos-parto')) ?>">Mais detalhes</a>
                        <a class="btn btn-whatsapp btn-sm" href="https://wa.me/5521986518591" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </article>
            </div>
            <div class="col-sm-6 col-lg-4 d-flex">
                <article class="afeto-service-card h-100 d-flex flex-column justify-content-between w-100">
                    <span>03</span>
                    <div class="service-icon"><i class="bi bi-patch-check-fill"></i></div>
                    <div class="service-copy">
                        <h3>Taping Pós-Parto</h3>
                        <p>Aplicações seguras para apoio postural, conforto e recuperação do corpo após o parto.</p>
                        <small>suporte · recuperação · bem-estar</small>
                    </div>
                    <div class="afeto-service-actions">
                        <a class="btn btn-brand btn-sm" href="<?= e(base_url('servico.php?slug=taping-pos-parto')) ?>">Mais detalhes</a>
                        <a class="btn btn-whatsapp btn-sm" href="https://wa.me/5521986518591" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </article>
            </div>
            <div class="col-sm-6 col-lg-4 d-flex">
                <article class="afeto-service-card h-100 d-flex flex-column justify-content-between w-100">
                    <span>04</span>
                    <div class="service-icon"><i class="bi bi-heart-pulse"></i></div>
                    <div class="service-copy">
                        <h3>Furinho Humanizado</h3>
                        <p>Orientação personalizada para os cuidados do umbigo do bebê com delicadeza e segurança.</p>
                        <small>cuidado · tranquilidade · proteção</small>
                    </div>
                    <div class="afeto-service-actions">
                        <a class="btn btn-brand btn-sm" href="<?= e(base_url('servico.php?slug=furinho-humanizado')) ?>">Mais detalhes</a>
                        <a class="btn btn-whatsapp btn-sm" href="https://wa.me/5521986518591" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </article>
            </div>
            <div class="col-sm-6 col-lg-4 d-flex">
                <article class="afeto-service-card h-100 d-flex flex-column justify-content-between w-100">
                    <span>05</span>
                    <div class="service-icon"><i class="bi bi-people-fill"></i></div>
                    <div class="service-copy">
                        <h3>Doula</h3>
                        <p>A presença afetiva e prática que acompanha a gestação, parto ou pós-parto com mais confiança.</p>
                        <small>acompanhamento · apoio emocional · orientação</small>
                    </div>
                    <div class="afeto-service-actions">
                        <a class="btn btn-brand btn-sm" href="<?= e(base_url('servico.php?slug=doula')) ?>">Mais detalhes</a>
                        <a class="btn btn-whatsapp btn-sm" href="https://wa.me/5522988441463" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="afeto-section afeto-parceiros" id="parceiros">
    <div class="container">
        <div class="text-center mb-5">
            <span class="afeto-section-tag">Parceiros(as)</span>
            <h2>Parceiros conveniados e credenciados</h2>
        </div>
        <div class="row g-4 align-items-stretch">
            <div class="col-sm-12 col-md-6 col-lg-4 d-flex">
                <article class="afeto-testimonial h-100 partner-card w-100">
                    <h3><a class="partner-name" href="<?= e(base_url('parceiros/milena-santos.php')) ?>">Milena Santos</a></h3>
                    <div class="afeto-stars">★★★★★</div>
                    <img src="img/millena_perfil.jpeg" alt="Milena Santos" class="brand-parceiros-img">
                    <p class="txt_global">Milena atua como doula parceira, oferecendo apoio emocional e prático para gestantes e puérperas.</p>
                    <div class="partner-actions">
                        <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('parceiros/milena-santos.php')) ?>">Ver perfil</a>
                        <a class="btn btn-whatsapp btn-sm" href="https://wa.me/5522988441463" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="afeto-section afeto-shop-preview">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="afeto-section-tag">loja integrada</span>
                <h2><?= $vendasHabilitadas ? 'Produtos que acompanham a jornada materna.' : 'Catálogo de produtos para conhecer e comparar.' ?></h2>
                <p>
                    Veja itens de amamentação, kits, pós-parto e cuidados do bebê em uma vitrine simples de navegar.
                    <?= $vendasHabilitadas ? 'Quando escolher, você pode seguir para o carrinho.' : 'No momento, as vendas online estão pausadas.' ?>
                </p>
                <a class="btn btn-brand" href="<?= e(base_url('loja/index.php')) ?>"><i class="bi bi-bag-heart"></i> <?= $vendasHabilitadas ? 'Ver produtos' : 'Ver catálogo' ?></a>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php foreach ($homeProdutos as $produto): ?>
                        <?php
                        $preco = promotion_price($produto);
                        $slug = (string) ($produto['slug'] ?? '');
                        $href = $slug !== '' ? base_url('loja/produto.php?slug=' . urlencode($slug)) : base_url('loja/index.php');
                        ?>
                        <div class="col-sm-4">
                            <a class="afeto-product-tile text-decoration-none" href="<?= e($href) ?>">
                                <?php if (!empty($produto['imagem_principal'])): ?>
                                    <img class="afeto-product-image" src="<?= e(base_url($produto['imagem_principal'])) ?>" alt="<?= e($produto['nome']) ?>">
                                <?php else: ?>
                                    <i class="bi bi-gift"></i>
                                <?php endif; ?>
                                <span><?= e($produto['nome']) ?></span>
                                <strong><?= $vendasHabilitadas ? money_br($preco) : 'Catálogo' ?></strong>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="afeto-section afeto-testimonials" id="depoimentos">
    <div class="container">
        <div class="text-center mb-5">
            <span class="afeto-section-tag">depoimentos</span>
            <h2>Quando a orientação chega, a rotina respira.</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <article class="afeto-testimonial h-100">
                    <div class="afeto-stars">★★★★★</div>
                    <p>"Me senti ouvida e orientada em cada detalhe. O atendimento trouxe calma."</p>
                    <strong>Cliente pós-parto</strong>
                </article>
            </div>
            <div class="col-md-4">
                <article class="afeto-testimonial h-100 featured">
                    <div class="afeto-stars">★★★★★</div>
                    <p>"A curadoria dos produtos ajuda muito porque faz sentido para a fase que estou vivendo."</p>
                    <strong>Mãe de primeira viagem</strong>
                </article>
            </div>
            <div class="col-md-4">
                <article class="afeto-testimonial h-100">
                    <div class="afeto-stars">★★★★★</div>
                    <p>"Consegui entender melhor o que comprar e quando realmente usar cada item."</p>
                    <strong>Cliente da loja</strong>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="afeto-cta" id="contato">
    <div class="container">
        <div class="afeto-cta-box">
            <span class="afeto-section-tag">vamos conversar</span>
            <h2>Pronta para receber<br><em>cuidado de verdade?</em></h2>
            <p>Entre em contato e agende seu atendimento. A Colo & Afeto existe para caminhar com você nessa fase tão especial da vida.</p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a class="btn btn-brand btn-lg" href="https://wa.me/5521986518591" target="_blank"><i class="bi bi-whatsapp"></i> Falar pelo WhatsApp</a>
                <a href="https://instagram.com" class="btn btn-outline-brand btn-lg" target="_blank"><i class="bi bi-instagram"></i> Ver no Instagram</a>
                <a class="btn btn-outline-brand btn-lg" href="<?= e(base_url('auth/cadastro.php')) ?>"><i class="bi bi-person-plus"></i> Criar cadastro</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
