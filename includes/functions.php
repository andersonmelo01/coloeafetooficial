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
