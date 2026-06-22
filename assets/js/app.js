document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (event) => {
        if (!confirm(el.dataset.confirm || 'Confirmar acao?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.js-auto-submit').forEach((el) => {
    el.addEventListener('change', () => el.closest('form')?.submit());
});

const onlyDigits = (value) => value.replace(/\D/g, '');

const masks = {
    cep(value) {
        return onlyDigits(value).slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2');
    },
    phone(value) {
        const digits = onlyDigits(value).slice(0, 11);
        if (digits.length <= 10) {
            return digits.replace(/(\d{2})(\d{4})(\d{0,4})/, (_, a, b, c) => c ? `(${a}) ${b}-${c}` : `(${a}) ${b}`);
        }
        return digits.replace(/(\d{2})(\d{5})(\d{0,4})/, (_, a, b, c) => c ? `(${a}) ${b}-${c}` : `(${a}) ${b}`);
    },
    cpf(value) {
        return onlyDigits(value).slice(0, 11)
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    },
    cnpj(value) {
        return onlyDigits(value).slice(0, 14)
            .replace(/(\d{2})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1/$2')
            .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    },
    cpfcnpj(value) {
        const digits = onlyDigits(value);
        return digits.length > 11 ? masks.cnpj(value) : masks.cpf(value);
    },
    ncm(value) {
        return onlyDigits(value).slice(0, 8);
    },
    fiscalCode(value) {
        return onlyDigits(value).slice(0, 4);
    },
    rtcCode(value) {
        return onlyDigits(value).slice(0, 6);
    },
    cclass(value) {
        return onlyDigits(value).slice(0, 12);
    },
    percent(value) {
        return value.replace(/[^0-9.,]/g, '').replace(',', '.').slice(0, 10);
    },
    uf(value) {
        return value.replace(/[^a-z]/gi, '').slice(0, 2).toUpperCase();
    },
    money(value) {
        const digits = onlyDigits(value);
        const amount = (Number(digits || 0) / 100).toFixed(2);
        return amount.replace('.', ',');
    },
};

function inferMask(input) {
    const name = (input.name || input.id || '').toLowerCase();
    if (input.dataset.mask) return input.dataset.mask;
    if (name.includes('cpf') || name.includes('documento')) return 'cpfcnpj';
    if (name.includes('cnpj')) return 'cnpj';
    if (name.includes('telefone') || name.includes('celular')) return 'phone';
    if (name.includes('cep')) return 'cep';
    if (name === 'uf' || name.endsWith('_uf')) return 'uf';
    if (name.includes('ncm')) return 'ncm';
    if (name.includes('cst_ibs') || name.includes('cst_is')) return 'rtcCode';
    if (name.includes('cclass_trib')) return 'cclass';
    if (name.includes('cfop') || name.includes('cst_') || name.includes('csosn')) return 'fiscalCode';
    if (name.includes('aliquota')) return 'percent';
    return '';
}

document.querySelectorAll('input').forEach((input) => {
    const mask = inferMask(input);
    if (!mask || !masks[mask]) return;
    input.addEventListener('input', () => {
        const start = input.selectionStart;
        input.value = masks[mask](input.value);
        input.setSelectionRange(input.value.length, input.value.length);
    });
    input.value = masks[mask](input.value);
});

const paymentSelect = document.getElementById('formaPagamento');
const cardTokenBox = document.getElementById('cardTokenBox');
if (paymentSelect && cardTokenBox) {
    const toggleCardToken = () => {
        cardTokenBox.style.display = paymentSelect.value === 'cartao_credito' ? 'block' : 'none';
    };
    paymentSelect.addEventListener('change', toggleCardToken);
    toggleCardToken();
}

const chatWidget = document.getElementById('afetoChatWidget');
const chatToggle = document.getElementById('afetoChatToggle');
const chatClose = document.getElementById('afetoChatClose');
const chatPanel = document.getElementById('afetoChatPanel');
const chatBody = document.getElementById('afetoChatBody');
const chatInput = document.getElementById('afetoChatInput');
const chatSend = document.getElementById('afetoChatSend');

if (chatWidget && chatToggle && chatClose && chatPanel && chatBody && chatInput && chatSend) {
    const responses = [
        { matcher: /como funciona.*doula|doula/i, text: 'A nossa doula oferece apoio antes, durante e depois do parto. Ela ajuda com preparação emocional, presença no parto, orientação prática para amamentação e apoio à família no pós-parto. É um acolhimento humano que traz mais segurança e confiança.' },
        { matcher: /quais serviços|serviços|servicos|o que vocês oferecem|oferecem/i, text: 'Oferecemos atendimento materno, suporte em amamentação e pós-parto, taping pós-parto, cuidados de furinho humanizado e serviço de doula. Também indicamos parceiros confiáveis e temos uma curadoria de produtos para cada fase da maternidade.' },
        { matcher: /agendar|agenda|marcar|atendimento/i, text: 'Para agendar, você pode enviar uma mensagem no WhatsApp, falar direto com nossa equipe e escolher o melhor dia e horário. Também ajudamos a definir o serviço mais adequado para sua fase materna.' },
        { matcher: /milena|parceira|parceiros|parceiro/i, text: 'Milena atua como doula parceira, com atendimento acolhedor e empático. Ela acompanha gestantes e puérperas com suporte emocional, orientação prática e presença humana durante esse momento especial.' },
        { matcher: /amamenta|amamentação|peg a|mama/i, text: 'No apoio à amamentação, trabalhamos para melhorar a pega, reduzir desconfortos e aumentar a segurança da mãe. Também oferecemos orientações sobre rotina, conforto do bebê e suporte à família para o momento de amamentar.' },
        { matcher: /pós-?parto|pos-?parto|recuperação|recuperacao/i, text: 'O pós-parto pode ser um período desafiador. Nosso suporte inclui orientação sobre cuidados do bebê, autocuidado da mãe, organização da rotina e acolhimento emocional para você e sua família.' },
        { matcher: /furinho|umbigo|cuidado.*umbigo/i, text: 'O cuidado com o furinho humanizado é feito com atenção e delicadeza. Orientamos limpeza, sinais de alerta e como deixar esse momento mais tranquilo para mãe e bebê.' },
        { matcher: /produto|loja|catalogo|produtos/i, text: 'Temos uma curadoria de produtos para amamentação, pós-parto e bebê. Você pode conhecer o catálogo online e receber indicações de itens que combinam com sua fase e suas necessidades.' },
        { matcher: /onde|local|atendimento presencial|online/i, text: 'Nosso atendimento é pensado para acolher você com flexibilidade, oferecendo suporte presencial quando possível e orientação online quando for melhor para sua rotina.' },
        { matcher: /preço|valor|custo|quanto custa/i, text: 'Os valores variam conforme o serviço e o tempo de atendimento. Para uma proposta personalizada, fale conosco pelo WhatsApp e podemos indicar o pacote mais adequado para você.' },
        { matcher: /whatsapp|contato|falar/i, text: 'O melhor caminho para contato imediato é pelo WhatsApp. Lá você pode tirar dúvidas, agendar atendimento ou pedir orientação rápida com nossa equipe materna.' },
    ];

    const appendMessage = (text, type) => {
        const message = document.createElement('div');
        message.className = `chat-message ${type}`;
        message.textContent = text;
        chatBody.appendChild(message);
        chatBody.scrollTop = chatBody.scrollHeight;
    };

    const getAnswer = (text) => {
        for (const response of responses) {
            if (response.matcher.test(text)) {
                return response.text;
            }
        }
        return 'Estou aqui para acolher! Se quiser, escreva sua dúvida com palavras como “amamentação”, “pós-parto”, “doula” ou “serviço”, e eu te respondo com mais detalhes.';
    };

    const showPanel = (open) => {
        if (open) {
            chatWidget.classList.add('open');
            chatToggle.setAttribute('aria-expanded', 'true');
            chatPanel.setAttribute('aria-hidden', 'false');
            chatInput.focus();
        } else {
            chatWidget.classList.remove('open');
            chatToggle.setAttribute('aria-expanded', 'false');
            chatPanel.setAttribute('aria-hidden', 'true');
        }
    };

    chatToggle.addEventListener('click', () => showPanel(!chatWidget.classList.contains('open')));
    chatClose.addEventListener('click', () => showPanel(false));

    const sendChat = () => {
        const value = chatInput.value.trim();
        if (!value) return;
        appendMessage(value, 'user');
        chatInput.value = '';
        setTimeout(() => {
            appendMessage(getAnswer(value), 'bot');
        }, 650);
    };

    const chatSuggestions = document.querySelectorAll('.chat-suggestion');
    chatSuggestions.forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.textContent.trim();
            chatInput.value = value;
            sendChat();
        });
    });

    chatSend.addEventListener('click', sendChat);
    chatInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendChat();
        }
    });
}
