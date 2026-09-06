-- =====================================================================
-- Migration 006: aplica no banco JÁ EXISTENTE as mesmas mudanças que
-- entraram no vitalclinic_schema.sql, sem apagar nenhum dado.
--
-- Pré-requisito: a migration 005 (colunas security_question /
-- security_answer_hash em `users`) já precisa ter sido aplicada.
--
-- O que este script faz:
--   1. Remove a tabela `password_resets`, que não é mais usada desde
--      que a recuperação de senha passou a ser 100% por Pergunta de
--      Segurança (não há mais envio de código por e-mail).
--   2. Cadastra uma Pergunta de Segurança de demonstração nos 3
--      usuários de exemplo (admin/médicos), só se eles ainda não
--      tiverem uma pergunta cadastrada — assim não sobrescreve nada
--      que você já tenha configurado manualmente pelo perfil.
--
-- Como rodar:
--   mysql -u root -p vitalclinic < migrations/006_remover_password_resets_e_seed_perguntas_seguranca.sql
-- =====================================================================

USE vitalclinic;

-- 1. Remove a tabela de códigos por e-mail (não é mais usada)
DROP TABLE IF EXISTS password_resets;

-- 2. Pergunta de segurança de demonstração (não sobrescreve quem já tem)
--    Respostas de demonstração: "Rex" / "Colégio Santa Rita" / "Recife"
--    (comparação ignora maiúsculas/minúsculas, espaços extras e acentos)
UPDATE users
   SET security_question = 'Qual o nome do seu primeiro animal de estimação?',
       security_answer_hash = '$2b$10$wfC1EC2FwTOwAF9J0EPfE.6p54n96xQ61.7Ide6bu5ey3zy.zEhuG'
 WHERE email = 'admin@clinica.local'
   AND security_question IS NULL;

UPDATE users
   SET security_question = 'Qual foi o nome da sua primeira escola?',
       security_answer_hash = '$2b$10$yJimUSf.awow2NElyFeWlukM.1F3B5cLmpgYN9PFQn4/Hus5JBwXi'
 WHERE email = 'medico@clinica.local'
   AND security_question IS NULL;

UPDATE users
   SET security_question = 'Qual é a sua cidade natal?',
       security_answer_hash = '$2b$10$vPU7lJHPJVWSF2LatKtonOIUx.ZXjFsreKnkevnq.0iJLuN1pWXKm'
 WHERE email = 'carlos.lima@clinicanorte.local'
   AND security_question IS NULL;
