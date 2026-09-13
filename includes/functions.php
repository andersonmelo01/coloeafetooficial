<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/conexao.php';

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $root = '';

    if (preg_match('#^(.*?/ColoAfeto)(/|$)#i', $script, $matches)) {
        $root = $matches[1];
    }

    return rtrim($root, '/') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money_br(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function service_catalog(): array
{
    return [
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
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function partner_catalog(): array
{
    if (app_config('parceiros.habilitados', '0') !== '1') {
        return [];
    }

    if (!function_exists('db_one')) {
        return [];
    }

    $rows = db_all(
        "SELECT * FROM parceiros WHERE ativo = 1 ORDER BY ordem ASC, nome ASC"
    );

    $catalog = [];
    foreach ($rows as $row) {
        $keywords = array_filter(array_map('trim', explode(',', (string) $row['keywords'])));
        $slug = (string) ($row['slug'] ?? '');
        if ($slug !== '' && !in_array($slug, $keywords, true)) {
            $keywords[] = $slug;
        }
        $nome = (string) ($row['nome'] ?? '');
        if ($nome !== '') {
            $keywords[] = $nome;
        }

        $profileUrl = (string) ($row['site_url'] ?? '');
        if ($profileUrl === '' || !preg_match('#^https?://#i', $profileUrl)) {
            $profileUrl = base_url('parceiros/perfil.php?slug=' . urlencode($slug));
        }

        $imageUrl = (string) ($row['imagem'] ?? '');
        if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = base_url($imageUrl);
        }

        $catalog[] = [
            'id' => (int) $row['id'],
            'name' => $nome,
            'role' => (string) ($row['papel'] ?? ''),
            'summary' => (string) ($row['resumo'] ?? ''),
            'description' => (string) ($row['descricao'] ?? ''),
            'profile_url' => $profileUrl,
            'whatsapp_url' => (string) ($row['whatsapp_url'] ?? ''),
            'instagram_url' => (string) ($row['instagram_url'] ?? ''),
            'facebook_url' => (string) ($row['facebook_url'] ?? ''),
            'linkedin_url' => (string) ($row['linkedin_url'] ?? ''),
            'image' => $imageUrl,
            'keywords' => array_values(array_unique($keywords)),
        ];
    }

    return $catalog;
}

function partner_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/parceiros';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function partner_upload_image(string $field = 'imagem'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK || (int) ($_FILES[$field]['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $tmp = (string) ($_FILES[$field]['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    $mime = $info['mime'] ?? '';
    if ($tmp === '' || !isset($allowed[$mime])) {
        return null;
    }

    $filename = 'parceiro-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, partner_upload_dir() . '/' . $filename)) {
        return null;
    }

    return 'uploads/parceiros/' . $filename;
}

function partner_slugify(string $nome): string
{
    $slug = strtolower(trim($nome));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) $slug);
    $slug = trim((string) $slug, '-');
    return $slug !== '' ? $slug : 'parceiro-' . date('YmdHis');
}

function bot_whatsapp_url(): string
{
    return 'https://wa.me/5521982846871';
}

function controle_estoque_habilitado(): bool
{
    return app_config('pdv.controle_estoque', '1') === '1';
}

function bot_chat_profile(?array $usuario): array
{
    $tipo = (string) ($usuario['tipo'] ?? '');

    if (in_array($tipo, ['admin', 'entregador'], true)) {
        return bot_profile_gestor();
    }

    if ($tipo === 'cliente') {
        return bot_profile_cliente($usuario);
    }

    return bot_profile_visitante();
}

function bot_profile_visitante(): array
{
    $wa = bot_whatsapp_url();
    $parceiros = function_exists('partner_catalog') ? partner_catalog() : [];

    $intencoes = [
        [
            'matcher' => 'como funciona.*doula|doula|parto',
            'text' => 'A nossa doula oferece apoio antes, durante e depois do parto. Ela ajuda com preparação emocional, presença no parto, orientação prática para amamentação e apoio à família no pós-parto. É um acolhimento humano que traz mais segurança e confiança.',
            'acoes' => [['label' => 'Agendar atendimento', 'url' => $wa]],
        ],
        [
            'matcher' => 'quais serviços|serviços|servicos|o que vocês oferecem|oferecem|atendimento materno',
            'text' => $parceiros
                ? 'Oferecemos atendimento materno, suporte em amamentação e pós-parto, taping pós-parto, cuidados de furinho humanizado e serviço de doula. Também indicamos parceiros confiáveis e temos uma curadoria de produtos para cada fase da maternidade.'
                : 'Oferecemos atendimento materno, suporte em amamentação e pós-parto, taping pós-parto, cuidados de furinho humanizado e serviço de doula. Também temos uma curadoria de produtos para cada fase da maternidade.',
        ],
        [
            'matcher' => 'agendar|agenda|marcar|atendimento|orçamento|orcamento',
            'text' => 'Para agendar, você pode enviar uma mensagem no WhatsApp e escolher o melhor dia e horário. Também ajudamos a definir o serviço mais adequado para a sua fase materna.',
            'acoes' => [['label' => 'Falar no WhatsApp', 'url' => $wa]],
        ],
        [
            'matcher' => 'amamenta|amamentação|amamentacao|pega|mama|mamífero',
            'text' => 'No apoio à amamentação, trabalhamos para melhorar a pega, reduzir desconfortos e aumentar a segurança da mãe. Também oferecemos orientações sobre rotina, conforto do bebê e suporte à família para o momento de amamentar.',
        ],
        [
            'matcher' => 'pós-?parto|pos-?parto|recuperação|recuperacao|quarentena',
            'text' => 'O pós-parto pode ser um período desafiador. Nosso suporte inclui orientação sobre cuidados do bebê, autocuidado da mãe, organização da rotina e acolhimento emocional para você e sua família.',
        ],
        [
            'matcher' => 'furinho|umbigo|pavio|cordão|cordao',
            'text' => 'O cuidado com o furinho humanizado é feito com atenção e delicadeza. Orientamos limpeza, sinais de alerta e como deixar esse momento mais tranquilo para mãe e bebê.',
        ],
        [
            'matcher' => 'produto|loja|catalogo|catálogo|produtos|comprar|retirada|entrega|frete',
            'text' => 'Temos uma curadoria de produtos para amamentação, pós-parto e bebê. Você pode conhecer o catálogo online e receber indicações de itens que combinam com a sua fase e as suas necessidades.',
            'acoes' => [['label' => 'Ver catálogo', 'url' => base_url('loja/index.php')]],
        ],
        [
            'matcher' => 'onde|local|presencial|online|endereço|endereco',
            'text' => 'Nosso atendimento é pensado para acolher você com flexibilidade, oferecendo suporte presencial quando possível e orientação online quando for melhor para a sua rotina.',
        ],
        [
            'matcher' => 'preço|preco|valor|custo|quanto custa|pacote',
            'text' => 'Os valores variam conforme o serviço e o tempo de atendimento. Para uma proposta personalizada, fale conosco pelo WhatsApp e podemos indicar o pacote mais adequado para você.',
            'acoes' => [['label' => 'Pedir proposta', 'url' => $wa]],
        ],
        [
            'matcher' => 'whatsapp|contato|falar|telefone|emaill|inhbox',
            'text' => 'O melhor caminho para contato imediato é pelo WhatsApp. Lá você pode tirar dúvidas, agendar atendimento ou pedir orientação rápida com a nossa equipe materna.',
            'acoes' => [['label' => 'Abrir WhatsApp', 'url' => $wa]],
        ],
    ];

    $sugestoes = ['Como funciona a doula?', 'Quais serviços oferecemos?', 'Como agendar atendimento?', 'Como comprar na loja?'];
    foreach ($parceiros as $partner) {
        $sugestoes[] = 'O que ' . $partner['name'] . ' faz?';
    }

    if ($parceiros) {
        $palavras = array_merge(['parceir'], ...array_map(static fn ($p) => [$p['name'], ...(array) $p['keywords']], $parceiros));
        $palavras = array_values(array_unique(array_filter($palavras)));
        $matcher = implode('|', array_map('preg_quote', $palavras));
        if ($matcher !== '') {
            $texto = implode("\n\n", array_map(static fn ($p) => $p['name'] . ' atua como ' . $p['role'] . '. ' . $p['summary'] . ($p['whatsapp_url'] ? ' Você também pode falar diretamente pelo WhatsApp informado no perfil.' : ''), $parceiros));
            $intencoes[] = ['matcher' => $matcher, 'text' => $texto];
        }
    }

    return [
        'perfil' => 'visitante',
        'titulo' => 'Assistente Colo & Afeto',
        'subtitulo' => 'Online · responde agora',
        'saudacao' => 'Olá! Eu sou a assistente Colo & Afeto. Posso tirar dúvidas sobre serviços, produtos e parceiros. Clique em uma pergunta ou escreva o que deseja saber.',
        'sugestoes' => $sugestoes,
        'intencoes' => $intencoes,
        'resposta_fallback' => 'Estou aqui para acolher! Se quiser, escreva sua dúvida com palavras como "amamentação", "pós-parto", "doula" ou "serviço", e eu te respondo com mais detalhes.',
    ];
}

function bot_profile_cliente(array $usuario): array
{
    $wa = bot_whatsapp_url();
    $id = (int) ($usuario['id'] ?? 0);
    $primeiroNome = trim(explode(' ', (string) ($usuario['nome'] ?? 'Cliente'))[0]);

    $totalPedidos = 0;
    $ultimoPedido = null;
    if ($id > 0) {
        $totalPedidos = (int) (db_one("SELECT COUNT(*) AS c FROM pedidos WHERE usuario_id = :id", ['id' => $id])['c'] ?? 0);
        $ultimoPedido = db_one("SELECT * FROM pedidos WHERE usuario_id = :id ORDER BY criado_em DESC LIMIT 1", ['id' => $id]);
    }

    if ($ultimoPedido) {
        $estado = ucfirst((string) $ultimoPedido['status']);
        $pedidoTexto = 'Seu pedido #' . (int) $ultimoPedido['id'] . ' está "' . $estado . '". ';
        $pedidoTexto .= 'Você pode acompanhar cada atualização na sua área do cliente.';
    } elseif ($totalPedidos > 0) {
        $pedidoTexto = 'Você já fez ' . $totalPedidos . ' pedido(s) conosco. Acesse a sua área do cliente para acompanhar o status de cada um.';
    } else {
        $pedidoTexto = 'Você ainda não fez nenhum pedido. Quando quiser, é só escolher um produto na loja e finalizar a compra.';
    }

    $intencoes = [
        [
            'matcher' => 'pedido|carrinho|status|rastreio|rastrear|acompanhar|onde está|compr',
            'text' => $pedidoTexto,
            'acoes' => [['label' => 'Ver meus pedidos', 'url' => base_url('cliente/pedidos.php')]],
        ],
        [
            'matcher' => 'cadastro|cadastrar|entrar|login|senha|conta',
            'text' => 'Cadastro e acesso rápido: você pode criar sua conta ou entrar quando quiser para acompanhar pedidos, salvar endereços e abrir chamados.',
            'acoes' => [['label' => 'Minha conta', 'url' => base_url('cliente/index.php')]],
        ],
        [
            'matcher' => 'chamado|dúvida|duvida|suporte|ajuda|reclama',
            'text' => 'Precisa de ajuda com um pedido ou produto? Abra um chamado na sua área do cliente e nossa equipe acompanha até a solução.',
            'acoes' => [['label' => 'Abrir chamado', 'url' => base_url('cliente/chamados.php')]],
        ],
        [
            'matcher' => 'pagamento|pix|pagar|boleto|cartão|cartao|parcel',
            'text' => 'As compras podem ser pagas com Pix, cartão e outras formas disponíveis no checkout. Após o pagamento, o status do pedido é atualizado na sua área do cliente. Se algo não estiver conforme, abra um chamado.',
            'acoes' => [['label' => 'Ir para a loja', 'url' => base_url('loja/index.php')]],
        ],
        [
            'matcher' => 'retirada|entrega|enviado|receber|frete',
            'text' => 'Acompanhe se o seu pedido vai ser retirado ou entregue direto na sua casa. O status "enviado" indica que o pacote está a caminho, e você pode conferir os detalhes na sua área do cliente.',
            'acoes' => [['label' => 'Ver meus pedidos', 'url' => base_url('cliente/pedidos.php')]],
        ],
        [
            'matcher' => 'whatsapp|contato|falar|telefone',
            'text' => 'Se preferir um atendimento humano, fale com a nossa equipe pelo WhatsApp. Estamos prontos para acolher você.',
            'acoes' => [['label' => 'Abrir WhatsApp', 'url' => $wa]],
        ],
    ];

    $sugestoes = ['Onde está meu pedido?', 'Como faço um chamado?', 'Como comprar na loja?', 'Formas de pagamento'];

    return [
        'perfil' => 'cliente',
        'titulo' => 'Atendimento ao cliente',
        'subtitulo' => 'Online · em que posso ajudar?',
        'saudacao' => 'Olá, ' . $primeiroNome . '! Aqui você pode acompanhar pedidos, abrir chamados e muito mais. Como posso te ajudar hoje?',
        'sugestoes' => $sugestoes,
        'intencoes' => $intencoes,
        'resposta_fallback' => 'Posso te ajudar com pedidos, pagamentos, entregas ou chamados. Use palavras como "pedido", "pagamento", "entrega" ou "chamado", ou abra um chamado na sua área do cliente.',
    ];
}

function bot_profile_gestor(): array
{
    $intencoes = [
        [
            'matcher' => 'venda|pdv|caixa.*registrar|registrar.*venda|lançar|lancar|cobrar',
            'text' => 'Para registrar uma venda no PDV: acesse "PDV Fácil", clique nos produtos, defina cliente quando necessário, escolha a forma de pagamento e finalize. O cupom é gerado automaticamente e pode ser impresso ou enviado por e-mail.',
            'acoes' => [['label' => 'Abrir PDV Fácil', 'url' => base_url('admin/vendas/index.php')], ['label' => 'Histórico de vendas', 'url' => base_url('admin/vendas/historico.php')]],
        ],
        [
            'matcher' => 'caixa|abrir caixa|fechar caixa|sangria|suprimento|movimento',
            'text' => 'O fluxo de caixa é controlado na área de vendas: abra o caixa no início do expediente, faça sangrias/suprimentos se necessário e feche no final para conferir o total. As entradas e saídas ficam registradas para conferência.',
            'acoes' => [['label' => 'Gerenciar caixa', 'url' => base_url('admin/vendas/caixa.php')]],
        ],
        [
            'matcher' => 'cupom|nfce|nfc-e|nota fiscal|fiscal|imprimir',
            'text' => 'Quando o módulo fiscal está habilitado, a venda gera cupom NFC-e (com chave de acesso) e registra a nota para envio. Caso contrário, emite cupom não fiscal. Ambos podem ser reimpressos ou enviados por e-mail no histórico de vendas.',
            'acoes' => [['label' => 'Histórico de vendas', 'url' => base_url('admin/vendas/historico.php')], ['label' => 'NF-e', 'url' => base_url('admin/notas/index.php')]],
        ],
        [
            'matcher' => 'pix|cartão|cartao|pagamento|efi|receber|à prazo|a prazo|parcel',
            'text' => 'Dinheiro e Pix são registrados direto no caixa. Cartão com tokenção online gera cobrança Efi; sem token, você registra o pagamento manualmente. Vendas a prazo exigem cliente cadastrado e geram parcelas para receber depois. Pagamentos pendentes aparecem em "Confirmar".',
            'acoes' => [['label' => 'Confirmar pagamentos', 'url' => base_url('admin/vendas/confirmar.php')], ['label' => 'Receber venda', 'url' => base_url('admin/vendas/receber.php')]],
        ],
        [
            'matcher' => 'produto|cadastrar produto|preço|preco|estoque|categoria|promo',
            'text' => 'Cadastre e organize produtos em Produtos, controle reposições em Estoque e crie Categorias, Grupos e Promoções para organizar a vitrine da loja.',
            'acoes' => [['label' => 'Produtos', 'url' => base_url('admin/produtos/index.php')], ['label' => 'Estoque', 'url' => base_url('admin/estoque/index.php')]],
        ],
        [
            'matcher' => 'cliente|cadastrar cliente',
            'text' => 'A pasta Clientes guarda o cadastro completo de cada cliente, usado nas vendas a prazo e nos relatórios.',
            'acoes' => [['label' => 'Clientes', 'url' => base_url('admin/clientes/index.php')]],
        ],
        [
            'matcher' => 'parceiro|conveniad',
            'text' => 'Parceiros são exibidos no site e no chat (se a visibilidade estiver habilitada em Configurações). Cadastre com nome, imagem, links e ordem de exibição.',
            'acoes' => [['label' => 'Novo parceiro', 'url' => base_url('admin/parceiros/novo.php')], ['label' => 'Lista de parceiros', 'url' => base_url('admin/parceiros/index.php')], ['label' => 'Configurações', 'url' => base_url('admin/configuracoes/index.php')]],
        ],
        [
            'matcher' => 'pedido|loja|checkout|compra online|site',
            'text' => 'Os pedidos da loja ficam em Pedidos: você acompanha pagamento, separação, envio e entrega, e cada pedido tem detalhes fiscais individuais.',
            'acoes' => [['label' => 'Pedidos', 'url' => base_url('admin/pedidos/index.php')]],
        ],
        [
            'matcher' => 'entrega|entregador|frete|rastreio',
            'text' => 'Entregas e entregadores ficam em Entregas/Entregadores, com histórico de cada profissional e acompanhamento dos envios dos pedidos.',
            'acoes' => [['label' => 'Entregas', 'url' => base_url('admin/entregas/index.php')], ['label' => 'Entregadores', 'url' => base_url('admin/entregadores/index.php')]],
        ],
        [
            'matcher' => 'relatório|relatorio|resumo|vendas.*[ou]?|dashboard|indicador',
            'text' => 'O painel e os Relatórios mostram vendas, valores recebidos, clientes, produtos mais vendidos e outros indicadores para acompanhar o desempenho.',
            'acoes' => [['label' => 'Dashboard', 'url' => base_url('admin/index.php')], ['label' => 'Relatórios', 'url' => base_url('admin/relatorios/index.php')], ['label' => 'Faturamento', 'url' => base_url('admin/faturamento/index.php')]],
        ],
        [
            'matcher' => 'config|chave|token|efi|certificado|credencia|email|hospedagem|whatsapp',
            'text' => 'A maioria das integrações é ajustada em Configurações: credenciais Efi (Pix/cartão), módulo fiscal/NFC-e, envio de e-mail, loja e seções do site.',
            'acoes' => [['label' => 'Configurações', 'url' => base_url('admin/configuracoes/index.php')], ['label' => 'Pagamentos', 'url' => base_url('admin/pagamentos/index.php')]],
        ],
        [
            'matcher' => 'chamado|suporte|cliente.*contato|dúvida|duvida',
            'text' => 'Chamados dos clientes ficam na pasta Chamados: responda, acompanhe a resolução e mantenha o histórico de atendimento.',
            'acoes' => [['label' => 'Chamados', 'url' => base_url('admin/chamados/index.php')]],
        ],
        [
            'matcher' => 'usuário|usuario|gestor|perfil|permissão|permissao|acesso',
            'text' => 'Usuários e permissões são controlados em Gestores e Perfis: cada perfil define as permissões de acesso às pastas do painel.',
            'acoes' => [['label' => 'Gestores', 'url' => base_url('admin/gestores/index.php')], ['label' => 'Perfis', 'url' => base_url('admin/perfis/index.php')]],
        ],
    ];

    return [
        'perfil' => 'gestor',
        'titulo' => 'Assistente do Gestor',
        'subtitulo' => 'Online · ajuda com o sistema',
        'saudacao' => 'Olá! Eu sou o assistente interno do sistema. Posso te ajudar a usar o PDV, gerenciar cadastros, pedidos, estoque, fiscal e configurações. O que você quer fazer?',
        'sugestoes' => ['Como registrar uma venda no caixa?', 'Como abrir e fechar o caixa?', 'Como emitir o cupom/NFC-e?', 'Como cadastrar um parceiro?'],
        'intencoes' => $intencoes,
        'resposta_fallback' => 'Ainda não encontrei uma resposta pronta para isso. Use o menu lateral do painel para navegar, ou me pergunte sobre "venda", "caixa", "cupom", "produto", "parceiro" ou "configurações".',
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function validate_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('Sessao expirada. Atualize a pagina e tente novamente.');
    }
}

function current_user(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['tipo'] ?? '') === 'admin';
}

function admin_permission_catalog(): array
{
    return [
        'dashboard' => 'Dashboard',
        'servicos' => 'Serviços',
        'produtos' => 'Produtos',
        'estoque' => 'Estoque',
        'promocoes' => 'Promoções',
        'categorias' => 'Categorias',
        'grupos' => 'Grupos',
        'pedidos' => 'Pedidos',
        'vendas' => 'Vendas presenciais (PDV Fácil)',
        'parceiros' => 'Parceiros',
        'entregas' => 'Entregas',
        'entregadores' => 'Entregadores',
        'clientes' => 'Clientes',
        'notas' => 'NF-e',
        'faturamento' => 'Faturamento',
        'pagamentos' => 'Pagamentos',
        'chamados' => 'Chamados',
        'relatorios' => 'Relatórios',
        'configuracoes' => 'Configurações',
        'perfis' => 'Perfis administrativos',
        'gestores' => 'Gestores',
    ];
}

function admin_permission_for_path(?string $scriptName = null): ?string
{
    $scriptName = str_replace('\\', '/', $scriptName ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!preg_match('#/admin/([^/]+)#', $scriptName, $matches)) {
        return null;
    }

    $module = $matches[1];
    if ($module === 'index.php') {
        return 'dashboard';
    }

    return [
        'grupos' => 'grupos',
        'notas' => 'notas',
    ][$module] ?? $module;
}

function current_admin_profile_id(): ?int
{
    $user = current_user();
    if (($user['tipo'] ?? '') !== 'admin') {
        return null;
    }

    if (array_key_exists('perfil_admin_id', $user)) {
        return $user['perfil_admin_id'] ? (int) $user['perfil_admin_id'] : null;
    }

    $row = db_one("SELECT perfil_admin_id FROM usuarios WHERE id = :id", ['id' => (int) $user['id']]);
    $_SESSION['usuario']['perfil_admin_id'] = !empty($row['perfil_admin_id']) ? (int) $row['perfil_admin_id'] : null;
    return $_SESSION['usuario']['perfil_admin_id'];
}

function admin_is_super(): bool
{
    if (!is_admin()) {
        return false;
    }

    $profileId = current_admin_profile_id();
    if (!$profileId) {
        return true;
    }

    $profile = db_one("SELECT administrador, ativo FROM admin_perfis WHERE id = :id", ['id' => $profileId]);
    return $profile && (int) $profile['ativo'] === 1 && (int) $profile['administrador'] === 1;
}

function admin_can(string $permission): bool
{
    if (admin_is_super()) {
        return true;
    }

    $profileId = current_admin_profile_id();
    if (!$profileId || !array_key_exists($permission, admin_permission_catalog())) {
        return false;
    }

    $row = db_one(
        "SELECT app.id
         FROM admin_perfil_permissoes app
         JOIN admin_perfis ap ON ap.id = app.perfil_id
         WHERE app.perfil_id = :perfil_id
           AND app.permissao = :permissao
           AND ap.ativo = 1
         LIMIT 1",
        ['perfil_id' => $profileId, 'permissao' => $permission]
    );

    return (bool) $row;
}

function require_admin_permission(?string $permission = null): void
{
    $permission = $permission ?: admin_permission_for_path();
    if ($permission && !admin_can($permission)) {
        http_response_code(403);
        exit('Acesso negado para este módulo.');
    }
}

function require_login(?string $tipo = null): void
{
    $user = current_user();

    if (!$user) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? base_url();
        redirect('auth/login.php');
    }

    if ($tipo && ($user['tipo'] ?? '') !== $tipo) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    if ($tipo === 'admin') {
        require_admin_permission();
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function db_all(string $sql, array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function db_one(string $sql, array $params = []): ?array
{
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

function pagination_params(int $perPage = 15, string $pageParam = 'pagina'): array
{
    $perPage = max(1, $perPage);
    $page = max(1, (int) ($_GET[$pageParam] ?? 1));

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'page_param' => $pageParam,
    ];
}

function paginate_query(string $sql, array $params = [], ?string $countSql = null, int $perPage = 15, string $pageParam = 'pagina'): array
{
    $paging = pagination_params($perPage, $pageParam);
    $baseSql = rtrim(trim($sql), ';');
    $totalSql = $countSql ?: "SELECT COUNT(*) AS total FROM ({$baseSql}) paginated_total";
    $total = (int) (db_one($totalSql, $params)['total'] ?? 0);
    $pages = max(1, (int) ceil($total / $paging['per_page']));

    if ($paging['page'] > $pages) {
        $paging['page'] = $pages;
        $paging['offset'] = ($pages - 1) * $paging['per_page'];
    }

    $stmt = db()->prepare($baseSql . ' LIMIT :__limit OFFSET :__offset');
    foreach ($params as $key => $value) {
        $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($param, $value, $type);
    }
    $stmt->bindValue(':__limit', $paging['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':__offset', $paging['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $paging['page'],
        'pages' => $pages,
        'per_page' => $paging['per_page'],
        'offset' => $paging['offset'],
        'page_param' => $pageParam,
    ];
}

function pagination_links(array $pagination, ?array $queryParams = null): string
{
    $total = (int) ($pagination['total'] ?? 0);
    $page = max(1, (int) ($pagination['page'] ?? 1));
    $pages = max(1, (int) ($pagination['pages'] ?? 1));
    $perPage = max(1, (int) ($pagination['per_page'] ?? 15));
    $pageParam = (string) ($pagination['page_param'] ?? 'pagina');
    $queryParams = $queryParams ?? $_GET;
    unset($queryParams[$pageParam]);

    if ($total === 0) {
        return '';
    }

    $from = (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);
    $urlFor = static function (int $targetPage) use ($queryParams, $pageParam): string {
        $params = array_merge($queryParams, [$pageParam => $targetPage]);
        return '?' . http_build_query($params);
    };

    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $nextDisabled = $page >= $pages ? ' disabled' : '';
    $prevHref = $page > 1 ? e($urlFor($page - 1)) : '#';
    $nextHref = $page < $pages ? e($urlFor($page + 1)) : '#';

    return '<div class="list-pagination d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">'
        . '<div class="small text-secondary">Mostrando ' . $from . '-' . $to . ' de ' . $total . ' registro(s)</div>'
        . '<nav aria-label="Navegacao da lista"><ul class="pagination pagination-sm mb-0">'
        . '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . $prevHref . '" aria-label="Pagina anterior"><i class="bi bi-chevron-left"></i></a></li>'
        . '<li class="page-item disabled"><span class="page-link">Pagina ' . $page . ' de ' . $pages . '</span></li>'
        . '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . $nextHref . '" aria-label="Proxima pagina"><i class="bi bi-chevron-right"></i></a></li>'
        . '</ul></nav></div>';
}

function sample_products(): array
{
    return [
        [
            'id' => 1,
            'nome' => 'Kit Amamentação Tranquila',
            'slug' => 'kit-amamentacao-tranquila',
            'categoria' => 'Amamentação',
            'grupo' => 'Kits',
            'preco' => 199.90,
            'preco_promocional' => 179.90,
            'estoque' => 12,
            'descricao_curta' => 'Almofada, coletor e protetores para uma rotina mais leve.',
        ],
        [
            'id' => 2,
            'nome' => 'Almofada de Amamentação Anatômica',
            'slug' => 'almofada-amamentacao-anatomica',
            'categoria' => 'Amamentação',
            'grupo' => 'Produtos físicos',
            'preco' => 139.90,
            'preco_promocional' => null,
            'estoque' => 8,
            'descricao_curta' => 'Capa lavável, enchimento antialérgico e apoio confortável.',
        ],
        [
            'id' => 3,
            'nome' => 'Kit Primeiros Cuidados do Bebê',
            'slug' => 'kit-primeiros-cuidados-bebe',
            'categoria' => 'Bebê',
            'grupo' => 'Kits',
            'preco' => 89.90,
            'preco_promocional' => null,
            'estoque' => 20,
            'descricao_curta' => 'Itens essenciais para cuidados diários do recém-nascido.',
        ],
    ];
}

function cart_items(): array
{
    return $_SESSION['carrinho'] ?? [];
}

function cart_count(): int
{
    return array_sum(array_column(cart_items(), 'quantidade'));
}

function cart_total(): float
{
    return array_reduce(cart_items(), fn ($sum, $item) => $sum + ($item['preco'] * $item['quantidade']), 0.0);
}

function app_config(string $key, ?string $default = null): ?string
{
    $row = db_one("SELECT valor FROM configuracoes WHERE chave = :chave", ['chave' => $key]);
    return $row['valor'] ?? $default;
}

function set_app_config(string $key, string $value, string $group = 'geral'): void
{
    $stmt = db()->prepare(
        "INSERT INTO configuracoes (chave, valor, grupo)
         VALUES (:chave, :valor, :grupo)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), grupo = VALUES(grupo)"
    );
    $stmt->execute(['chave' => $key, 'valor' => $value, 'grupo' => $group]);
}

function fiscal_enabled(): bool
{
    return app_config('fiscal.habilitado', '0') === '1';
}

function fiscal_reforma_enabled(): bool
{
    return app_config('fiscal.reforma_tributaria_habilitada', '0') === '1';
}

function efi_enabled(): bool
{
    return app_config('efi.habilitado', '0') === '1';
}

function efi_pix_enabled(): bool
{
    return efi_enabled() && app_config('efi.pix_habilitado', '1') === '1';
}

function efi_card_enabled(): bool
{
    return efi_enabled() && app_config('efi.cartao_habilitado', '0') === '1';
}

function loja_vendas_enabled(): bool
{
    return app_config('loja.vendas_habilitadas', '1') === '1';
}

function loja_catalog_message(): string
{
    $message = trim((string) app_config('loja.mensagem_catalogo', ''));
    return $message !== ''
        ? $message
        : 'A loja está temporariamente funcionando como catálogo. As vendas online estão pausadas, mas os produtos podem ser visualizados normalmente.';
}

function promotion_price(array $produto): float
{
    $base = (float) $produto['preco'];

    if (!empty($produto['promo_preco'])) {
        return (float) $produto['promo_preco'];
    }

    if (!empty($produto['promo_percentual'])) {
        return round($base * (1 - ((float) $produto['promo_percentual'] / 100)), 2);
    }

    if (!empty($produto['preco_promocional'])) {
        return (float) $produto['preco_promocional'];
    }

    return $base;
}

function product_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/produtos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function product_images(int $produtoId): array
{
    return db_all(
        "SELECT *
         FROM produto_imagens
         WHERE produto_id = :produto_id
         ORDER BY principal DESC, ordem ASC, id ASC",
        ['produto_id' => $produtoId]
    );
}

function product_first_image(int $produtoId): ?array
{
    return db_one(
        "SELECT *
         FROM produto_imagens
         WHERE produto_id = :produto_id
         ORDER BY principal DESC, ordem ASC, id ASC
         LIMIT 1",
        ['produto_id' => $produtoId]
    );
}

function product_upload_images(int $produtoId, string $field = 'imagens'): int
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return 0;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $maxBytes = 5 * 1024 * 1024;
    $existing = (int) (db_one("SELECT COUNT(*) AS total FROM produto_imagens WHERE produto_id = :id", ['id' => $produtoId])['total'] ?? 0);
    $saved = 0;
    $count = count($_FILES[$field]['name']);
    $dir = product_upload_dir();

    for ($i = 0; $i < $count; $i++) {
        $error = (int) ($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK || (int) ($_FILES[$field]['size'][$i] ?? 0) > $maxBytes) {
            continue;
        }

        $tmp = (string) ($_FILES[$field]['tmp_name'][$i] ?? '');
        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        if (!$tmp || !isset($allowed[$mime])) {
            continue;
        }

        $filename = 'produto-' . $produtoId . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $destination = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            continue;
        }

        $relativePath = 'uploads/produtos/' . $filename;
        db()->prepare(
            "INSERT INTO produto_imagens (produto_id, caminho, principal, ordem)
             VALUES (:produto_id, :caminho, :principal, :ordem)"
        )->execute([
            'produto_id' => $produtoId,
            'caminho' => $relativePath,
            'principal' => ($existing + $saved) === 0 ? 1 : 0,
            'ordem' => $existing + $saved,
        ]);
        $saved++;
    }

    return $saved;
}

function product_delete_image(int $imageId, int $produtoId): bool
{
    $image = db_one("SELECT * FROM produto_imagens WHERE id = :id AND produto_id = :produto_id", [
        'id' => $imageId,
        'produto_id' => $produtoId,
    ]);
    if (!$image) {
        return false;
    }

    db()->prepare("DELETE FROM produto_imagens WHERE id = :id")->execute(['id' => $imageId]);

    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $image['caminho']);
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . $path;
    $uploadsRoot = product_upload_dir();
    $realUploads = realpath($uploadsRoot);
    $realFile = realpath($absolute);
    if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
        unlink($realFile);
    }

    $remainingPrincipal = db_one("SELECT id FROM produto_imagens WHERE produto_id = :id AND principal = 1 LIMIT 1", ['id' => $produtoId]);
    if (!$remainingPrincipal) {
        $first = product_first_image($produtoId);
        if ($first) {
            db()->prepare("UPDATE produto_imagens SET principal = 1 WHERE id = :id")->execute(['id' => (int) $first['id']]);
        }
    }

    return true;
}

function product_set_main_image(int $imageId, int $produtoId): void
{
    $image = db_one("SELECT id FROM produto_imagens WHERE id = :id AND produto_id = :produto_id", [
        'id' => $imageId,
        'produto_id' => $produtoId,
    ]);
    if (!$image) {
        return;
    }

    db()->prepare("UPDATE produto_imagens SET principal = 0 WHERE produto_id = :produto_id")->execute(['produto_id' => $produtoId]);
    db()->prepare("UPDATE produto_imagens SET principal = 1 WHERE id = :id AND produto_id = :produto_id")->execute([
        'id' => $imageId,
        'produto_id' => $produtoId,
    ]);
}

function service_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/servicos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function service_gallery_images(string $serviceSlug, bool $onlyActive = false): array
{
    $sql = "SELECT *
            FROM servico_galeria
            WHERE servico_slug = :service_slug";
    if ($onlyActive) {
        $sql .= " AND ativo = 1";
    }
    $sql .= " ORDER BY ordem ASC, id ASC";

    return db_all($sql, ['service_slug' => $serviceSlug]);
}

function service_upload_gallery_image(string $serviceSlug, string $title, string $description, string $field = 'imagem'): bool
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return false;
    }

    $error = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return false;
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($error !== UPLOAD_ERR_OK || (int) ($_FILES[$field]['size'] ?? 0) > $maxBytes) {
        return false;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $tmp = (string) ($_FILES[$field]['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    $mime = $info['mime'] ?? '';
    if (!$tmp || !isset($allowed[$mime])) {
        return false;
    }

    $filename = 'servico-' . preg_replace('/[^a-z0-9-]+/i', '-', $serviceSlug) . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $destination = service_upload_dir() . '/' . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        return false;
    }

    $order = (int) (db_one(
        "SELECT COALESCE(MAX(ordem), -1) + 1 AS proxima_ordem
         FROM servico_galeria
         WHERE servico_slug = :service_slug",
        ['service_slug' => $serviceSlug]
    )['proxima_ordem'] ?? 0);

    db()->prepare(
        "INSERT INTO servico_galeria (servico_slug, titulo, descricao, caminho, ordem, ativo)
         VALUES (:servico_slug, :titulo, :descricao, :caminho, :ordem, 1)"
    )->execute([
        'servico_slug' => $serviceSlug,
        'titulo' => $title,
        'descricao' => $description,
        'caminho' => 'uploads/servicos/' . $filename,
        'ordem' => $order,
    ]);

    return true;
}

function service_update_gallery_image(int $imageId, string $serviceSlug, string $title, string $description, int $order, bool $active): bool
{
    $stmt = db()->prepare(
        "UPDATE servico_galeria
         SET titulo = :titulo, descricao = :descricao, ordem = :ordem, ativo = :ativo
         WHERE id = :id AND servico_slug = :service_slug"
    );
    $stmt->execute([
        'id' => $imageId,
        'service_slug' => $serviceSlug,
        'titulo' => $title,
        'descricao' => $description,
        'ordem' => $order,
        'ativo' => $active ? 1 : 0,
    ]);

    return (bool) db_one("SELECT id FROM servico_galeria WHERE id = :id AND servico_slug = :service_slug", [
        'id' => $imageId,
        'service_slug' => $serviceSlug,
    ]);
}

function service_delete_gallery_image(int $imageId, string $serviceSlug): bool
{
    $image = db_one("SELECT * FROM servico_galeria WHERE id = :id AND servico_slug = :service_slug", [
        'id' => $imageId,
        'service_slug' => $serviceSlug,
    ]);
    if (!$image) {
        return false;
    }

    db()->prepare("DELETE FROM servico_galeria WHERE id = :id")->execute(['id' => $imageId]);

    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $image['caminho']);
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . $path;
    $uploadsRoot = service_upload_dir();
    $realUploads = realpath($uploadsRoot);
    $realFile = realpath($absolute);
    if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
        unlink($realFile);
    }

    return true;
}

function chamado_status_options(): array
{
    return [
        'aberto' => 'Aberto',
        'em_analise' => 'Em analise',
        'pendente_informacao' => 'Pendente de informacao',
        'finalizado' => 'Finalizado',
    ];
}

function chamado_status_label(?string $status): string
{
    return chamado_status_options()[$status ?? ''] ?? (string) $status;
}

function chamado_generate_protocol(int $id): string
{
    return 'CA-' . date('Ymd') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}
