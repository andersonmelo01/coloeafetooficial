CREATE DATABASE IF NOT EXISTS colo_afeto
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE colo_afeto;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  tipo ENUM('admin', 'cliente', 'entregador') NOT NULL DEFAULT 'cliente',
  perfil_admin_id INT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL UNIQUE,
  descricao VARCHAR(255) NULL,
  administrador TINYINT(1) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_perfil_permissoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  perfil_id INT NOT NULL,
  permissao VARCHAR(80) NOT NULL,
  UNIQUE KEY uq_perfil_permissao (perfil_id, permissao),
  FOREIGN KEY (perfil_id) REFERENCES admin_perfis(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS clientes_perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo_pessoa ENUM('fisica', 'juridica') NOT NULL DEFAULT 'fisica',
  documento VARCHAR(20) NOT NULL,
  inscricao_estadual VARCHAR(30) NULL,
  telefone VARCHAR(30) NOT NULL,
  data_nascimento DATE NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS enderecos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  cep VARCHAR(12) NOT NULL,
  logradouro VARCHAR(180) NOT NULL,
  numero VARCHAR(20) NOT NULL,
  complemento VARCHAR(120) NULL,
  bairro VARCHAR(120) NOT NULL,
  cidade VARCHAR(120) NOT NULL,
  uf CHAR(2) NOT NULL,
  principal TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  descricao TEXT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grupos_produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  descricao TEXT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NULL,
  grupo_id INT NULL,
  nome VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  sku VARCHAR(60) NOT NULL UNIQUE,
  descricao_curta VARCHAR(255) NULL,
  descricao TEXT NULL,
  ncm VARCHAR(12) NOT NULL,
  cest VARCHAR(12) NULL,
  cfop VARCHAR(8) NOT NULL DEFAULT '5102',
  unidade VARCHAR(8) NOT NULL DEFAULT 'UN',
  origem_mercadoria VARCHAR(2) NULL,
  cst_icms VARCHAR(4) NULL,
  csosn VARCHAR(4) NULL,
  cst_pis VARCHAR(4) NULL,
  cst_cofins VARCHAR(4) NULL,
  aliquota_icms DECIMAL(7,4) NOT NULL DEFAULT 0,
  aliquota_pis DECIMAL(7,4) NOT NULL DEFAULT 0,
  aliquota_cofins DECIMAL(7,4) NOT NULL DEFAULT 0,
  cst_ibs_cbs VARCHAR(6) NULL,
  cclass_trib_ibs_cbs VARCHAR(12) NULL,
  aliquota_ibs_uf DECIMAL(7,4) NOT NULL DEFAULT 0,
  aliquota_ibs_municipal DECIMAL(7,4) NOT NULL DEFAULT 0,
  aliquota_cbs DECIMAL(7,4) NOT NULL DEFAULT 0,
  cst_is VARCHAR(6) NULL,
  cclass_trib_is VARCHAR(12) NULL,
  aliquota_is DECIMAL(7,4) NOT NULL DEFAULT 0,
  peso_kg DECIMAL(10,3) NOT NULL DEFAULT 0,
  altura_cm DECIMAL(10,2) NOT NULL DEFAULT 0,
  largura_cm DECIMAL(10,2) NOT NULL DEFAULT 0,
  comprimento_cm DECIMAL(10,2) NOT NULL DEFAULT 0,
  preco DECIMAL(10,2) NOT NULL,
  preco_promocional DECIMAL(10,2) NULL,
  custo DECIMAL(10,2) NOT NULL DEFAULT 0,
  estoque INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  destaque TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (grupo_id) REFERENCES grupos_produtos(id)
);

CREATE TABLE IF NOT EXISTS produto_imagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  caminho VARCHAR(255) NOT NULL,
  principal TINYINT(1) NOT NULL DEFAULT 0,
  ordem INT NOT NULL DEFAULT 0,
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  status ENUM('novo', 'aguardando_pagamento', 'pago', 'separacao', 'enviado', 'entregue', 'cancelado') NOT NULL DEFAULT 'novo',
  fiscal_status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  fiscal_mensagem TEXT NULL,
  confirmado_em DATETIME NULL,
  revisado_em DATETIME NULL,
  pagamento_status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  pagamento_payload LONGTEXT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  frete DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  forma_pagamento VARCHAR(40) NOT NULL,
  codigo_rastreio VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS pedido_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  produto_id INT NULL,
  nome_produto VARCHAR(180) NOT NULL,
  ncm VARCHAR(12) NULL,
  cfop VARCHAR(8) NULL,
  unidade VARCHAR(8) NULL,
  cst_ibs_cbs VARCHAR(6) NULL,
  cclass_trib_ibs_cbs VARCHAR(12) NULL,
  aliquota_ibs_uf DECIMAL(7,4) NOT NULL DEFAULT 0,
  aliquota_ibs_municipal DECIMAL(7,4) NOT NULL DEFAULT 0,
  aliquota_cbs DECIMAL(7,4) NOT NULL DEFAULT 0,
  cst_is VARCHAR(6) NULL,
  cclass_trib_is VARCHAR(12) NULL,
  aliquota_is DECIMAL(7,4) NOT NULL DEFAULT 0,
  quantidade INT NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE IF NOT EXISTS pagamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  provedor VARCHAR(60) NULL,
  metodo VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  transacao_id VARCHAR(120) NULL,
  valor DECIMAL(10,2) NOT NULL,
  pago_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

CREATE TABLE IF NOT EXISTS entregas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  entregador_id INT NULL,
  transportadora VARCHAR(120) NULL,
  servico VARCHAR(80) NULL,
  codigo_rastreio VARCHAR(80) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  observacao_entregador TEXT NULL,
  previsao_entrega DATE NULL,
  enviado_em DATETIME NULL,
  entregue_em DATETIME NULL,
  confirmado_por_entregador_em DATETIME NULL,
  motivo_cancelamento TEXT NULL,
  cancelada_em DATETIME NULL,
  cancelada_por INT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

CREATE TABLE IF NOT EXISTS entrega_tentativas (
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
);

CREATE TABLE IF NOT EXISTS notas_fiscais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  numero VARCHAR(30) NULL,
  serie VARCHAR(10) NULL,
  chave_acesso VARCHAR(60) NULL,
  protocolo VARCHAR(80) NULL,
  status ENUM('pendente', 'transmitida', 'autorizada', 'cancelada', 'rejeitada', 'corrigida') NOT NULL DEFAULT 'pendente',
  xml_url VARCHAR(255) NULL,
  xml_path VARCHAR(255) NULL,
  danfe_url VARCHAR(255) NULL,
  motivo_cancelamento TEXT NULL,
  cancelada_em DATETIME NULL,
  carta_correcao TEXT NULL,
  corrigida_em DATETIME NULL,
  emitida_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

CREATE TABLE IF NOT EXISTS movimentos_estoque (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  usuario_id INT NULL,
  tipo ENUM('entrada', 'saida', 'ajuste') NOT NULL,
  quantidade INT NOT NULL,
  saldo_anterior INT NOT NULL DEFAULT 0,
  saldo_posterior INT NOT NULL DEFAULT 0,
  motivo VARCHAR(160) NOT NULL,
  origem VARCHAR(80) NULL,
  observacao TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produto_id) REFERENCES produtos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS chamados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(30) NULL UNIQUE,
  usuario_id INT NOT NULL,
  tipo ENUM('reclamacao', 'elogio', 'duvida') NOT NULL,
  assunto VARCHAR(160) NOT NULL,
  mensagem TEXT NOT NULL,
  status ENUM('aberto', 'em_analise', 'pendente_informacao', 'finalizado') NOT NULL DEFAULT 'aberto',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS chamados_mensagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chamado_id INT NOT NULL,
  usuario_id INT NOT NULL,
  mensagem TEXT NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS configuracoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(120) NOT NULL UNIQUE,
  valor TEXT NULL,
  grupo VARCHAR(60) NOT NULL DEFAULT 'geral',
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS promocoes (
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
);

CREATE TABLE IF NOT EXISTS servico_galeria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  servico_slug VARCHAR(120) NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  descricao VARCHAR(255) NULL,
  caminho VARCHAR(255) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_servico_galeria_slug (servico_slug)
);

CREATE TABLE IF NOT EXISTS faturamento_eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  tipo VARCHAR(60) NOT NULL,
  status VARCHAR(60) NOT NULL,
  mensagem TEXT NULL,
  payload LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

CREATE TABLE IF NOT EXISTS webhook_eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provedor VARCHAR(60) NOT NULL,
  evento VARCHAR(120) NULL,
  status VARCHAR(60) NULL,
  pedido_id INT NULL,
  payload LONGTEXT NULL,
  processado TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emails_envios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NULL,
  destinatario_email VARCHAR(160) NOT NULL,
  destinatario_nome VARCHAR(160) NULL,
  assunto VARCHAR(180) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo)
VALUES ('Administrador', 'admin@coloafeto.local', '$2y$12$R9PH0ZlDfexkFG90eFVeye/805JaH6QakxSQBqHJRDs1xzF/Jdmzy', 'admin', 1)
ON DUPLICATE KEY UPDATE email = email;

INSERT INTO admin_perfis (nome, descricao, administrador, ativo)
VALUES ('Administrador', 'Acesso total ao sistema.', 1, 1)
ON DUPLICATE KEY UPDATE nome = nome;

INSERT INTO categorias (nome, slug, descricao) VALUES
('Amamentação', 'amamentacao', 'Produtos para a jornada de amamentação.'),
('Bebê', 'bebe', 'Produtos para cuidados com o bebê.'),
('Pós-parto', 'pos-parto', 'Produtos para recuperação e conforto no pós-parto.')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO grupos_produtos (nome, slug, descricao) VALUES
('Kits', 'kits', 'Combos e kits especiais.'),
('Produtos físicos', 'produtos-fisicos', 'Produtos individuais para venda e entrega.')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO produtos
(categoria_id, grupo_id, nome, slug, sku, descricao_curta, descricao, ncm, cfop, unidade, peso_kg, altura_cm, largura_cm, comprimento_cm, preco, preco_promocional, custo, estoque, ativo, destaque)
SELECT c.id, g.id, 'Kit Amamentação Tranquila', 'kit-amamentacao-tranquila', 'KIT-AMAM-001',
'Almofada, coletor e protetores para uma rotina mais leve.', 'Kit especial para apoio durante a amamentação.', '63079090', '5102', 'UN', 1.200, 18, 35, 45, 199.90, 179.90, 95.00, 12, 1, 1
FROM categorias c, grupos_produtos g
WHERE c.slug = 'amamentacao' AND g.slug = 'kits'
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO configuracoes (chave, valor, grupo) VALUES
('fiscal.habilitado', '0', 'fiscal'),
('fiscal.ambiente', 'homologacao', 'fiscal'),
('fiscal.uf', 'RJ', 'fiscal'),
('fiscal.cnpj', '', 'fiscal'),
('fiscal.razao_social', '', 'fiscal'),
('fiscal.inscricao_estadual', '', 'fiscal'),
('fiscal.inscricao_municipal', '', 'fiscal'),
('fiscal.cnae', '', 'fiscal'),
('fiscal.crt', '1', 'fiscal'),
('fiscal.serie_nfe', '1', 'fiscal'),
('fiscal.proximo_numero_nfe', '1', 'fiscal'),
('fiscal.codigo_municipio', '', 'fiscal'),
('fiscal.endereco_logradouro', '', 'fiscal'),
('fiscal.endereco_numero', '', 'fiscal'),
('fiscal.endereco_bairro', '', 'fiscal'),
('fiscal.endereco_municipio', '', 'fiscal'),
('fiscal.endereco_cep', '', 'fiscal'),
('fiscal.certificado_pfx', '', 'fiscal'),
('fiscal.certificado_senha', '', 'fiscal'),
('fiscal.reforma_tributaria_habilitada', '0', 'fiscal'),
('efi.habilitado', '0', 'pagamento'),
('efi.pix_habilitado', '1', 'pagamento'),
('efi.cartao_habilitado', '0', 'pagamento'),
('efi.ambiente', 'sandbox', 'pagamento'),
('efi.client_id', '', 'pagamento'),
('efi.client_secret', '', 'pagamento'),
('efi.payee_code', '', 'pagamento'),
('efi.pix_chave', '', 'pagamento'),
('efi.certificado', '', 'pagamento'),
('efi.webhook_token', '', 'pagamento'),
('efi.webhook_url', '', 'pagamento'),
('loja.vendas_habilitadas', '1', 'loja'),
('loja.mensagem_catalogo', 'A loja está temporariamente funcionando como catálogo. As vendas online estão pausadas, mas os produtos podem ser visualizados normalmente.', 'loja'),
('email.habilitado', '0', 'email'),
('email.smtp_host', '', 'email'),
('email.smtp_port', '587', 'email'),
('email.smtp_usuario', '', 'email'),
('email.smtp_senha', '', 'email'),
('email.smtp_secure', 'tls', 'email'),
('email.remetente_email', '', 'email'),
('email.remetente_nome', 'Colo e Afeto', 'email')
ON DUPLICATE KEY UPDATE chave = chave;
