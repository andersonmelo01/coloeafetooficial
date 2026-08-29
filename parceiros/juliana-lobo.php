<?php
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Juliana Lobo';
$active = 'home';
$bodyClass = 'partner-page';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="afeto-section afeto-partner-detail">
    <div class="container p-0">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <img src="<?= e(base_url('img/juliana_lobo_perfil.jpeg')) ?>" alt="Juliana Lobo" class="img-fluid rounded-4 shadow-sm">
            </div>
            <div class="col-lg-7">
                
                <div class="partner-contact-block mt-0 p-2 p-md-2 rounded-5 shadow-sm bg-white border-0">
                    <span class="afeto-section-tag">Parceira</span>
                    <div class="partner-contact-block mt-1 rounded-5 shadow-sm border-0 overflow-hidden position-relative">
                    <div class="partner-contact-glow"></div>
                    <div class="row g-4 align-items-center">
                        <h1>Juliana Lobo</h1>
                        <p class="afeto-service-intro">Cuidado especializado para bebês, crianças e suas famílias, 
                                                    desde os primeiros dias de vida. Acompanhamento do crescimento e desenvolvimento, 
                                                    prevenção e tratamento de doenças, além de orientações personalizadas para cada fase da infância, 
                                                    com atenção, acolhimento e carinho.</p>
                        <div class="col-md-7">
                            <h2>Fale direto com o consultório da Dra Juliana Lobo</h2>
                            <p>Converse com a secretária para tirar dúvidas, 
                                agendar sua consulta ou saber mais sobre o acompanhamento pediátrico e neonatal, 
                                com atendimento humanizado, acolhedor e dedicado ao cuidado dos pequenos.</p>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <span class="partner-contact-pill">Atendimento direto</span>
                        </div>
                    </div>
                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                            <div class="partner-contact-card">
                                <span class="contact-card-title">Contato direto</span><br>
                                <a href="https://wa.me/5522992456743" target="_blank" class="btn btn-success rounded-pill px-4 py-2"><i class="bi bi-whatsapp"></i>
                                Solicitar orçamento
                                </a>
                            </div>
                    </div>
                        <div class="col-md-6">
                            <div class="partner-contact-card">
                                <span class="contact-card-title">Redes sociais</span>
                                <div class="partner-socials">
                                    <a href="https://wa.me/5522992456743" target="_blank" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                    <a href="julianaLobo.html" target="_blank" title="Site pessoal"><i class="bi bi-globe2"></i></a>
                                    <a href="https://www.instagram.com/julobopediatra?igsi=NnM2c20wMm9iOTJ0" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
                                    <a href="https://facebook.com/" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                                    <a href="https://linkedin.com/" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
