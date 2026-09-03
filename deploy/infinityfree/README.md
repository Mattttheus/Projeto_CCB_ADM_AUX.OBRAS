# Publicacao gratuita na InfinityFree

## Quando usar

Use esta opcao quando quiser:

- deixar o codigo visivel no GitHub;
- publicar uma versao gratuita para acesso publico;
- evitar infraestrutura Docker paga.

## Limitacoes importantes

- O GitHub mostra o codigo, mas nao executa esta aplicacao PHP com MySQL.
- Na InfinityFree, recursos como cron podem nao estar disponiveis no plano gratuito.
- Se o cron nao estiver disponivel, a fila em `cron/processar_fila.php` dependera de execucao manual ou de um servico externo de agendamento.

## Requisitos

- Conta na InfinityFree
- Um dominio gratuito ou dominio proprio apontado para a hospedagem
- Banco MySQL criado no painel

## Publicacao pelo painel

1. No GitHub, deixe o repositorio publico para que o codigo possa ser visualizado.
2. Na InfinityFree, crie a conta de hospedagem e abra o painel de controle.
3. Em `MySQL Databases`, crie o banco e anote:
   - host do banco
   - nome do banco
   - usuario
   - senha
4. Abra o `phpMyAdmin` da hospedagem e importe `/home/runner/work/Projeto_CCB_ADM_AUX.OBRAS/Projeto_CCB_ADM_AUX.OBRAS/config/schema.sql`.
5. Envie o pacote `dist/auxiliar-obras-infinityfree.zip` para a pasta `htdocs` e extraia o conteudo nela.
6. Crie o arquivo `.env` na raiz publicada a partir de `/home/runner/work/Projeto_CCB_ADM_AUX.OBRAS/Projeto_CCB_ADM_AUX.OBRAS/.env.example`.
7. Preencha as variaveis `DB_*`, `MAIL_*` e `MAIL_FROM_EMAIL` com os valores da hospedagem.
8. Confirme que a pasta `uploads/` existe e pode receber gravacao pela aplicacao.
9. Abra o dominio publicado. A raiz redireciona para `page/login.php`.

## Exemplo de .env

```ini
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_NAME=if0_xxxxxxxx_auxiliar_obras
DB_USER=if0_xxxxxxxx
DB_PASSWORD=senha_do_banco

MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_FROM_EMAIL=remetente_verificado@seu-dominio.com
MAIL_USER=seu_usuario_smtp
MAIL_PASSWORD=sua_chave_smtp
```

## Publicando o link no GitHub

Depois do deploy:

1. adicione a URL em `About` no repositorio;
2. atualize o `README.md` com o link publico;
3. opcionalmente publique uma release com as instrucoes de uso.
