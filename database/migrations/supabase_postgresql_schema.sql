-- ==============================================================================
-- SCHEMA SUPABASE (POSTGRESQL) - PROJETO AUXILIAR DE OBRAS
-- ==============================================================================
-- Execute este script no SQL Editor do seu Dashboard no Supabase
-- (https://supabase.com/dashboard/project/<seu-projeto>/sql/new)
-- ==============================================================================

-- Remover tabelas se existirem (para importação limpa)
DROP TABLE IF EXISTS obra_responsaveis CASCADE;
DROP TABLE IF EXISTS chamados CASCADE;
DROP TABLE IF EXISTS documentos_obras CASCADE;
DROP TABLE IF EXISTS atividades CASCADE;
DROP TABLE IF EXISTS lancamentos_financeiros CASCADE;
DROP TABLE IF EXISTS orcamentos_obras CASCADE;
DROP TABLE IF EXISTS notificacoes_email CASCADE;
DROP TABLE IF EXISTS fila_emails CASCADE;
DROP TABLE IF EXISTS compras CASCADE;
DROP TABLE IF EXISTS obras CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

-- 1. Tabela: usuarios
CREATE TABLE usuarios (
  id SERIAL PRIMARY KEY,
  empresa_id INT DEFAULT 1,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  role VARCHAR(20) DEFAULT 'user',
  tipo VARCHAR(20) DEFAULT 'comum',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabela: obras
CREATE TABLE obras (
  id SERIAL PRIMARY KEY,
  empresa_id INT DEFAULT 1,
  nome VARCHAR(150) NOT NULL,
  status VARCHAR(20) DEFAULT 'em_andamento' CHECK (status IN ('em_andamento', 'concluida', 'pausada')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabela: compras
CREATE TABLE compras (
  id SERIAL PRIMARY KEY,
  obra_id INT NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('produto', 'material')),
  descricao VARCHAR(255) NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  data_compra DATE NOT NULL
);

-- 4. Tabela: fila_emails
CREATE TABLE fila_emails (
  id SERIAL PRIMARY KEY,
  destinatario VARCHAR(150) NOT NULL,
  assunto VARCHAR(255) NOT NULL,
  mensagem_html TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'enviado', 'erro')),
  tentativas SMALLINT NOT NULL DEFAULT 0,
  erro_mensagem TEXT NULL,
  data_envio TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_fila_status_tentativas ON fila_emails (status, tentativas);

-- 5. Tabela: notificacoes_email
CREATE TABLE notificacoes_email (
  id SERIAL PRIMARY KEY,
  tipo_evento VARCHAR(80) NOT NULL,
  entidade_tipo VARCHAR(40) NOT NULL,
  entidade_id INT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_notificacao_evento UNIQUE (tipo_evento, entidade_tipo, entidade_id)
);

-- 6. Tabela: orcamentos_obras
CREATE TABLE orcamentos_obras (
  obra_id INT NOT NULL PRIMARY KEY REFERENCES obras(id) ON DELETE CASCADE,
  valor_orcado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 7. Tabela: lancamentos_financeiros
CREATE TABLE lancamentos_financeiros (
  id SERIAL PRIMARY KEY,
  obra_id INT NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  categoria VARCHAR(30) NOT NULL CHECK (categoria IN ('material','operacional','servico','equipamento','produto')),
  descricao VARCHAR(255) NOT NULL,
  quantidade DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  valor_unitario DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  data_lancamento DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_financeiro_obra_data ON lancamentos_financeiros (obra_id, data_lancamento);

-- 8. Tabela: atividades
CREATE TABLE atividades (
  id SERIAL PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  descricao TEXT NULL,
  data_atividade DATE NULL,
  tipo VARCHAR(20) DEFAULT 'unico' CHECK (tipo IN ('unico', 'recorrente')),
  dia_semana INT NULL,
  obra_id INT NULL REFERENCES obras(id) ON DELETE SET NULL,
  data_limite DATE NULL,
  status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'em_andamento', 'concluida')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_atividades_obra ON atividades (obra_id);

-- 9. Tabela: documentos_obras
CREATE TABLE documentos_obras (
  id SERIAL PRIMARY KEY,
  obra_id INT NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  nome_arquivo VARCHAR(255) NOT NULL,
  caminho_arquivo VARCHAR(255) NOT NULL,
  tipo_documento VARCHAR(100) DEFAULT 'Geral',
  data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_documentos_obra ON documentos_obras (obra_id);

-- 10. Tabela: chamados
CREATE TABLE chamados (
  id SERIAL PRIMARY KEY,
  obra_id INT NULL REFERENCES obras(id) ON DELETE SET NULL,
  usuario_id INT NULL REFERENCES usuarios(id) ON DELETE SET NULL,
  titulo VARCHAR(150) NOT NULL,
  descricao TEXT NOT NULL,
  prioridade VARCHAR(20) NOT NULL DEFAULT 'verde' CHECK (prioridade IN ('verde', 'amarelo', 'vermelho')),
  status VARCHAR(20) DEFAULT 'aberto' CHECK (status IN ('aberto', 'em_atendimento', 'resolvido', 'fechado')),
  data_abertura TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  data_fechamento TIMESTAMP NULL,
  fechado_por INT NULL REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 11. Tabela: obra_responsaveis
CREATE TABLE obra_responsaveis (
  id SERIAL PRIMARY KEY,
  obra_id INT NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
  usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_obra_usuario UNIQUE (obra_id, usuario_id)
);

-- Usuário Admin padrão (Senha original: admin123, hash bcrypt)
INSERT INTO usuarios (nome, email, senha, role, tipo)
VALUES ('Administrador', 'admin@admin.com', '$2y$10$w8uM5H30a2F65d... (use seu hash)', 'admin', 'admin')
ON CONFLICT (email) DO NOTHING;
