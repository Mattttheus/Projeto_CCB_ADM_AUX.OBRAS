# Publicacao remota com WampServer

## Quando usar

- Use WampServer para teste remoto, homologacao controlada ou acesso interno.
- Para producao publica na internet, prefira Linux/VPS ou hospedagem dedicada com HTTPS e monitoramento apropriados.

## Requisitos

- Windows com WampServer instalado.
- Apache com `mod_rewrite` habilitado.
- PHP 8.2 ou superior com as extensoes `mysqli`, `pdo_mysql`, `fileinfo` e `openssl`.
- MySQL ou MariaDB.
- Composer disponivel no servidor Windows.

## 1. Publicar o projeto no WampServer

1. Copie o projeto para uma pasta do WampServer, por exemplo `C:\wamp64\www\Projeto_CCB_ADM_AUX.OBRAS`.
2. No diretorio do projeto, execute `composer install --no-dev --optimize-autoloader`.
3. Confirme que a raiz publicada contem `index.php` e `.htaccess`. O `.htaccess` bloqueia acesso web direto a `config/`, `cron/`, `database/`, `vendor/`, `.env` e arquivos `.sql`.

## 2. Configurar o VirtualHost do Apache

1. Abra o arquivo de VirtualHosts do Apache no WampServer.
2. Use o modelo em [httpd-vhosts.example.conf](httpd-vhosts.example.conf), ajustando dominio e caminhos.
3. Aponte o `DocumentRoot` para a raiz do projeto.
4. Garanta `AllowOverride All` para que o `.htaccess` seja aplicado.
5. Reinicie o Apache.

Exemplo de diretorio publicado:

```text
C:\wamp64\www\Projeto_CCB_ADM_AUX.OBRAS
```

## 3. Banco de dados

1. Crie um banco MySQL/MariaDB no WampServer.
2. Importe `config/schema.sql` pelo phpMyAdmin ou via linha de comando.
3. Use um usuario com permissao de leitura e escrita nesse banco.

Exemplo via linha de comando:

```bat
mysql -u root -p auxiliar_obras < C:\wamp64\www\Projeto_CCB_ADM_AUX.OBRAS\config\schema.sql
```

## 4. Variaveis de ambiente

1. Copie `.env.example` para `.env` na raiz do projeto.
2. Preencha todas as variaveis abaixo com os valores do servidor:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=auxiliar_obras
DB_USER=usuario_do_banco
DB_PASSWORD=senha_do_banco

MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_FROM_EMAIL=remetente@seu-dominio.com
MAIL_USER=usuario_smtp
MAIL_PASSWORD=senha_ou_chave_smtp
```

O arquivo `.env` nao deve ser enviado ao GitHub.

## 5. Pasta de uploads

- Garanta permissao de gravacao para a conta do Windows usada pelo Apache.
- Mantenha a pasta `uploads/` dentro do projeto.
- Nao use permissao ampla desnecessaria; conceda escrita apenas ao usuario do servico.

## 6. Liberar acesso remoto

1. Defina um IP local fixo para o servidor Windows.
2. No Firewall do Windows, crie regras de entrada para TCP 80 e 443, ou libere o `httpd.exe`.
3. No roteador, redirecione as portas 80 e 443 para o IP local do servidor.
4. Configure dominio, subdominio ou DNS dinamico apontando para o IP publico.
5. Se o provedor bloquear porta 80, use uma alternativa como reverse proxy externo ou mapeamento suportado pela operadora.

## 7. HTTPS

- Antes de expor o sistema pela internet, habilite HTTPS.
- Voce pode configurar certificado diretamente no Apache do WampServer ou publicar atras de um reverse proxy com renovacao automatica.
- Se optar por certificado local no Apache, use tambem um `VirtualHost *:443` com `SSLEngine on`, certificado valido e redirecionamento de HTTP para HTTPS.

## 8. Agendar o processamento da fila

Crie uma tarefa agendada no Windows para executar `cron/processar_fila.php` a cada minuto.

Exemplo com `schtasks`:

```bat
schtasks /Create /SC MINUTE /MO 1 /TN "AuxiliarObrasFila" /TR "\"C:\wamp64\bin\php\php8.2.0\php.exe\" \"C:\wamp64\www\Projeto_CCB_ADM_AUX.OBRAS\cron\processar_fila.php\"" /F
```

Ajuste o caminho do `php.exe` para a versao instalada no WampServer.

## 9. Validacao externa

Valide a publicacao de fora da rede local:

1. Acesso a `page/login.php`.
2. Conexao com banco de dados sem erro 500.
3. Upload de arquivos em `uploads/`.
4. Envio de e-mails com as variaveis `MAIL_*`.
5. Funcionamento das paginas principais e das rotinas agendadas.

## 10. Recomendacoes de seguranca

- Nao exponha phpMyAdmin publicamente.
- Nao remova o `.htaccess` da raiz do projeto.
- Nao publique `.env`, dumps SQL, logs ou backups na pasta servida pelo Apache.
- Para uso publico continuo, migre para uma hospedagem Linux/VPS assim que possivel.
