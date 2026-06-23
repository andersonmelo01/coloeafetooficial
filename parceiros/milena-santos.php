<?php
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Milena Santos';
$active = 'home';
$bodyClass = 'partner-page';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="afeto-section afeto-partner-detail">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <img src="<?= e(base_url('img/millena_perfil.jpeg')) ?>" alt="Milena Santos" class="img-fluid rounded-4 shadow-sm">
            </div>
            <div class="col-lg-7">
                
                <div class="partner-contact-block mt-0 p-2 p-md-5 rounded-5 shadow-sm bg-white border-0">
                    <span class="afeto-section-tag">Parceira</span>
                    <div class="partner-contact-block mt-1 rounded-5 shadow-sm border-0 overflow-hidden position-relative">
                    <div class="partner-contact-glow"></div>
                    <div class="row g-4 align-items-center">
                        <h1>Milena Santos</h1>
                        <p class="afeto-service-intro">Doula parceira com experiência em apoio maternal, acolhimento emocional e cuidado humanizado.</p>
                
                        <div class="col-md-7">
                            <h2>Fale direto com a Doula</h2>
                            <p>Converse com a Milena para tirar dúvidas, agendar seu acompanhamento ou saber mais sobre o suporte maternal com acolhimento humano.</p>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <span class="partner-contact-pill">Atendimento direto</span>
                        </div>
                    </div>
                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                            <div class="partner-contact-card">
                                <span class="contact-card-title">Contato direto</span>
                                <a class="contact-link" href="https://wa.me/5522988441463" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                                <a class="contact-link" href="mailto:millenalion2017@gmail.com"><i class="bi bi-envelope"></i> millenalion2017@gmail.com</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="partner-contact-card">
                                <span class="contact-card-title">Redes sociais</span>
                                <div class="partner-socials">
                                    <a href="doula.html" target="_blank" title="Site pessoal"><i class="bi bi-globe2"></i></a>
                                    <a href="https://instagram.com/milena" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
                                    <a href="https://facebook.com/milena" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                                    <a href="https://linkedin.com/in/milena" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
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
