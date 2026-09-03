# 🏗️ Auxiliar Obras - CCB ADM (SaaS & Gestão de Obras)

Sistema web para gestão administrativa, financeira e operacional de obras da Congregação Cristã no Brasil (CCB), com suporte a rotinas automatizadas, controle financeiro (receitas e despesas), relatórios e upload de documentos.

---

## 📌 Sumário

1. [Visão Geral](#-visão-geral)
2. [Arquitetura & Diretrizes](#-arquitetura--diretrizes)
   - [Domain-Driven Design (DDD)](#domain-driven-design-ddd)
   - [Clean Architecture](#clean-architecture)
   - [Boas Práticas de Clean Code](#boas-práticas-de-clean-code)
3. [Estrutura do Repositório](#-estrutura-do-repositório)
4. [Módulos & Funcionalidades](#-módulos--funcionalidades)
5. [Configuração & Instalação](#-configuração--instalação)

---

## 💡 Visão Geral

O **Auxiliar Obras** foi projetado para descentralizar e organizar a gestão de reformas e construções. A plataforma permite monitorar fluxos de caixa, agendamentos, cronogramas operacionais (via *Cron Jobs*) e repositório centralizado de comprovantes/anexos.

---

## 📐 Arquitetura & Diretrizes

O projeto adota uma abordagem moderna orientada a objetos em PHP, mantendo baixo acoplamento e alta coesão entre a lógica de negócios e as camadas de visualização e banco de dados.

### Domain-Driven Design (DDD)

- **Linguagem Ubíqua:** Toda a estrutura de pastas, classes e tabelas reflete o domínio real de gestão de obras (Receitas, Despesas, Anotações, Cronogramas).

- **Entidades e Valores:** A lógica central das obras e transações é encapsulada na camada de aplicação (`app/`), isolando as regras operacionais da apresentação final.
- **Isolamento de Infraestrutura:** Persistência (`database/`), uploads (`uploads/`) e rotinas de fundo (`cron/`) funcionam como serviços de suporte ao domínio.

### Clean Architecture

A aplicação é dividida em camadas bem definidas:

1. **Presentation Layer (`page/`, `assets/`):** Views PHP, formulários, componentes visuais (CSS/JS) e dashboards.

### Boas Práticas de Clean Code

- **Operadores de Coalescência Nula (`??`):** Tratamento seguro contra *warnings* de variáveis indefinidas no PHP 7+.

- **Prepare Statements:** Uso estrito de `PDO` com consultas preparadas para evitar *SQL Injection*.
- **Encapsulamento e Sanitização:** Sanitização de saídas com `htmlspecialchars()` e prevenção de falsos erros no Linter/Intelephense.
- **Nomes Expressivos:** Métodos e variáveis descritos no contexto do domínio (`$receitas`, `$despesas`, `$anotacoes`).

---

## 📂 Estrutura do Repositório

text
Projeto_CCB_ADM_AUX.OBRAS/
├── .github/          # Workflows e pipelines CI/CD do GitHub Actions
├── .vs/              # Configurações do ambiente IDE (Visual Studio / VS Code)
├── app/              # Lógica de negócios, controllers e entidades
├── assets/           # Arquivos estáticos (CSS, JS, Imagens, Libs front-end)
├── config/           # Conexão PDO, sessões e parâmetros do sistema
├── cron/             # Tasks automatizadas e tarefas agendadas em segundo plano
├── database/         # Schemas SQL, tabelas e rotinas de banco de dados
├── page/             # Páginas da aplicação (Dashboard, Módulos de Entrada/Saída)
└── uploads/          # Diretório de armazenamento seguro de anexos e notas fiscais
⚙️ Módulos & Funcionalidades
🏠 Dashboard (page/dashboard.php)
Resumo consolidado de balanço (Total de Receitas vs. Despesas).

Exibição rápida de notas e anotações ativas do mês vigente.

💰 Gestão Financeira (app/ e page/)
Entradas (Receitas): Registro de repasses, doações e verbas direcionadas à obra.

Saídas (Despesas): Cadastro de pagamentos de materiais, serviços e mão de obra com suporte a upload de comprovantes em uploads/.

⏱️ Automações (cron/)
Execução programada de tarefas recorrentes, como consolidação de fechamentos mensais e verificação de pendências.

🔐 Segurança e Configuração (config/)
Tratamento centralizado de conexões PDO.

Controle de sessão seguro (session_start(), proteção de páginas restritas).

🚀 Configuração & Instalação
Pré-requisitos
PHP 7.4+ ou PHP 8.x

Servidor Web (Apache / Nginx)

Banco de dados MySQL / MariaDB
3. **Application / Core (`app/`):** Controladores de regras de negócio, validações, cálculos financeiros e manipulação de fluxos.
4. **Infrastructure & Config (`config/`, `database/`, `uploads/`):** Scripts PDO, gerenciamento de variáveis de ambiente, migrações SQL e armazenamento físico de arquivos.
5. **Background Tasks (`cron/`):** Automações e rotinas periódicas (ex: envios de e-mail, relatórios automáticos e atualizações de status).

## Publicacao para testes remotos

O GitHub armazena e versiona o codigo. Para disponibilizar a aplicacao a usuarios, publique este repositorio em uma hospedagem com PHP 8.2+, MySQL/MariaDB e Composer.

1. Clone o repositorio na hospedagem e execute `composer install --no-dev --optimize-autoloader`.
2. Crie um banco MySQL e importe `config/schema.sql`.
3. Copie `.env.example` para `.env` e preencha `DB_*`, `MAIL_*` e `MAIL_FROM_EMAIL` com valores da hospedagem. Nunca envie `.env` ao GitHub.
4. Configure o Apache/Nginx para servir a raiz do projeto e confirme que o PHP pode gravar em `uploads/`.
5. Agende `cron/processar_fila.php` a cada minuto com o PHP da hospedagem para processar a fila de e-mails.

Exemplo de agendamento Linux:

```cron
* * * * * /usr/bin/php /caminho/do/projeto/cron/processar_fila.php >/dev/null 2>&1
```

### Railway ou Render

Este repositorio inclui `Dockerfile`, portanto ambas as plataformas podem criar o servico web diretamente a partir da branch `main`. No Railway, adicione um servico MySQL e defina as variaveis do servico PHP usando referencias `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER` e `MYSQLPASSWORD`. No Render, crie um banco MySQL externo e informe os mesmos valores como `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`.

Em ambas as plataformas, configure tambem `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASSWORD` e `MAIL_FROM_EMAIL`. Depois do primeiro deploy, importe `config/schema.sql` no banco provisionado. Crie um segundo servico agendado para executar `php cron/processar_fila.php` a cada minuto.

### InfinityFree

Para uma opcao gratuita focada em PHP compartilhado, use o pacote `dist/auxiliar-obras-infinityfree.zip` e siga [deploy/infinityfree/README.md](deploy/infinityfree/README.md).

- O codigo pode ficar visivel no GitHub, mas a execucao publica precisa acontecer fora do GitHub.
- A hospedagem deve oferecer PHP, MySQL e escrita em `uploads/`.
- Se o plano gratuito nao oferecer cron, a fila de e-mails em `cron/processar_fila.php` ficara limitada ate que voce use execucao manual ou um agendador externo.

### HostGator

Para hospedagem compartilhada com cPanel, use o pacote `dist/auxiliar-obras-hostgator.zip` e siga [deploy/hostgator/README.md](deploy/hostgator/README.md). O pacote inclui as dependencias PHP e nao inclui `.env`, uploads ou logs.

## Divulgacao via GitHub

Depois de publicar a aplicacao:

1. deixe o repositorio publico para que o codigo possa ser visualizado;
2. adicione a URL da aplicacao no campo **About** do GitHub;
3. mantenha neste `README.md` um link para o ambiente publicado;
4. se quiser distribuir um snapshot pronto, publique uma release com o ZIP de deploy.
