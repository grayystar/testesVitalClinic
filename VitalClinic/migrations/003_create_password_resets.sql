-- =====================================================================
-- Migration 003: cria a tabela `password_resets`
-- Uso: rodar em bancos já existentes, sem apagar dados.
-- Para instalações novas, o vitalclinic_schema.sql já inclui a tabela.
-- =====================================================================

USE vitalclinic;

CREATE TABLE IF NOT EXISTS password_resets (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED        NOT NULL,
    code_hash    VARCHAR(255)        NOT NULL,
    expires_at   DATETIME            NOT NULL,
    attempts     TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    consumed_at  DATETIME            NULL,
    created_at   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_password_resets_user ON password_resets(user_id, consumed_at, expires_at);
