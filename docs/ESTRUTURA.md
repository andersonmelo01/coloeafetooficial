# Estrutura do Projeto Colo e Afeto

Este projeto foi organizado em PHP puro com Bootstrap 5, CSS e JavaScript.

## Pastas principais

- `assets/`: CSS e JavaScript globais.
- `includes/`: funcoes compartilhadas, header, footer e sessao.
- `database/`: schema MySQL inicial.
- `loja/`: vitrine de produtos com pesquisa e compra.
- `carrinho/`: adicionar, atualizar e remover itens.
- `checkout/`: fechamento de pedido para cliente logado.
- `auth/`: login, logout e cadastro fiscal do cliente.
- `cliente/`: dashboard, historico de compras, NF-e e chamados.
- `admin/`: dashboard do gestor e modulos de produtos, categorias, grupos, pedidos, entregas, entregadores, clientes, NF-e, chamados e relatorios.
- `entregador/`: area do entregador para consultar entregas vinculadas e confirmar entrega.
- `storage/certificados/`: certificados enviados pelo painel de configuracoes. A pasta possui `.htaccess` para bloquear acesso HTTP direto no Apache.
- `storage/nfe/`: XMLs de NF-e salvos pelo modulo de faturamento. A pasta possui `.htaccess` para bloquear acesso HTTP direto no Apache.

## Banco de dados

O arquivo de conexao solicitado esta em `conexao.php`.

1. Ao acessar `auth/login.php`, o sistema tenta criar automaticamente o banco e as tabelas usando `database/schema.sql`.
2. Se preferir, importe manualmente o banco usando `database/schema.sql`.
3. Confira usuario e senha do MySQL em `conexao.php`.
4. Primeiro acesso do gestor:
   - E-mail: `admin@coloafeto.local`
   - Senha: `admin123`

Troque essa senha antes de publicar.

## Observacoes de producao

- Instalar dependencias com Composer quando for usar integracoes:
  - `composer install`
  - SPED-NFe: `nfephp-org/sped-nfe`
  - Efi Bank: `efipay/sdk-php-apis-efi`
  - E-mail: `phpmailer/phpmailer`
- O modulo fiscal pode ser habilitado/desabilitado em `admin/configuracoes/index.php`.
- Com fiscal habilitado, o gestor deve revisar o pedido e validar dados fiscais antes de confirmar.
- Com fiscal desabilitado, o sistema permite vendas sem controle fiscal.
- O modulo de faturamento foi preparado para SPED-NFe e SEFAZ, incluindo eventos de faturamento, validacao fiscal, status e NF-e pendente.
- A listagem de produtos sinaliza pendencias fiscais como NCM, CFOP, origem da mercadoria, CST/CSOSN e tributacao basica antes da emissao.
- O cadastro de produtos e os itens do pedido possuem campos preparados para a Reforma Tributaria do Consumo: CST IBS/CBS, classificacao tributaria IBS/CBS, aliquotas IBS UF/Municipal, aliquota CBS e campos de IS quando aplicavel.
- A validacao dos campos da Reforma Tributaria pode ser habilitada em `admin/configuracoes/index.php`; quando desabilitada, o sistema mostra alertas de revisao sem bloquear a preparacao fiscal.
- O modulo de faturamento possui emissao/preparacao em lote e tela de gestao da NF-e para salvar/abrir XML, registrar cancelamento e carta de correcao.
- Para NF-e em producao, preencha em `admin/configuracoes/index.php`: CNPJ, IE, CRT, serie, proximo numero, endereco do emitente, certificado A1/PFX, senha e ambiente. O certificado pode ser enviado por upload para evitar erro de digitacao do caminho.
- Cancelamento e carta de correcao ficam registrados para auditoria; em producao, conclua a transmissao dos eventos pela SPED-NFe com retorno autorizado da SEFAZ.
- O modulo de pagamento foi preparado para Efi Bank, com configuracoes para Pix QR Code e cartao de credito.
- Para cartao de credito Efi, preencha tambem o `Identificador de conta/payee_code`, pois a Efi exige `payment_token` gerado no front-end.
- Configure na Efi o webhook apontando para `webhooks/efi.php?token=SEU_TOKEN`. Quando o pagamento for confirmado, o sistema atualiza automaticamente o pedido para pago.
- Configure SMTP em `admin/configuracoes/index.php` para enviar e-mails de confirmacao de pedido, alteracao de status, saida para entrega e entrega concluida. Os envios ficam registrados em `emails_envios`.
- O gestor cadastra entregadores em `admin/entregadores/index.php` e vincula pedidos em `admin/entregas/index.php`.
- O entregador acessa `entregador/index.php`, visualiza apenas suas entregas e confirma a entrega; isso atualiza o pedido para entregue e notifica o cliente por e-mail.
- O modulo `admin/relatorios/index.php` consolida indicadores por periodo para pedidos, pagamentos, produtos, estoque, fiscal/NF-e, entregas, chamados, clientes, catalogo, promocoes e e-mails. O relatorio de entregas mostra quantas entregas cada entregador confirmou no mes.
- O modulo `admin/perfis` cria perfis administrativos com permissoes por modulo. O modulo `admin/gestores` cadastra usuarios administrativos e vincula cada gestor a um perfil.
- Administradores sem perfil limitado, ou com perfil marcado como Administrador, possuem acesso total. Perfis limitados so visualizam menus autorizados e tambem sao bloqueados ao acessar URLs sem permissao.
- O modulo `admin/estoque` registra entrada, saida por avaria/dano, saida manual e ajuste, sempre com usuario, saldo anterior e saldo posterior para auditoria.
- O modulo de chamados gera protocolo automaticamente, permite conversa entre cliente e gestor, troca de status e bloqueia novas mensagens do cliente quando o chamado estiver finalizado.
- Integrar calculo de frete/transportadora.
- Adicionar upload de imagens de produtos.
- Configurar HTTPS, backups, logs e regras de seguranca no servidor.
