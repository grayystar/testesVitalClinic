-- =====================================================================
-- VITAL CLINIC — CORREÇÃO DE DADOS E PADRONIZAÇÃO DE CREDENCIAIS
-- Rodar depois de vitalclinic_schema.sql e (se estiver usando) seed_data_extra.sql
--   mysql -u root -p vitalclinic < correcao_dados_e_padronizacao.sql
-- Todo o script roda dentro de uma única transação: se algo falhar no
-- meio do caminho, nada é aplicado.
-- =====================================================================

USE vitalclinic;
START TRANSACTION;

-- ---------------------------------------------------------------------
-- PARTE 1 — DIAGNÓSTICO (somente leitura, não altera nada)
-- Rode essas consultas ANTES da correção para ver o que será afetado.
-- ---------------------------------------------------------------------

-- 1.1 E-mails duplicados na tabela users (não deveria haver, já que a
--     coluna é UNIQUE — mas serve como checagem de sanidade caso o
--     índice tenha sido removido manualmente em algum ambiente de teste).
SELECT email, COUNT(*) AS ocorrencias
FROM users
GROUP BY email
HAVING COUNT(*) > 1;

-- 1.2 Usuários com papel (role) definido mas dados essenciais nulos.
--     Médicos/administradores sem nome ou e-mail, pacientes sem CPF.
SELECT id, name, email, role, document
FROM users
WHERE name IS NULL
   OR email IS NULL
   OR (role = 'patient' AND document IS NULL);

-- 1.3 Médicos (tabela doctors) cujo user_id não existe mais em users
--     (relacionamento quebrado — não deveria acontecer com FK ativa,
--     mas é o tipo de checagem que vale rodar após qualquer importação
--     manual de dados).
SELECT d.id, d.user_id
FROM doctors d
LEFT JOIN users u ON u.id = d.user_id
WHERE u.id IS NULL;

-- 1.4 Consultas (appointments) referenciando paciente ou médico
--     inexistente.
SELECT a.id, a.patient_id, a.doctor_id
FROM appointments a
LEFT JOIN users p ON p.id = a.patient_id
LEFT JOIN doctors d ON d.id = a.doctor_id
WHERE p.id IS NULL OR d.id IS NULL;

-- 1.5 Clínicas sem NENHUM administrador vinculado.
--     >>> Esta consulta apontou 3 clínicas sem responsável no banco
--     atual: Clínica Norte, Clínica Leste e Clínica Vitalità <<<
SELECT c.id, c.name
FROM clinics c
LEFT JOIN users u ON u.clinic_id = c.id AND u.role = 'admin'
WHERE u.id IS NULL;

-- 1.6 Médicos sem nenhuma grade de horário (doctor_schedules) cadastrada
--     — não impede o médico de logar, mas ele nunca teria horários
--     disponíveis para agendamento.
SELECT d.id, u.name
FROM doctors d
JOIN users u ON u.id = d.user_id
LEFT JOIN doctor_schedules s ON s.doctor_id = d.id
WHERE s.id IS NULL;


-- ---------------------------------------------------------------------
-- PARTE 2 — CORREÇÃO: clínicas sem administrador
-- Cria um usuário administrador padrão para cada clínica que ainda não
-- tem nenhum (idempotente: se rodar de novo, não duplica, pois a
-- condição NOT EXISTS já filtra as clínicas que faltam).
-- ---------------------------------------------------------------------
INSERT INTO users (clinic_id, name, email, password_hash, role, phone, status)
SELECT
    c.id,
    CONCAT('Administrador — ', c.name),
    CONCAT('admin.', LOWER(
        REPLACE(REPLACE(REPLACE(REPLACE(c.name, ' ', ''), 'á','a'), 'é','e'), 'í','i')
    ), '@vitalclinic.local'),
    '$2y$10$J.uHpwyXN/MZ/9R92TkaoOwDD1CjmijzVaWDxDxjZOmsR8ve3jsNK', -- Senha123!
    'admin',
    NULL,
    'active'
FROM clinics c
WHERE NOT EXISTS (
    SELECT 1 FROM users u WHERE u.clinic_id = c.id AND u.role = 'admin'
);

-- ---------------------------------------------------------------------
-- PARTE 3 — CORREÇÃO: remover duplicidade de e-mail, mantendo o
-- registro mais antigo (menor id) e apagando os demais.
-- Só executa algo se a consulta 1.1 tiver retornado linhas.
-- ---------------------------------------------------------------------
DELETE u1 FROM users u1
INNER JOIN users u2
    ON u1.email = u2.email
   AND u1.id > u2.id;

-- ---------------------------------------------------------------------
-- PARTE 4 — ATIVAÇÃO + PADRONIZAÇÃO DE SENHA DE TESTE
-- Ativa TODAS as contas (mesmo que estivessem 'inactive' por algum
-- motivo) e define a mesma senha de desenvolvimento pra todo mundo
-- (admin, doctor e patient), pra toda a equipe conseguir testar
-- qualquer conta sem depender do status atual dela.
-- Hash bcrypt de "Senha123!", compatível com password_verify() do PHP.
-- ---------------------------------------------------------------------
UPDATE users
SET status = 'active',
    password_hash = '$2y$10$J.uHpwyXN/MZ/9R92TkaoOwDD1CjmijzVaWDxDxjZOmsR8ve3jsNK';

COMMIT;

-- =====================================================================
-- Após rodar este script, TODAS as contas ativas (admin/médico/paciente)
-- usam a senha: Senha123!
-- =====================================================================
