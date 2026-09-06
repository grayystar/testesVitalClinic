-- =====================================================================
-- Migration 002: adiciona a coluna derivada `is_admin` em `users`
-- Uso: rodar em bancos já existentes, sem apagar dados.
-- Para instalações novas, o vitalclinic_schema.sql já inclui a coluna.
-- =====================================================================

USE vitalclinic;

ALTER TABLE users
    ADD COLUMN is_admin TINYINT(1) UNSIGNED AS (role = 'admin') STORED
        COMMENT '1 = ADM, 0 = demais perfis (derivado de `role`, somente leitura)'
    AFTER role;

ALTER TABLE users
    ADD KEY idx_users_role (role);
