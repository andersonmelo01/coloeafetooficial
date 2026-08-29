```php
<?php
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'AMS Sistemas';
$active = 'home';
$bodyClass = 'partner-page';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="afeto-section afeto-partner-detail">
    <div class="container p-0">
        <div class="row align-items-center g-5">

            <!-- IMAGEM / LOGO -->
            <div class="col-lg-5 text-center">
                <img
                    src="<?= e(base_url('img/logoAms.png')) ?>"
                    alt="AMS Sistemas"
                    class="img-fluid rounded-4 shadow-sm"
                >
            </div>

            <!-- CONTEÚDO -->
            <div class="col-lg-7">

                <div class="partner-contact-block mt-0 p-2 p-md-2 rounded-5 shadow-sm bg-white border-0">

                    <span class="afeto-section-tag">AMS Sistemas</span>

                    <div class="partner-contact-block mt-1 rounded-5 shadow-sm border-0 overflow-hidden position-relative">

                        <div class="partner-contact-glow"></div>

                        <div class="row g-4 align-items-center">

                            <div class="col-12">

                                <h1>AMS Sistemas</h1>

                                <p class="afeto-service-intro">
                                    Soluções em informática, criação de sites,
                                    desenvolvimento de sistemas e gestão de
                                    conteúdo digital para empresas e profissionais.
                                </p>

                            </div>

                            <div class="col-md-7">

                                <h2>Transforme sua presença digital</h2>

                                <p>
                                    A AMS Sistemas oferece soluções personalizadas
                                    para empresas, profissionais e empreendedores
                                    que desejam melhorar sua presença na internet,
                                    automatizar processos e utilizar a tecnologia
                                    para crescer.
                                </p>

                            </div>

                            <div class="col-md-5 text-md-end">

                                <span class="partner-contact-pill">
                                    Soluções digitais
                                </span>

                            </div>

                        </div>

                        <!-- SERVIÇOS -->
                        <div class="row g-3 mt-4">

                            <div class="col-md-6">
                                <div class="partner-contact-card">

                                    <span class="contact-card-title">
                                        Nossos serviços
                                    </span>

                                    <div class="mt-3">

                                        <p class="mb-2">
                                            <i class="bi bi-globe2"></i>
                                            <strong>Criação de Sites</strong>
                                        </p>

                                        <p class="mb-2">
                                            <i class="bi bi-code-slash"></i>
                                            <strong>Desenvolvimento de Sistemas</strong>
                                        </p>

                                        <p class="mb-2">
                                            <i class="bi bi-megaphone"></i>
                                            <strong>Gestão de Conteúdo Digital</strong>
                                        </p>

                                        <p class="mb-2">
                                            <i class="bi bi-pc-display"></i>
                                            <strong>Soluções em Informática</strong>
                                        </p>

                                        <p class="mb-0">
                                            <i class="bi bi-tools"></i>
                                            <strong>Manutenção e Suporte</strong>
                                        </p>

                                    </div>

                                </div>
                            </div>

                            <!-- CONTATO -->
                            <div class="col-md-6">

                                <div class="partner-contact-card">

                                    <span class="contact-card-title">
                                        Fale com a AMS
                                    </span>

                                    <p class="mt-3 mb-3">
                                        Solicite um orçamento ou converse
                                        conosco sobre seu projeto.
                                    </p>

                                    <div class="partner-socials">

                                        <a
                                            href="https://wa.me/5521982846871"
                                            target="_blank"
                                            title="WhatsApp"
                                        >
                                            <i class="bi bi-whatsapp"></i>
                                        </a>

                                        <a
                                            href="mailto:amssistemas95@gmail.com"
                                            title="E-mail"
                                        >
                                            <i class="bi bi-envelope"></i>
                                        </a>

                                        <a
                                            href="https://instagram.com/@amstecnologia95"
                                            target="_blank"
                                            title="Instagram"
                                        >
                                            <i class="bi bi-instagram"></i>
                                        </a>

                                        <a
                                            href="https://linkedin.com/company/amssistemas"
                                            target="_blank"
                                            title="LinkedIn"
                                        >
                                            <i class="bi bi-linkedin"></i>
                                        </a>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- CTA -->
                        <div class="text-center mt-4">

                            <a href="https://wa.me/5521982846871" target="_blank" class="btn btn-success rounded-pill px-4 py-2"><i class="bi bi-whatsapp"></i>
                                Solicitar orçamento
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```
