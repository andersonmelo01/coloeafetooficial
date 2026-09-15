# ColoAfeto PDV — Guia Completo de Configuração

Guia passo-a-passo para instalar, configurar e atualizar o sistema PDV mobile
da ColoAfeto em produção.

---

## Sumário

1. [Visão geral do sistema](#1-visão-geral)
2. [Pré-requisitos](#2-pré-requisitos)
3. [Banco de dados (produção)](#3-banco-de-dados)
4. [Backend PHP (Apache2/VPS)](#4-backend-php)
5. [App mobile (instalação e build)](#5-app-mobile)
6. [Configuração do app para produção](#6-configuração-do-app)
7. [Testes de integração](#7-testes)
8. [Operação e manutenção](#8-operação)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Visão geral do sistema

```
┌──────────────────────────────────────────────────────┐
│  APP MOBILE (Expo / React Native)                    │
│  Expo SDK 57 · RN 0.86.3 · TypeScript               │
│  SecureStore (token + URL da API)                    │
└────────────────────┬─────────────────────────────────┘
                     │  HTTPS (JSON + Bearer token)
                     ▼
┌──────────────────────────────────────────────────────┐
│  API PHP  api/pdv/*.php                              │
│  bootstrap.php → functions.php → conexao.php → MySQL │
│  Regras de negócio: admin/vendas/_pdv.php            │
│  Gateway de pagamento: Efi (pix/cartão)              │
│  Cupom fiscal: CupomService.php                      │
└────────────────────┬─────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────┐
│  BANCO MySQL (colo_afeto)                            │
│  Mesmas tabelas do site/painel administrativo        │
│  Tabela adicional: mobile_tokens (auto-criada)       │
└──────────────────────────────────────────────────────┘
```

O app mobile é **apenas o front de vendas balcão** (PDV). Ele não cria/modifica
schema — lê e escreve exatamente as mesmas tabelas do PDV web.

**Fluxo de dados:**  
O app envia e-mail + senha → `login.php` valida no banco `usuarios`, gera um
token 64-byte (armazenado em `mobile_tokens` com expiração de 45 dias) e retorna
as configurações do PDV (`mobile_config`). Todas as requisições subsequentes usam
`Authorization: Bearer <token>`.

---

## 2. Pré-requisitos

### VPS (Ubuntu + Apache2)

| Item | Versão mínima | Notas |
|------|---------------|-------|
| Ubuntu Server | 20.04+ | 22.04 recomendado |
| Apache | 2.4+ | `mod_rewrite` habilitado |
| PHP | 8.1+ | Extensões: `pdo_mysql`, `json`, `openssl`, `session` |
| MariaDB/MySQL | 10.3+ | Ou MySQL 8.0+ |
| Certificado SSL | — | Let's Encrypt (obrigatório para o app) |

### Desenvolvimento local

| Item | Versão |
|------|--------|
| Node.js | 20+ |
| npm | 10+ |
| Expo CLI | `npx expo` (via npx) |
| EAS CLI | `npx eas-cli` (para build de APK) |

---

## 3. Banco de dados (produção)

O app usa **o mesmo banco de dados** do site/loja. Não há scripts de
migração separados — as tabelas necessárias já existem no schema do site.

### 3.1 Criar banco e usuário de produção

No MySQL/MariaDB da VPS, execute:

```sql
-- Criar banco (caso ainda não exista)
CREATE DATABASE IF NOT EXISTS colo_afeto
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- Criar usuário dedicado para a API
CREATE USER IF NOT EXISTS 'colo_afeto_user'@'localhost'
IDENTIFIED BY 'SUA_SENHA_FORTE_AQUI';

-- Dar permissões apenas no banco do projeto
GRANT ALL PRIVILEGES ON colo_afeto.* TO 'colo_afeto_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3.2 Importar/verificar schema

Se estiver fazendo deploy em uma VPS nova:

```bash
# Importar dump do banco (se disponível)
mysql -u root -p colo_afeto < /caminho/do/dump.sql
```

Se o banco já existe (migrando de outro servidor), basta copiar.

### 3.3 Tabela mobile_tokens

**Não é necessário criar manualmente.** O `bootstrap.php` cria automaticamente:

```sql
CREATE TABLE IF NOT EXISTS mobile_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    ultimo_uso DATETIME DEFAULT NULL,
    INDEX idx_mobile_tokens_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Configurações do PDV no painel administrativo

O app lê as configurações do PDV de uma tabela `configuracoes`. As chaves
relevantes são:

| Chave | Valores | Efeito no app |
|-------|---------|---------------|
| `pdv.controle_estoque` | `0` / `1` | Habilita aviso de estoque baixo |
| `fiscal.habilitado` | `0` / `1` | Mostra opção de NFC-e no checkout |
| `efi.habilitado` | `0` / `1` | Habilita pagamentos via Efi (PIX/cartão) |
| `fiscal.razao_social` | texto | Nome do estabelecimento exibido no app |

Essas configurações são gerenciadas pelo painel admin do site (Configurações).

---

## 4. Backend PHP (Apache2/VPS)

### 4.1 Estrutura de arquivos do projeto

O projeto inteiro vive numa pasta chamada `ColoAfeto`. O app PDV é um submódulo
dentro dela:

```
/var/www/html/ColoAfeto/              ← DocumentRoot do projeto
├── conexao.php                       ← Configuração do banco (DB_HOST, DB_USER, etc.)
├── includes/
│   ├── functions.php                 ← Funções globais (db(), base_url(), app_config...)
│   ├── CupomService.php              ← Serviço de cupom fiscal
│   └── EfiService.php                ← Gateway de pagamento Efi (PIX/cartão)
├── admin/
│   └── vendas/
│       └── _pdv.php                  ← Regras de negócio do PDV (pdv_stock_control, pdv_caixa, etc.)
└── api/
    └── pdv/                          ← ← ← A API REST que o app consome
        ├── bootstrap.php             ← Bootstrap: CORS, helpers, auto-cria mobile_tokens
        ├── login.php                 ← POST /login.php  (email+senha → token)
        ├── logout.php                ← POST /logout.php (invalida token)
        ├── me.php                    ← GET  /me.php     (dados do usuario logado)
        ├── produtos.php              ← GET  /produtos.php (busca, categoria, paginação)
        ├── categorias.php            ← GET  /categorias.php
        ├── clientes.php              ← GET  /clientes.php (busca por nome)
        ├── caixa.php                 ← GET/POST (abrir/fechar/sangria/suprimento)
        ├── venda.php                 ← POST /venda.php  (criar venda completa)
        ├── historico.php             ← GET  /historico.php (lista + detalhe de vendas)
        ├── cancelar.php              ← POST /cancelar.php (cancela venda + estorno)
        └── cupom.php                 ← POST /cupom.php  (envia cupom por e-mail)
```

> **Importante:** `bootstrap.php` inclui `../../includes/functions.php` e
> `../../admin/vendas/_pdv.php`. A estrutura de pastas DEVE ser mantida.

### 4.2 Configurar o banco em produção

Edite `conexao.php` na raiz do projeto:

```php
// Produção (descomente e ajuste):
const DB_HOST = 'localhost';
const DB_NAME = 'colo_afeto';
const DB_USER = 'colo_afeto_user';    // ← usuário criado no passo 3.1
const DB_PASS = 'SUA_SENHA_FORTE';    // ← senha desse usuário
const DB_CHARSET = 'utf8mb4';
```

### 4.3 Configurar o Virtual Host do Apache

```apache
<VirtualHost *:443>
    ServerName www.coloafetooficial.com.br
    DocumentRoot /var/www/html

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/www.coloafetooficial.com.br/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/www.coloafetooficial.com.br/privkey.pem

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/coloafeto-error.log
    CustomLog ${APACHE_LOG_DIR}/coloafeto-access.log combined
</VirtualHost>
```

Depois reinicie o Apache:

```bash
sudo systemctl reload apache2
```

### 4.4 SSL com Let's Encrypt

```bash
# Instalar certbot (se ainda não tiver)
sudo apt install certbot python3-certbot-apache

# Gerar certificado
sudo certbot --apache -d www.coloafetooficial.com.br

# Renovação automática (verifique)
sudo certbot renew --dry-run
```

### 4.5 Habilitar mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 4.6 Testar o backend

```bash
# Testar login (substitua credenciais)
curl -X POST https://www.coloafetooficial.com.br/ColoAfeto/api/pdv/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@coloafeto.local","senha":"admin123"}'

# Deve retornar JSON com { "ok": true, "token": "abc123...", ... }
```

---

## 5. App mobile (instalação e build)

### 5.1 Instalar dependências

```bash
cd mobile
npm install
npx expo install --check    # sincroniza versões dos pacotes Expo
npx expo install expo-font  # dependência peer de @expo/vector-icons
```

### 5.2 Verificar integridade

```bash
# TypeScript deve compilar sem erros
npx tsc --noEmit

# Verificação Expo (21 checks)
npx expo-doctor
```

Ambos devem terminar com EXIT=0 / 21/21 checks.

### 5.3 Rodar em desenvolvimento

```bash
npx expo start
```

Escaneie o QR Code com o **Expo Go** no celular (mesma rede Wi-Fi).

### 5.4 Gerar APK de produção (EAS Build)

```bash
# Instalar EAS CLI (se ainda não tiver)
npm install -g eas-cli

# Fazer login na Expo
eas login

# Configurar projeto (primeira vez)
eas build:configure

# Gerar build Android (profile: production)
eas build -p android --profile production
```

O APK/ABB será disponibilizado para download no painel da Expo.

### 5.5 Gerar AAB (para Google Play)

O profile `production` no `eas.json` deve gerar AAB por padrão:

```json
{
  "build": {
    "production": {
      "android": {
        "buildType": "app-bundle"
      }
    }
  }
}
```

---

## 6. Configuração do app para produção

### 6.1 URL da API (padrão)

O app aponta para a URL de produção por padrão em `src/api/client.ts`:

```ts
export const DEFAULT_API_URL =
  'https://www.coloafetooficial.com.br/ColoAfeto/api/pdv';
```

> **Se o domínio ou caminho for diferente**, altere esta constante antes do build.
> O app também permite trocar a URL em tempo de execução (ver 6.2).

### 6.2 Trocar URL pelo app (sem recompilar)

O app salva a URL da API em **SecureStore** (iOS Keychain / Android Keystore).
Para trocar:

1. Na tela de **Login**, toque em **"Configurar endereço do servidor"**
2. Informe o novo endereço (ex.: `https://www.coloafetooficial.com.br/ColoAfeto/api/pdv`)
3. Faça login — a URL fica salva automaticamente

Ou, após login, vá em **Ajustes** (aba inferior) → campo "Endereço do PDV" → Salvar.

### 6.3 Credenciais de acesso

O login aceita **apenas**:

- Usuários com `tipo = 'admin'` na tabela `usuarios`
- Que tenham permissão para o módulo de vendas (perfil com `administrador = 1` ou permissão de vendas)
- Senha conferindo com `senha_hash` (bcrypt)

Se o usuário não tiver permissão, o app mostra erro 403.

### 6.4 Caixa

Antes de registrar vendas, é necessário **abrir o caixa** na aba "Caixa". Se
o caixa estiver fechado, o app mostra a tela de abertura ao acessar o PDV.

---

## 7. Testes de integração

Após deploy, teste o fluxo completo:

### 7.1 Backend isolado

```bash
# 1. Login
curl -s -X POST https://www.coloafetooficial.com.br/ColoAfeto/api/pdv/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"SEU_EMAIL","senha":"SUA_SENHA"}' | jq .

# 2. Salvar o token da resposta
TOKEN="cole_o_token_aqui"

# 3. Buscar produtos
curl -s "https://www.coloafetooficial.com.br/ColoAfeto/api/pdv/produtos.php" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 4. Verificar dados do usuário
curl -s "https://www.coloafetooficial.com.br/ColoAfeto/api/pdv/me.php" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

### 7.2 App + Backend

1. Instale o APK no celular
2. Na primeira abertura, informe o endereço da API (se não for o padrão)
3. Faça login
4. Abra o caixa → Adicione itens ao carrinho → Finalize uma venda
5. Verifique se a venda aparece no histórico e no painel admin

### 7.3 Verificar bundle

```bash
cd mobile
npx expo export --platform android   # bundle de produção (934 módulos esperados)
```

Deve terminar com EXIT=0.

---

## 8. Operação e manutenção

### 8.1 Atualizar o backend

1. Substitua os arquivos PHP na VPS
2. Não há migrações — o `bootstrap.php` auto-cria `mobile_tokens`
3. Se houver novas tabelas, importe o schema atualizado
4. Teste com `curl` antes de liberar

### 8.2 Atualizar o app

1. Altere o código no repositório local
2. Verifique: `npx tsc --noEmit && npx expo-doctor`
3. Gere novo build: `eas build -p android --profile production`
4. Distribua o APK ou publique na Google Play

### 8.3 Tokens expirados

Tokens do PDV duram **45 dias** (configurado em `login.php`). Se expirarem,
o app redireciona automaticamente para o login. O usuário só precisa fazer
login novamente.

### 8.4 Logs

- **Apache:** `/var/log/apache2/coloafeto-error.log`
- **PHP:** erros aparecem no response JSON (`{ "ok": false, "error": "..." }`)
- **App:** não gera logs persistentes; erros são exibidos como toast na tela

### 8.5 Segurança

| Aspecto | Implementação |
|---------|---------------|
| Transporte | HTTPS obrigatório (app recusa HTTP) |
| Token | SHA-256 hash no banco; raw só em memória/SecureStore |
| Armazenamento | iOS: Keychain · Android: Keystore (via expo-secure-store) |
| Senhas | bcrypt no banco (`senha_hash`) |
| CORS | Configurado em `bootstrap.php` (aceita todas as origens para o PDV) |
| Expiração | 45 dias; redo de login automático |

---

## 9. Troubleshooting

### App não conecta ao servidor

- Verifique se a URL está correta (barra final não é necessária)
- Confirme que HTTPS está habilitado (o app recusa HTTP em produção)
- Teste com `curl` isolado para confirmar que a API responde
- Verifique se o CORS não está bloqueando (teste no browser: acessar a URL direto)

### Login retorna 403 "sem permissão"

- O usuário precisa ter `tipo = 'admin'` na tabela `usuarios`
- E ter permissão de vendas (perfil com `administrador = 1` ou módulo vendas ativo)
- Verifique no painel admin → Usuários → Perfil do usuário

### Tela de caixa não aparece

- O app detecta `caixa` na resposta de `/me.php` → se vier `null`, mostra tela de abertura
- Verifique se há caixa aberto no banco: `SELECT * FROM caixas WHERE status = 'aberto'`

### Build Expo falha

```bash
# Verificar dependências
npx expo-doctor

# Limpar cache
npx expo r -c

# Verificar se todas as dependências peer estão instaladas
npx expo install --check
```

### Erro de bundling (Metro)

```bash
# Limpar cache do Metro
npx expo start --clear

# Reinstalar node_modules
rm -rf node_modules && npm install
```

---

## Referência rápida

| Componente | Caminho |
|------------|---------|
| API PHP | `ColoAfeto/api/pdv/` |
| Configuração do banco | `ColoAfeto/conexao.php` |
| Regras de negócio PDV | `ColoAfeto/admin/vendas/_pdv.php` |
| Código-fonte do app | `mobile/src/` |
| Configuração do Expo | `mobile/app.json` |
| URL da API no app | `mobile/src/api/client.ts` (linha 5) |
| Navegação | `mobile/src/navigation/index.tsx` |
| Documentação | `mobile/README.md`, `mobile/CONFIGURACAO.md` |
