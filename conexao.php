<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'colo_afeto';
const DB_USER = 'root';
const DB_PASS = 'admin';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);

    return $pdo;
}

function db_server(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);

    return $pdo;
}

function database_schema_exists(): bool
{
    $requiredTables = [
        'usuarios',
        'admin_perfis',
        'admin_perfil_permissoes',
        'clientes_perfis',
        'enderecos',
        'categorias',
        'grupos_produtos',
        'produtos',
        'produto_imagens',
        'pedidos',
        'pedido_itens',
        'pagamentos',
        'entregas',
        'entrega_tentativas',
        'notas_fiscais',
        'movimentos_estoque',
        'chamados',
        'chamados_mensagens',
        'configuracoes',
        'promocoes',
        'faturamento_eventos',
        'webhook_eventos',
        'emails_envios',
    ];

    $tableList = implode("','", $requiredTables);
    $stmt = db_server()->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = :database
           AND table_name IN ('{$tableList}')"
    );
    $stmt->execute(['database' => DB_NAME]);
    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0) === count($requiredTables);
}

function ensure_database_schema(): void
{
    $exists = database_schema_exists();

    if (!$exists) {
        $schemaPath = __DIR__ . '/database/schema.sql';
        if (!is_file($schemaPath)) {
            throw new RuntimeException('Arquivo database/schema.sql nao encontrado.');
        }

        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            throw new RuntimeException('Nao foi possivel ler database/schema.sql.');
        }

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $pdo = db_server();

        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    ensure_application_migrations();
}

function column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = :database
           AND table_name = :table
           AND column_name = :column"
    );
    $stmt->execute([
        'database' => DB_NAME,
        'table' => $table,
        'column' => $column,
    ]);
    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0) > 0;
}

function ensure_application_migrations(): void
{
    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS configuracoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chave VARCHAR(120) NOT NULL UNIQUE,
            valor TEXT NULL,
            grupo VARCHAR(60) NOT NULL DEFAULT 'geral',
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promocoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_id INT NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            descricao VARCHAR(255) NULL,
            preco_promocional DECIMAL(10,2) NULL,
            percentual_desconto DECIMAL(5,2) NULL,
            data_inicio DATE NULL,
            data_fim DATE NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            destaque TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (produto_id) REFERENCES produtos(id)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS faturamento_eventos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            status VARCHAR(60) NOT NULL,
            mensagem TEXT NULL,
            payload LONGTEXT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS webhook_eventos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provedor VARCHAR(60) NOT NULL,
            evento VARCHAR(120) NULL,
            status VARCHAR(60) NULL,
            pedido_id INT NULL,
            payload LONGTEXT NULL,
            processado TINYINT(1) NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS emails_envios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NULL,
            destinatario_email VARCHAR(160) NOT NULL,
            destinatario_nome VARCHAR(160) NULL,
            assunto VARCHAR(180) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pendente',
            erro TEXT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS entrega_tentativas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entrega_id INT NOT NULL,
            pedido_id INT NOT NULL,
            entregador_id INT NULL,
            observacao TEXT NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'tentativa',
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (entrega_id) REFERENCES entregas(id),
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
            FOREIGN KEY (entregador_id) REFERENCES usuarios(id)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_perfis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL UNIQUE,
            descricao VARCHAR(255) NULL,
            administrador TINYINT(1) NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_perfil_permissoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            perfil_id INT NOT NULL,
            permissao VARCHAR(80) NOT NULL,
            UNIQUE KEY uq_perfil_permissao (perfil_id, permissao),
            FOREIGN KEY (perfil_id) REFERENCES admin_perfis(id) ON DELETE CASCADE
        )"
    );

    try {
        $pdo->exec("ALTER TABLE usuarios MODIFY tipo ENUM('admin', 'cliente', 'entregador') NOT NULL DEFAULT 'cliente'");
    } catch (Throwable $e) {
        // Mantem compatibilidade em ambientes onde o usuario do banco nao pode alterar ENUM.
    }

    if (!column_exists('usuarios', 'perfil_admin_id')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN perfil_admin_id INT NULL AFTER tipo");
    }

    $columns = [
        'fiscal_status' => "ALTER TABLE pedidos ADD COLUMN fiscal_status VARCHAR(40) NOT NULL DEFAULT 'pendente'",
        'fiscal_mensagem' => "ALTER TABLE pedidos ADD COLUMN fiscal_mensagem TEXT NULL",
        'confirmado_em' => "ALTER TABLE pedidos ADD COLUMN confirmado_em DATETIME NULL",
        'revisado_em' => "ALTER TABLE pedidos ADD COLUMN revisado_em DATETIME NULL",
        'pagamento_status' => "ALTER TABLE pedidos ADD COLUMN pagamento_status VARCHAR(40) NOT NULL DEFAULT 'pendente'",
        'pagamento_payload' => "ALTER TABLE pedidos ADD COLUMN pagamento_payload LONGTEXT NULL",
    ];

    foreach ($columns as $column => $sql) {
        if (!column_exists('pedidos', $column)) {
            $pdo->exec($sql);
        }
    }

    $stockColumns = [
        'usuario_id' => "ALTER TABLE movimentos_estoque ADD COLUMN usuario_id INT NULL AFTER produto_id",
        'saldo_anterior' => "ALTER TABLE movimentos_estoque ADD COLUMN saldo_anterior INT NOT NULL DEFAULT 0 AFTER quantidade",
        'saldo_posterior' => "ALTER TABLE movimentos_estoque ADD COLUMN saldo_posterior INT NOT NULL DEFAULT 0 AFTER saldo_anterior",
        'origem' => "ALTER TABLE movimentos_estoque ADD COLUMN origem VARCHAR(80) NULL AFTER motivo",
        'observacao' => "ALTER TABLE movimentos_estoque ADD COLUMN observacao TEXT NULL AFTER origem",
    ];

    foreach ($stockColumns as $column => $sql) {
        if (!column_exists('movimentos_estoque', $column)) {
            $pdo->exec($sql);
        }
    }

    $deliveryColumns = [
        'entregador_id' => "ALTER TABLE entregas ADD COLUMN entregador_id INT NULL AFTER pedido_id",
        'observacao_entregador' => "ALTER TABLE entregas ADD COLUMN observacao_entregador TEXT NULL AFTER status",
        'confirmado_por_entregador_em' => "ALTER TABLE entregas ADD COLUMN confirmado_por_entregador_em DATETIME NULL AFTER entregue_em",
        'motivo_cancelamento' => "ALTER TABLE entregas ADD COLUMN motivo_cancelamento TEXT NULL AFTER confirmado_por_entregador_em",
        'cancelada_em' => "ALTER TABLE entregas ADD COLUMN cancelada_em DATETIME NULL AFTER motivo_cancelamento",
        'cancelada_por' => "ALTER TABLE entregas ADD COLUMN cancelada_por INT NULL AFTER cancelada_em",
    ];

    foreach ($deliveryColumns as $column => $sql) {
        if (!column_exists('entregas', $column)) {
            $pdo->exec($sql);
        }
    }

    $productFiscalColumns = [
        'origem_mercadoria' => "ALTER TABLE produtos ADD COLUMN origem_mercadoria VARCHAR(2) NULL AFTER unidade",
        'cst_icms' => "ALTER TABLE produtos ADD COLUMN cst_icms VARCHAR(4) NULL AFTER origem_mercadoria",
        'csosn' => "ALTER TABLE produtos ADD COLUMN csosn VARCHAR(4) NULL AFTER cst_icms",
        'cst_pis' => "ALTER TABLE produtos ADD COLUMN cst_pis VARCHAR(4) NULL AFTER csosn",
        'cst_cofins' => "ALTER TABLE produtos ADD COLUMN cst_cofins VARCHAR(4) NULL AFTER cst_pis",
        'aliquota_icms' => "ALTER TABLE produtos ADD COLUMN aliquota_icms DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER cst_cofins",
        'aliquota_pis' => "ALTER TABLE produtos ADD COLUMN aliquota_pis DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER aliquota_icms",
        'aliquota_cofins' => "ALTER TABLE produtos ADD COLUMN aliquota_cofins DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER aliquota_pis",
        'cst_ibs_cbs' => "ALTER TABLE produtos ADD COLUMN cst_ibs_cbs VARCHAR(6) NULL AFTER aliquota_cofins",
        'cclass_trib_ibs_cbs' => "ALTER TABLE produtos ADD COLUMN cclass_trib_ibs_cbs VARCHAR(12) NULL AFTER cst_ibs_cbs",
        'aliquota_ibs_uf' => "ALTER TABLE produtos ADD COLUMN aliquota_ibs_uf DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER cclass_trib_ibs_cbs",
        'aliquota_ibs_municipal' => "ALTER TABLE produtos ADD COLUMN aliquota_ibs_municipal DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER aliquota_ibs_uf",
        'aliquota_cbs' => "ALTER TABLE produtos ADD COLUMN aliquota_cbs DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER aliquota_ibs_municipal",
        'cst_is' => "ALTER TABLE produtos ADD COLUMN cst_is VARCHAR(6) NULL AFTER aliquota_cbs",
        'cclass_trib_is' => "ALTER TABLE produtos ADD COLUMN cclass_trib_is VARCHAR(12) NULL AFTER cst_is",
        'aliquota_is' => "ALTER TABLE produtos ADD COLUMN aliquota_is DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER cclass_trib_is",
    ];

    foreach ($productFiscalColumns as $column => $sql) {
        if (!column_exists('produtos', $column)) {
            $pdo->exec($sql);
        }
    }

    $orderItemFiscalColumns = [
        'ncm' => "ALTER TABLE pedido_itens ADD COLUMN ncm VARCHAR(12) NULL AFTER nome_produto",
        'cfop' => "ALTER TABLE pedido_itens ADD COLUMN cfop VARCHAR(8) NULL AFTER ncm",
        'unidade' => "ALTER TABLE pedido_itens ADD COLUMN unidade VARCHAR(8) NULL AFTER cfop",
        'cst_ibs_cbs' => "ALTER TABLE pedido_itens ADD COLUMN cst_ibs_cbs VARCHAR(6) NULL AFTER unidade",
        'cclass_trib_ibs_cbs' => "ALTER TABLE pedido_itens ADD COLUMN cclass_trib_ibs_cbs VARCHAR(12) NULL AFTER cst_ibs_cbs",
        'aliquota_ibs_uf' => "ALTER TABLE pedido_itens ADD COLUMN aliquota_ibs_uf DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER cclass_trib_ibs_cbs",
        'aliquota_ibs_municipal' => "ALTER TABLE pedido_itens ADD COLUMN aliquota_ibs_municipal DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER aliquota_ibs_uf",
        'aliquota_cbs' => "ALTER TABLE pedido_itens ADD COLUMN aliquota_cbs DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER aliquota_ibs_municipal",
        'cst_is' => "ALTER TABLE pedido_itens ADD COLUMN cst_is VARCHAR(6) NULL AFTER aliquota_cbs",
        'cclass_trib_is' => "ALTER TABLE pedido_itens ADD COLUMN cclass_trib_is VARCHAR(12) NULL AFTER cst_is",
        'aliquota_is' => "ALTER TABLE pedido_itens ADD COLUMN aliquota_is DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER cclass_trib_is",
    ];

    foreach ($orderItemFiscalColumns as $column => $sql) {
        if (!column_exists('pedido_itens', $column)) {
            $pdo->exec($sql);
        }
    }

    $noteColumns = [
        'xml_path' => "ALTER TABLE notas_fiscais ADD COLUMN xml_path VARCHAR(255) NULL AFTER xml_url",
        'motivo_cancelamento' => "ALTER TABLE notas_fiscais ADD COLUMN motivo_cancelamento TEXT NULL AFTER danfe_url",
        'cancelada_em' => "ALTER TABLE notas_fiscais ADD COLUMN cancelada_em DATETIME NULL AFTER motivo_cancelamento",
        'carta_correcao' => "ALTER TABLE notas_fiscais ADD COLUMN carta_correcao TEXT NULL AFTER cancelada_em",
        'corrigida_em' => "ALTER TABLE notas_fiscais ADD COLUMN corrigida_em DATETIME NULL AFTER carta_correcao",
    ];

    foreach ($noteColumns as $column => $sql) {
        if (!column_exists('notas_fiscais', $column)) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec("ALTER TABLE notas_fiscais MODIFY status ENUM('pendente', 'transmitida', 'autorizada', 'cancelada', 'rejeitada', 'corrigida') NOT NULL DEFAULT 'pendente'");
    } catch (Throwable $e) {
        // Mantem a instalacao funcionando mesmo em bases com restricao para alterar ENUM.
    }

    if (!column_exists('chamados', 'protocolo')) {
        $pdo->exec("ALTER TABLE chamados ADD COLUMN protocolo VARCHAR(30) NULL AFTER id");
    }

    $pdo->exec(
        "UPDATE chamados
         SET protocolo = CONCAT('CA-', DATE_FORMAT(criado_em, '%Y%m%d'), '-', LPAD(id, 6, '0'))
         WHERE protocolo IS NULL OR protocolo = ''"
    );

    $pdo->exec("UPDATE chamados SET status = 'em_analise' WHERE status = 'em_atendimento'");
    $pdo->exec("UPDATE chamados SET status = 'finalizado' WHERE status IN ('resolvido', 'fechado')");

    $pdo->exec(
        "INSERT INTO chamados_mensagens (chamado_id, usuario_id, mensagem)
         SELECT ch.id, ch.usuario_id, ch.mensagem
         FROM chamados ch
         LEFT JOIN chamados_mensagens cm ON cm.chamado_id = ch.id
         WHERE cm.id IS NULL
           AND ch.mensagem IS NOT NULL
           AND ch.mensagem <> ''"
    );

    try {
        $pdo->exec("ALTER TABLE chamados MODIFY status ENUM('aberto', 'em_analise', 'pendente_informacao', 'finalizado') NOT NULL DEFAULT 'aberto'");
    } catch (Throwable $e) {
        // Bases antigas podem usar VARCHAR/status customizado; as telas validam os status permitidos.
    }

    $defaults = [
        ['fiscal.habilitado', '0', 'fiscal'],
        ['fiscal.ambiente', 'homologacao', 'fiscal'],
        ['fiscal.uf', 'RJ', 'fiscal'],
        ['fiscal.cnpj', '', 'fiscal'],
        ['fiscal.razao_social', '', 'fiscal'],
        ['fiscal.inscricao_estadual', '', 'fiscal'],
        ['fiscal.inscricao_municipal', '', 'fiscal'],
        ['fiscal.cnae', '', 'fiscal'],
        ['fiscal.crt', '1', 'fiscal'],
        ['fiscal.serie_nfe', '1', 'fiscal'],
        ['fiscal.proximo_numero_nfe', '1', 'fiscal'],
        ['fiscal.codigo_municipio', '', 'fiscal'],
        ['fiscal.endereco_logradouro', '', 'fiscal'],
        ['fiscal.endereco_numero', '', 'fiscal'],
        ['fiscal.endereco_bairro', '', 'fiscal'],
        ['fiscal.endereco_municipio', '', 'fiscal'],
        ['fiscal.endereco_cep', '', 'fiscal'],
        ['fiscal.certificado_pfx', '', 'fiscal'],
        ['fiscal.certificado_senha', '', 'fiscal'],
        ['fiscal.reforma_tributaria_habilitada', '0', 'fiscal'],
        ['efi.habilitado', '0', 'pagamento'],
        ['efi.pix_habilitado', '1', 'pagamento'],
        ['efi.cartao_habilitado', '0', 'pagamento'],
        ['efi.ambiente', 'sandbox', 'pagamento'],
        ['efi.client_id', '', 'pagamento'],
        ['efi.client_secret', '', 'pagamento'],
        ['efi.payee_code', '', 'pagamento'],
        ['efi.pix_chave', '', 'pagamento'],
        ['efi.certificado', '', 'pagamento'],
        ['efi.webhook_token', bin2hex(random_bytes(16)), 'pagamento'],
        ['efi.webhook_url', '', 'pagamento'],
        ['loja.vendas_habilitadas', '1', 'loja'],
        ['loja.mensagem_catalogo', 'A loja esta temporariamente funcionando como catalogo. As vendas online estao pausadas, mas os produtos podem ser visualizados normalmente.', 'loja'],
        ['email.habilitado', '0', 'email'],
        ['email.smtp_host', '', 'email'],
        ['email.smtp_port', '587', 'email'],
        ['email.smtp_usuario', '', 'email'],
        ['email.smtp_senha', '', 'email'],
        ['email.smtp_secure', 'tls', 'email'],
        ['email.remetente_email', '', 'email'],
        ['email.remetente_nome', 'Colo e Afeto', 'email'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO configuracoes (chave, valor, grupo) VALUES (:chave, :valor, :grupo)");
    foreach ($defaults as [$key, $value, $group]) {
        $stmt->execute(['chave' => $key, 'valor' => $value, 'grupo' => $group]);
    }

    $pdo->exec(
        "INSERT IGNORE INTO admin_perfis (nome, descricao, administrador, ativo)
         VALUES ('Administrador', 'Acesso total ao sistema.', 1, 1)"
    );
}
