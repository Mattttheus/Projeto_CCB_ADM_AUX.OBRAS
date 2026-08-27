CREATE TABLE IF NOT EXISTS `fila_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `destinatario` VARCHAR(150) NOT NULL,
  `assunto` VARCHAR(255) NOT NULL,
  `mensagem_html` MEDIUMTEXT NOT NULL,
  `status` ENUM('pendente','enviado','erro') NOT NULL DEFAULT 'pendente',
  `tentativas` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `erro_mensagem` TEXT NULL,
  `data_envio` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_fila_status_tentativas` (`status`, `tentativas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
