document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (event) => {
        if (!confirm(el.dataset.confirm || 'Confirmar acao?')) {
            event.preventDefault();
        }
    });
});

if (document.querySelector('.mobile-tabbar')) {
    document.body.classList.add('has-mobile-tabbar');
}

const mainNav = document.getElementById('mainNav');
if (mainNav && window.bootstrap) {
    mainNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 991.98px)').matches && mainNav.classList.contains('show')) {
                window.bootstrap.Collapse.getOrCreateInstance(mainNav).hide();
            }
        });
    });
}

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
const chatTyping = document.getElementById('afetoChatTyping');
const chatConfigElement = document.getElementById('afetoChatConfig');

if (chatWidget && chatToggle && chatClose && chatPanel && chatBody && chatInput && chatSend) {
    let chatConfig = {};
    if (chatConfigElement) {
        try {
            chatConfig = JSON.parse(chatConfigElement.textContent || '{}');
        } catch (error) {
            chatConfig = {};
        }
    }

    const intents = Array.isArray(chatConfig.intencoes) ? chatConfig.intencoes.map((intent) => {
        let matcher = null;
        if (typeof intent.matcher === 'string' && intent.matcher) {
            try {
                matcher = new RegExp(intent.matcher, 'i');
            } catch (error) {
                matcher = null;
            }
        }
        return { matcher, text: String(intent.text || ''), acoes: Array.isArray(intent.acoes) ? intent.acoes : [] };
    }) : [];
    const fallback = String(chatConfig.resposta_fallback || '');

    const appendMessage = (text, type, acoes) => {
        const message = document.createElement('div');
        message.className = `chat-message ${type}`;
        const content = document.createElement('div');
        content.className = 'chat-message-text';
        content.textContent = text;
        message.appendChild(content);
        if (type === 'bot' && acoes && acoes.length) {
            const actions = document.createElement('div');
            actions.className = 'chat-actions';
            acoes.forEach((acao) => {
                const label = acao && acao.label ? acao.label : '';
                const url = acao && acao.url ? acao.url : '#';
                if (!label) return;
                const link = document.createElement('a');
                link.className = 'chat-action';
                link.href = url;
                link.textContent = label;
                actions.appendChild(link);
            });
            message.appendChild(actions);
        }
        chatBody.appendChild(message);
        chatBody.scrollTop = chatBody.scrollHeight;
    };

    const getAnswer = (text) => {
        for (const intent of intents) {
            if (intent.matcher && intent.matcher.test(text)) {
                return intent;
            }
        }
        return { matcher: null, text: fallback, acoes: [] };
    };

    const showPanel = (open) => {
        if (open) {
            chatWidget.classList.add('chat-open');
            chatWidget.classList.remove('chat-closed');
            chatToggle.setAttribute('aria-expanded', 'true');
            chatPanel.setAttribute('aria-hidden', 'false');
            chatInput.focus();
        } else {
            chatWidget.classList.remove('chat-open');
            chatWidget.classList.add('chat-closed');
            chatToggle.setAttribute('aria-expanded', 'false');
            chatPanel.setAttribute('aria-hidden', 'true');
        }
    };

    chatToggle.addEventListener('click', () => showPanel(!chatWidget.classList.contains('chat-open')));
    chatClose.addEventListener('click', () => showPanel(false));

    const scrollChat = () => {
        chatBody.scrollTop = chatBody.scrollHeight;
    };

    const showTyping = (visible) => {
        if (!chatTyping) return;
        chatTyping.hidden = !visible;
        if (visible) {
            scrollChat();
        }
    };

    const sendChat = () => {
        const value = chatInput.value.trim();
        if (!value) return;
        const answer = getAnswer(value);
        appendMessage(value, 'user');
        chatInput.value = '';
        showTyping(true);
        setTimeout(() => {
            showTyping(false);
            appendMessage(answer.text, 'bot', answer.acoes);
        }, 700);
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

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && chatWidget.classList.contains('chat-open')) {
            showPanel(false);
        }
    });
}
