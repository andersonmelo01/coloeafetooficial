# Manual: Atualização do sistema na VPS via GitHub

Este passo a passo atualiza o código já instalado na VPS (veja `docs/INSTALACAO_VPS_UBUNTU.md`)
**sem perder os dados**, pois o banco de dados da VPS já está alimentado.

> Resumo: o banco não é recriado em nenhuma atualização. As mudanças de estrutura
> (novas tabelas, colunas e configurações) são aplicadas **automaticamente e de forma
> idempotente** no primeiro acesso após o `git pull`, através do `conexao.php`
> (`ensure_application_migrations`). Não é preciso rodar migração manual.

## Premissas

- A VPS já tem o projeto instalado via `git` em `/var/www/coloafeto` (ajuste o caminho se for outro).
- Branch padrão: `main` (substitua pela branch usada no repositório).
- Acesso SSH com usuário que possa usar `sudo`.

## 1. Backup antes de atualizar (recomendado)

```bash
cd /var/www/coloafeto

# 1. Backup do banco
sudo mysqldump -u root -p your_db_name > ~/backup_$(date +%F_%H%M).sql

# 2. Backup do código atual (git já guarda o histórico; este é extra)
sudo tar czf ~/backup_codigo_$(date +%F_%H%M).tar.gz --exclude=vendor --exclude=uploads .
```

Ajuste `your_db_name` para o nome real do banco (ex.: `colo_afeto`).

## 2. Baixar a nova versão

```bash
cd /var/www/coloafeto

sudo git status            # confira se não há alterações locais não commitadas
sudo git fetch origin
sudo git diff main origin/main --stat   # veja o que vai mudar (opcional)
sudo git pull origin main
```

Se houver alterações locais que travem o `pull`:

```bash
sudo git stash
sudo git pull origin main
sudo git stash pop
```

## 3. Dependências (só se `composer.json` mudou)

```bash
cd /var/www/coloafeto
sudo composer install --no-dev --optimize-autoloader
```

## 4. Permissões (uploads, storage e logs)

Mantenha as pastas de escrita sob o usuário do web server:

```bash
sudo chown -R www-data:www-data /var/www/coloafeto/uploads /var/www/coloafeto/storage
sudo chmod -R 775 /var/www/coloafeto/uploads /var/www/coloafeto/storage
```

> O `git pull` pode alterar o dono de arquivos novos para o usuário do `sudo`.
> O `chown` acima corrige isso rapidamente.

## 5. Estrutura do banco — sem ação manual

No primeiro acesso após atualizar (qualquer página do sistema), o `conexao.php`
aplica, se necessário e de forma idempotente:

- novas tabelas (`CREATE TABLE IF NOT EXISTS`);
- novas colunas e índices;
- novos registros de configuração (`INSERT IGNORE`);
- alterações de ENUM (ex.: `vendas.status` passou a aceitar `'pendente'`).

Para validar a estrutura, abra no navegador:

```text
https://coloafeto.com.br/schema_diag.php
```

Deve exibir `OK`.

## 6. Testar a versão nova

1. Faça login em `https://coloafeto.com.br/auth/login.php`.
2. Abra o painel e confira uma venda no **Histórico de vendas**.
3. Verifique o SMTP/e-mail em **Configurações → E-mail** (configurações ficam no banco, não são sobrescritas).
4. Se a loja usa pagamentos online, confirme as credenciais da Efi em **Configurações** e
   que o webhook continua apontando para `https://coloafeto.com.br/webhooks/efi.php?token=SEU_TOKEN`.

## 7. Rollback (voltar para a versão anterior)

```bash
cd /var/www/coloafeto
sudo git log --oneline -10             # localize o commit anterior à atualização
sudo git reset --hard <commit-anterior>
sudo composer install --no-dev --optimize-autoloader   # se houve mudança de dependências
sudo chown -R www-data:www-data /var/www/coloafeto/uploads /var/www/coloafeto/storage
```

Se a atualização tinha **alterado dados do banco** (raro – as migrações só criam estrutura),
restaure o backup:

```bash
sudo mysql -u root -p your_db_name < ~/backup_YYYY-MM-DD_HHMM.sql
```

## 8. Frequência recomendada

- Atualização **será feita quando o desenvolvedor publicar** no GitHub uma versão testada.
- Antes de atualizar em produção, atualize primeiro em um ambiente de testes/homologação.
- Mantenha as senhas do banco e credenciais fora do código (no `app_config`/banco ou `config.php`
  fora do Git), para nunca haver segredo no histórico do repositório.

## 9. Autenticação do GitHub na VPS

- Repositórios privados pedem credencial. Opções:
  1. **Deploy key** (recomendado): `ssh-keygen` na VPS e a chave pública adicionada em
     GitHub → Repositório → Settings → Deploy keys, com acesso apenas de leitura (`git pull`).
  2. **Personal access token** (PAT) com escopo `repo`, usado na URL remota
     `https://<user>:<token>@github.com/org/repo.git`.
- Confira a origem atual:

```bash
cd /var/www/coloafeto
sudo git remote -v
```