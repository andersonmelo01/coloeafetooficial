# Manual de instalacao em VPS Ubuntu com Apache2 e MySQL

Este manual instala o projeto Colo e Afeto em uma VPS Ubuntu usando Apache2, MySQL, PHP e Composer.

## 1. Premissas

- VPS com Ubuntu Server 22.04 LTS ou 24.04 LTS.
- Acesso SSH com usuario que possa usar `sudo`.
- Dominio ou subdominio apontando para o IP da VPS.
- Codigo do projeto disponivel por Git, SFTP, SCP ou arquivo compactado.

Nos exemplos abaixo:

- Dominio: `coloafeto.com.br`
- Pasta do projeto: `/var/www/coloafeto`
- Banco de dados: `colo_afeto`
- Usuario do banco: `colo_afeto_user`

Troque esses valores pelos dados reais do servidor.

## 2. Atualizar o servidor

```bash
sudo apt update
sudo apt upgrade -y
sudo timedatectl set-timezone America/Sao_Paulo
```

## 3. Instalar Apache2, MySQL, PHP e dependencias

```bash
sudo apt install -y apache2 mysql-server \
  php libapache2-mod-php php-cli php-mysql php-curl php-mbstring \
  php-xml php-zip php-gd php-intl php-soap unzip git curl composer
```

Habilite modulos uteis do Apache:

```bash
sudo a2enmod rewrite headers ssl
sudo systemctl enable apache2
sudo systemctl enable mysql
sudo systemctl restart apache2
```

Confira as versoes:

```bash
php -v
apache2 -v
mysql --version
composer --version
```

O projeto exige PHP `>= 8.1`.

## 4. Proteger a instalacao do MySQL

Execute o assistente de seguranca:

```bash
sudo mysql_secure_installation
```

Em producao, remova usuarios anonimos, desabilite login remoto do root e remova o banco de teste quando o assistente perguntar.

## 5. Criar banco e usuario MySQL

Entre no MySQL:

```bash
sudo mysql
```

Crie o banco, usuario e permissoes:

```sql
CREATE DATABASE IF NOT EXISTS colo_afeto
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'colo_afeto_user'@'localhost'
  IDENTIFIED BY 'And95079@@';

GRANT ALL PRIVILEGES ON colo_afeto.* TO 'colo_afeto_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Guarde a senha em local seguro. Ela sera usada no arquivo `conexao.php`.

## 6. Enviar o projeto para a VPS

Crie a pasta:

```bash
sudo mkdir -p /var/www/html/coloeafetooficial
sudo chown -R "$USER":www-data /var/www/html/coloeafetooficial
```

Se o projeto estiver em um repositorio Git:

```bash
git clone URL_DO_REPOSITORIO /var/www/coloeafetooficial
```

Se o projeto for enviado por SFTP/SCP, copie todos os arquivos para:

```text
/var/www/coloafeto
```

A estrutura final deve conter arquivos como:

```text
/var/www/coloeafetooficial/index.php
/var/www/coloeafetooficial/conexao.php
/var/www/coloeafetooficial/database/schema.sql
/var/www/coloeafetooficial/admin/
/var/www/coloeafetooficial/loja/
```

## 7. Configurar a conexao com o banco

Edite o arquivo:

```bash
nano /var/www/coloeafetooficial/conexao.php
```

Altere as constantes iniciais:

```php
const DB_HOST = 'localhost';
const DB_NAME = 'colo_afeto';
const DB_USER = 'colo_afeto_user';
const DB_PASS = 'TROQUE_POR_UMA_SENHA_FORTE';
const DB_CHARSET = 'utf8mb4';
```

Nao use o usuario `root` do MySQL em producao.

## 8. Instalar dependencias PHP com Composer

Dentro da pasta do projeto:

```bash
cd /var/www/coloafeto
composer install --no-dev --optimize-autoloader
```

As principais dependencias do projeto sao:

- `nfephp-org/sped-nfe`
- `efipay/sdk-php-apis-efi`
- `phpmailer/phpmailer`

## 9. Importar o schema do banco

O sistema tenta criar o banco e as tabelas automaticamente ao acessar `auth/login.php`, usando `database/schema.sql`.

Para instalar manualmente, execute como administrador do MySQL, pois o arquivo tambem contem `CREATE DATABASE` e `USE`:

```bash
sudo mysql < /var/www/coloafeto/database/schema.sql
```

Depois valide:

```bash
mysql -u colo_afeto_user -p -e "SHOW TABLES FROM colo_afeto;"
```

## 10. Ajustar permissoes

Defina o dono e as permissoes:

```bash
sudo chown -R www-data:www-data /var/www/html/coloeafetooficial
sudo find /var/www/html/coloeafetooficial -type d -exec chmod 755 {} \;
sudo find /var/www/html/coloeafetooficial -type f -exec chmod 644 {} \;
```

Garanta escrita nas pastas usadas para uploads, certificados e arquivos fiscais:

```bash
sudo chmod -R 775 /var/www/html/coloeafetooficial/uploads
sudo chmod -R 775 /var/www/html/coloeafetooficial/storage
```

As pastas `storage/`, `storage/certificados/`, `storage/nfe/` e `uploads/produtos/` possuem `.htaccess`. Por isso o VirtualHost deve permitir `AllowOverride All`.

## 11. Configurar o VirtualHost do Apache

Crie o arquivo:

```bash
sudo nano /etc/apache2/sites-available/coloafeto.conf
```

Conteudo sugerido:

```apache
<VirtualHost *:80>
    ServerName coloeafetooficial.com.br
    ServerAlias www.coloeafetooficial.com.br

    DocumentRoot /var/www/html/coloeafetooficial
    DirectoryIndex index.php index.html

    <Directory /var/www/html/coloeafetooficial>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/coloeafetooficial-error.log
    CustomLog ${APACHE_LOG_DIR}/coloeafetooficial-access.log combined
</VirtualHost>
```

Ative o site:

```bash
sudo a2dissite 000-default.conf
sudo a2ensite coloafeto.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Se o `configtest` retornar `Syntax OK`, o Apache esta pronto.

## 12. Liberar firewall

Se estiver usando UFW:

```bash
sudo ufw allow OpenSSH
sudo ufw allow "Apache Full"
sudo ufw enable
sudo ufw status
```

## 13. Configurar HTTPS com Certbot

Instale o Certbot:

```bash
sudo apt install -y certbot python3-certbot-apache
```

Emita o certificado:

```bash
sudo certbot --apache -d coloeafetooficial.com.br -d www.coloeafetooficial.com.br
```

Teste a renovacao automatica:

```bash
sudo certbot renew --dry-run
```

## 14. Primeiro acesso

Acesse:

```text
https://coloafeto.com.br/auth/login.php
```

Usuario inicial:

```text
E-mail: admin@coloafeto.local
Senha: admin123
```

Troque a senha imediatamente antes de publicar a loja.

## 15. Configuracoes iniciais do painel

No painel administrativo, revise:

- Dados fiscais em `admin/configuracoes/index.php`.
- Certificado A1/PFX e senha, se a NF-e for usada.
- Ambiente fiscal: homologacao ou producao.
- Configuracoes da Efi Bank para Pix/cartao, se pagamentos online forem usados.
- Webhook da Efi apontando para `https://coloafeto.com.br/webhooks/efi.php?token=SEU_TOKEN`.
- SMTP para envio de e-mails transacionais.
- Produtos, estoque, categorias, grupos e promocoes.
- Entregadores e vinculos de entrega.

## 16. Checklist de validacao

Execute no servidor:

```bash
sudo apache2ctl configtest
sudo systemctl status apache2
sudo systemctl status mysql
php -m | grep -E "pdo_mysql|curl|mbstring|xml|zip|gd|intl|soap"
```

Teste no navegador:

- Pagina inicial: `https://coloafeto.com.br/`
- Login: `https://coloafeto.com.br/auth/login.php`
- Painel admin: `https://coloafeto.com.br/admin/`
- Loja: `https://coloafeto.com.br/loja/`
- Webhook Efi: `https://coloafeto.com.br/webhooks/efi.php?token=SEU_TOKEN`

Confira logs em caso de erro:

```bash
sudo tail -f /var/log/apache2/coloafeto-error.log
sudo tail -f /var/log/apache2/error.log
```

## 17. Backups

Crie uma pasta para backups:

```bash
sudo mkdir -p /var/backups/coloafeto
sudo chown "$USER":"$USER" /var/backups/coloafeto
```

Backup manual do banco:

```bash
mysqldump -u colo_afeto_user -p colo_afeto > /var/backups/coloafeto/colo_afeto_$(date +%F).sql
```

Backup manual dos arquivos importantes:

```bash
tar -czf /var/backups/coloafeto/arquivos_$(date +%F).tar.gz \
  /var/www/coloafeto/uploads \
  /var/www/coloafeto/storage
```

Recomenda-se automatizar esses backups com `cron` e copiar os arquivos para um local externo.

## 18. Atualizacao do projeto

Antes de atualizar:

```bash
cd /var/www/coloafeto
mysqldump -u colo_afeto_user -p colo_afeto > /var/backups/coloafeto/pre_update_$(date +%F_%H-%M).sql
```

Se estiver usando Git:

```bash
git pull
composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data /var/www/coloafeto
sudo systemctl reload apache2
```

Depois acesse `auth/login.php` para que o sistema aplique criacao/ajustes automaticos de tabelas quando necessario.

## 19. Solucao de problemas comuns

Erro `Access denied for user`:

- Confira `DB_USER` e `DB_PASS` em `conexao.php`.
- Teste o login com `mysql -u colo_afeto_user -p colo_afeto`.

Erro `could not find driver`:

- Instale o driver MySQL do PHP: `sudo apt install php-mysql`.
- Reinicie o Apache: `sudo systemctl restart apache2`.

Tela branca ou erro 500:

- Confira `/var/log/apache2/coloafeto-error.log`.
- Verifique se o Composer foi executado.
- Verifique permissoes em `uploads/` e `storage/`.

Arquivos em `storage/` acessiveis publicamente:

- Confirme que o VirtualHost usa `AllowOverride All`.
- Confirme que os arquivos `.htaccess` foram enviados para a VPS.

Uploads falhando:

- Confira permissao de escrita em `uploads/`.
- Confira limites de upload no PHP em `/etc/php/*/apache2/php.ini`.
