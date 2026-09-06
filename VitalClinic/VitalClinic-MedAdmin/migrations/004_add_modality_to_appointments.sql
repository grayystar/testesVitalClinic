-- =====================================================================
-- Migration 004: adiciona a coluna `modality` em `appointments`
-- Uso: rodar em bancos já existentes, sem apagar dados.
-- Para instalações novas, o vitalclinic_schema.sql já inclui a coluna.
-- =====================================================================

USE vitalclinic;

ALTER TABLE appointments
    ADD COLUMN modality ENUM('presencial','teleconsulta') NOT NULL DEFAULT 'presencial'
        AFTER status;
