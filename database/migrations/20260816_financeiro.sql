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
