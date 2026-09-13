# Manual: Configuração de envio de e-mail com provedores gratuitos

O sistema envia e-mails (recibos, cupom por e-mail, notificações de pedido) usando PHPMailer via SMTP.
Nenhum e-mail é enviado enquanto `email.habilitado` estiver desligado.

## 1. Onde configurar

Acesse o painel administrativo em **Configurações → E-mail** (`admin/configuracoes/index.php`) e preencha:

| Campo da tela                        | Chave interna      | Exemplo                          |
| ------------------------------------ | ------------------ | -------------------------------- |
| Habilitar envio de e-mails (checkbox) | `email.habilitado` | ✔                                |
| Remetente e-mail                     | `email.remetente_email` | `loja@coloafeto.com.br`      |
| Remetente nome                       | `email.remetente_nome`  | `Colo e Afeto`             |
| SMTP host                            | `email.smtp_host`  | `smtp.gmail.com`                  |
| Porta                                | `email.smtp_port`  | `587`                             |
| Segurança (TLS / SSL / Nenhuma)      | `email.smtp_secure` | `tls`                           |
| SMTP usuário                         | `email.smtp_usuario` | conta usada no SMTP            |
| SMTP senha                           | `email.smtp_senha`  | senha/senha de app da conta    |

Pré-requisito: a pasta `vendor/` com PHPMailer instalada. Se faltar, rode na raiz do sistema:

```bash
composer install
```

O status de cada envio fica na tabela `emails_envios` (`status` = `enviado`, `ignorado` ou `erro`).
Para testar: abra uma venda em **Histórico de vendas → Imprimir cupom → Enviar por e-mail**.

## 2. Gmail (recomendado para volume baixo)

O Gmail não aceita a senha normal da conta — é preciso gerar uma **senha de aplicativo**.

1. Ative a **Verificação em duas etapas** em `https://myaccount.google.com/security`.
2. Em Segurança → **Senhas de app** (`https://myaccount.google.com/apppasswords`) crie uma senha (tipo "E-mail").
3. Preencha:
   - SMTP host: `smtp.gmail.com`
   - Porta: `587`
   - Segurança: `tls`
   - Usuário: seu e-mail `@gmail.com` completo
   - Senha: a senha de app (16 caracteres, sem espaços)
4. Limite ~500 e-mails/dia. Para a loja em produção pode ser bloqueado como spam — monitorar.

Cuidado: se chegou um e-mail de segurança do Google com *"Sign-in attempt blocked"*,
confirme que está usando a **senha de app** e não a senha normal.

## 3. Outlook / Hotmail / Live

- SMTP host: `smtp-mail.outlook.com`
- Porta: `587`
- Segurança: `tls`
- Usuário: e-mail completo; Senha: a senha normal da conta
- Sem suporte a "environments" por app; volume baixo. Pode exigir autorização de app "menos segura".

## 4. Yahoo Mail

- SMTP host: `smtp.mail.yahoo.com`
- Porta: `465` com Segurança `ssl` (ou `587` com `tls`)
- Requer senha de aplicativo (gerada na conta Yahoo), senha normal não funciona.

## 5. Zoho Mail (gratuito)

- SMTP host: `smtp.zoho.com`
- Porta: `587`
- Segurança: `tls`
- Usuário/Senha: credenciais da conta Zoho. Boa opção gratuita com domínio próprio.

## 6. SendGrid (grátis – recomendado para produção)

Plano gratuito: ~100 e-mails/dia, ótimo para domínio próprio.

1. Crie conta em `https://sendgrid.com`, gere uma **API Key** com permissão de envio (Mail Send).
2. Preencha:
   - SMTP host: `smtp.sendgrid.net`
   - Porta: `587`
   - Segurança: `tls`
   - Usuário: `apikey` (literal)
   - Senha: a chave da API gerada
3. Remetente: confirme o domínio ou e-mail remetente no SendGrid (Sender Authentication) para o envio sair.

## 7. Teste rápido com e-mail virtual (Mailtrap)

Para validar a configuração sem enviar para destinatários reais:

- SMTP host: `sandbox.smtp.mailtrap.io`
- Porta: `587` (ou `2525`)
- Segurança: `tls`
- Usuário/Senha: os valores do seu Inbox do Mailtrap (não são dados reais de envio).

## 8. Produção: dicas rápidas (SPF/DKIM/DMARC)

Com domínio próprio, a entrega melhora muito configurando no DNS do domínio:

- **SPF**: `v=spf1 include:sendgrid.net ~all` (ou do provedor usado)
- **DKIM**: registro DNS informado pelo provedor
- **DMARC**: `v=DMARC1; p=none; rua=mailto:admin@coloafeto.local`

Sem SPF/DKIM os e-mails tendem a ir para spam quando se usa um domínio próprio.

## 9. Problemas comuns

| Sintoma | Causa provável | Solução |
| ------- | -------------- | ------- |
| `erro` na tabela `emails_envios` | Porta/Host errados | Conferir host/porta/segurança do provedor |
| Gmail "sign-in blocked" | Usando senha normal | Gerar senha de aplicativo |
| Spam = recibo | Sem SPF/DKIM | Configurar DNS do domínio |
| Nada é enviado | `email.habilitado` = 0 | Marcar o checkbox em Configurações → E-mail |
| PHPMailer não encontrado | `vendor/` ausente | Rodar `composer install` |