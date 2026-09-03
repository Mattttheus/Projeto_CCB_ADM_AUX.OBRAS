-- phpMyAdmin-compatible schema
-- Select the target database before importing this file.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables (safe for re-import)
DROP TABLE IF EXISTS `obra_responsaveis`;
DROP TABLE IF EXISTS `chamados`;
DROP TABLE IF EXISTS `documentos_obras`;
DROP TABLE IF EXISTS `atividades`;
DROP TABLE IF EXISTS `compras`;
DROP TABLE IF EXISTS `lancamentos_financeiros`;
DROP TABLE IF EXISTS `orcamentos_obras`;
DROP TABLE IF EXISTS `obras`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `fila_emails`;

-- Table: usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `empresa_id` INT DEFAULT 1,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) DEFAULT 'user',
  `tipo` VARCHAR(20) DEFAULT 'comum',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: obras
CREATE TABLE IF NOT EXISTS `obras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `empresa_id` INT DEFAULT 1,
  `nome` VARCHAR(150) NOT NULL,
  `status` ENUM('em_andamento', 'concluida', 'pausada') DEFAULT 'em_andamento',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: compras
CREATE TABLE IF NOT EXISTS `compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `tipo` ENUM('produto', 'material') NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `quantidade` INT NOT NULL DEFAULT 1,
  `valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `data_compra` DATE NOT NULL,
  INDEX (`obra_id`),
  CONSTRAINT `fk_compras_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: fila_emails
CREATE TABLE IF NOT EXISTS `fila_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `destinatario` VARCHAR(150) NOT NULL,
  `assunto` VARCHAR(255) NOT NULL,
  `mensagem_html` MEDIUMTEXT NOT NULL,
  `status` ENUM('pendente', 'enviado', 'erro') NOT NULL DEFAULT 'pendente',
  `tentativas` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `erro_mensagem` TEXT NULL,
  `data_envio` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_fila_status_tentativas` (`status`, `tentativas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificacoes_email` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo_evento` VARCHAR(80) NOT NULL,
  `entidade_tipo` VARCHAR(40) NOT NULL,
  `entidade_id` INT NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_notificacao_evento` (`tipo_evento`, `entidade_tipo`, `entidade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tables: financial management by project
CREATE TABLE IF NOT EXISTS `orcamentos_obras` (
  `obra_id` INT NOT NULL PRIMARY KEY,
  `valor_orcado` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_orcamentos_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lancamentos_financeiros` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `categoria` ENUM('material','operacional','servico','equipamento','produto') NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `quantidade` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `valor_unitario` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `data_lancamento` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_financeiro_obra_data` (`obra_id`, `data_lancamento`),
  CONSTRAINT `fk_lancamentos_financeiros_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atividades
CREATE TABLE IF NOT EXISTS `atividades` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(150) NOT NULL,
  `descricao` TEXT NULL,
  `data_atividade` DATE NULL,
  `tipo` ENUM('unico', 'recorrente') DEFAULT 'unico',
  `dia_semana` INT NULL,
  `obra_id` INT NULL,
  `data_limite` DATE NULL,
  `status` ENUM('pendente', 'em_andamento', 'concluida') DEFAULT 'pendente',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`obra_id`),
  CONSTRAINT `fk_atividades_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: documentos_obras
CREATE TABLE IF NOT EXISTS `documentos_obras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `nome_arquivo` VARCHAR(255) NOT NULL,
  `caminho_arquivo` VARCHAR(255) NOT NULL,
  `tipo_documento` VARCHAR(100) DEFAULT 'Geral',
  `data_upload` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`obra_id`),
  CONSTRAINT `fk_documentos_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chamados
CREATE TABLE IF NOT EXISTS `chamados` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NULL,
  `usuario_id` INT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `descricao` TEXT NOT NULL,
  `prioridade` ENUM('verde', 'amarelo', 'vermelho') NOT NULL DEFAULT 'verde',
  `status` ENUM('aberto', 'em_atendimento', 'resolvido', 'fechado') DEFAULT 'aberto',
  `data_abertura` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `data_fechamento` DATETIME NULL,
  `fechado_por` INT NULL,
  INDEX (`obra_id`),
  CONSTRAINT `fk_chamados_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: obra_responsaveis
CREATE TABLE IF NOT EXISTS `obra_responsaveis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  INDEX (`obra_id`),
  INDEX (`usuario_id`),
  UNIQUE KEY `uq_obra_responsavel` (`obra_id`, `usuario_id`),
  CONSTRAINT `fk_resp_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: usuário administrador padrão (senha: admin123)
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `role`, `tipo`)
VALUES ('Administrador', 'admin@obras.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36LrvWFm', 'admin', 'admin')
ON DUPLICATE KEY UPDATE `id` = `id`;

SET FOREIGN_KEY_CHECKS = 1;

-- Database connection settings
DB_HOST=sql213.infinityfree.com
DB_PORT=3306
DB_NAME=if0_41646147_root
DB_USER=if0_41646147
DB_PASSWORD=senha_exibida_no_painel

