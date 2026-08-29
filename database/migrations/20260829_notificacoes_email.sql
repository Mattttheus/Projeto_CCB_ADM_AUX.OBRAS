CREATE TABLE IF NOT EXISTS notificacoes_email (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_evento VARCHAR(80) NOT NULL,
    entidade_tipo VARCHAR(40) NOT NULL,
    entidade_id INT NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notificacao_evento (tipo_evento, entidade_tipo, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;