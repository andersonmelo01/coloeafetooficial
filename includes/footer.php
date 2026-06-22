<?php require_once __DIR__ . '/functions.php'; ?>
</main>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <a class="footer-brand" href="<?= e(base_url('home.php')) ?>">
                    <img src="<?= e(base_url('img/logo.jpeg')) ?>" alt="Colo e Afeto">
                    <span>Colo & Afeto</span>
                </a>
                <p class="footer-copy">
                    Acolhimento, orientacao materna e curadoria de produtos para amamentacao, pos-parto e cuidados com o bebe.
                </p>
                <?php if (!loja_vendas_enabled()): ?>
                    <div class="footer-status"><i class="bi bi-info-circle"></i> Loja em modo catalogo</div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-lg-2">
                <h2>Atalhos</h2>
                <a href="<?= e(base_url('home.php#sobre')) ?>">Sobre</a>
                <a href="<?= e(base_url('home.php#servicos')) ?>">Servicos</a>
                <a href="<?= e(base_url('loja/index.php')) ?>"><?= loja_vendas_enabled() ? 'Loja' : 'Catalogo' ?></a>
                <a href="<?= e(base_url('cliente/index.php')) ?>">Area do cliente</a>
            </div>
            <div class="col-6 col-lg-2">
                <h2>Atendimento</h2>
                <a href="<?= e(base_url('auth/cadastro.php')) ?>">Criar cadastro</a>
                <a href="<?= e(base_url('auth/login.php')) ?>">Entrar</a>
                <a href="<?= e(base_url('cliente/chamados.php')) ?>">Chamados</a>
                <a href="<?= e(base_url('admin/index.php')) ?>">Gestor</a>
            </div>
            <div class="col-lg-3">
                <h2>Contato</h2>
                <a class="footer-contact" href="https://wa.me/5521982846871" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                <a class="footer-contact" href="https://instagram.com" target="_blank"><i class="bi bi-instagram"></i> Instagram</a>
                <span class="footer-contact"><i class="bi bi-geo-alt"></i> Atendimento com foco materno-infantil</span>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Colo & Afeto. Todos os direitos reservados.</span>
            <span>Feito para cuidar antes, durante e depois da compra.</span>
        </div>
    </div>
</footer>

<div class="chat-widget chat-closed" id="afetoChatWidget">
    <button type="button" class="chat-toggle" id="afetoChatToggle" aria-expanded="false" aria-controls="afetoChatPanel">
        <span>Ajuda</span>
        <i class="bi bi-chat-dots"></i>
    </button>
    <div class="chat-panel" id="afetoChatPanel" aria-hidden="true">
        <div class="chat-header">
            <div>Precisa de ajuda?</div>
            <button type="button" class="chat-close" id="afetoChatClose" aria-label="Fechar chat">✕</button>
        </div>
        <div class="chat-body" id="afetoChatBody">
            <div class="chat-message bot">Olá! Eu sou a assistente Colo & Afeto. Posso tirar dúvidas sobre serviços, parceiros e atendimento. Clique em uma pergunta ou escreva o que deseja saber.</div>
        </div>
        <div class="chat-suggestions" id="afetoChatSuggestions">
            <button type="button" class="chat-suggestion">Como funciona a doula?</button>
            <button type="button" class="chat-suggestion">Quais serviços vocês oferecem?</button>
            <button type="button" class="chat-suggestion">Como agendar atendimento?</button>
            <button type="button" class="chat-suggestion">O que a Milena faz?</button>
        </div>
        <div class="chat-input-area">
            <input type="text" id="afetoChatInput" placeholder="Escreva sua dúvida..." aria-label="Mensagem de chat">
            <button type="button" class="btn btn-whatsapp" id="afetoChatSend">Enviar</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
</body>
</html>
