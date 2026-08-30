# Publicacao na HostGator

## Requisitos

- PHP 8.2 ou superior com as extensoes `mysqli`, `pdo_mysql`, `fileinfo` e `openssl`.
- MySQL ou MariaDB.
- Um dominio apontado para a pasta `public_html`.

## Publicacao pelo cPanel

1. Em `MySQL Database Wizard`, crie o banco, o usuario e associe o usuario ao banco com privilegios `ALL PRIVILEGES`.
2. Abra `phpMyAdmin`, selecione o banco criado e importe `config/schema.sql`.
3. Em `File Manager`, abra `public_html`, envie `auxiliar-obras-hostgator.zip` e use `Extract` nessa mesma pasta.
4. Crie `public_html/.env` a partir de `.env.example` e preencha as credenciais do banco e do SMTP. O arquivo `.env` nao deve ser enviado ao GitHub.
5. Em `MultiPHP Manager`, selecione PHP 8.2+ para o dominio.
6. Confirme que `public_html/uploads` pode receber gravacao pelo usuario da conta. Use permissao 0755; nao use 0777.
7. Abra o dominio. A raiz redireciona para `page/login.php`.

## Variaveis de ambiente

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=prefixo_nome_do_banco
DB_USER=prefixo_usuario_do_banco
DB_PASSWORD=senha_do_banco

MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USER=login_smtp_brevo
MAIL_PASSWORD=chave_smtp_brevo
MAIL_FROM_EMAIL=remetente_verificado@seu-dominio.com
```

Em hospedagem compartilhada HostGator, o host MySQL costuma ser `localhost`. Use o valor exibido no cPanel caso ele seja diferente.

## Cron da fila de e-mails

Em `cPanel > Cron Jobs`, adicione a execucao a cada minuto. Substitua `USUARIO` pelo usuario cPanel e ajuste a versao do PHP se necessario:

```cron
* * * * * /usr/local/bin/php /home/USUARIO/public_html/cron/processar_fila.php >/dev/null 2>&1
```

Se `/usr/local/bin/php` nao existir, consulte `cPanel > MultiPHP Manager` ou o suporte HostGator para o caminho do PHP CLI.
