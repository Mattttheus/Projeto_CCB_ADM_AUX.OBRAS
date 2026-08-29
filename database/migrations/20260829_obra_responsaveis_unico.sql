ALTER TABLE obra_responsaveis
    ADD UNIQUE KEY uq_obra_responsavel (obra_id, usuario_id);