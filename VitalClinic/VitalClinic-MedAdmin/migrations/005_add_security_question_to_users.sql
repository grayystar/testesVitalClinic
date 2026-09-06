-- =====================================================================
-- Migration 005: adiciona a Pergunta de Segurança em `users`
-- Uso: rodar em bancos já existentes, sem apagar dados.
-- Para instalações novas, o vitalclinic_schema.sql já inclui as colunas.
--
-- A resposta é sempre armazenada como hash (password_hash), nunca em
-- texto puro — mesmo padrão já usado para `password_hash` e para os
-- códigos em `password_resets.code_hash`.
-- =====================================================================

USE vitalclinic;

ALTER TABLE users
    ADD COLUMN security_question    VARCHAR(255) NULL AFTER status,
    ADD COLUMN security_answer_hash VARCHAR(255) NULL AFTER security_question;
