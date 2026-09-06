-- =====================================================================
-- VITAL CLINIC (VCTCC) — MODELO RELACIONAL MySQL
-- Projetado a partir da análise do código-fonte do site de referência
-- (app/repository.php, data/demo-state.json, páginas admin/médico)
-- =====================================================================

DROP DATABASE IF EXISTS vitalclinic;
CREATE DATABASE vitalclinic
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vitalclinic;

-- ---------------------------------------------------------------------
-- 1. clinics — unidades/clínicas que usam o sistema (multi-clínica)
-- ---------------------------------------------------------------------
CREATE TABLE clinics (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)        NOT NULL,
    cnpj        VARCHAR(20)         NOT NULL UNIQUE,
    address     VARCHAR(255)        NULL,
    phone       VARCHAR(20)         NULL,
    whatsapp    VARCHAR(20)         NULL,
    email       VARCHAR(150)        NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NULL     ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. specialties — especialidades médicas (N:N com médicos via doctors)
-- ---------------------------------------------------------------------
CREATE TABLE specialties (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL UNIQUE,
    description VARCHAR(255)        NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. users — tabela única de contas (admin / doctor / patient)
--    O papel (role) diferencia o comportamento no sistema
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id       INT UNSIGNED        NULL,
    name            VARCHAR(150)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('admin','doctor','patient') NOT NULL,
    is_admin        TINYINT(1) UNSIGNED AS (role = 'admin') STORED, -- 1 = ADM, 0 = demais perfis (coluna derivada de `role`, somente leitura)
    phone           VARCHAR(20)         NULL,
    document        VARCHAR(20)         NULL,   -- CPF do paciente/usuário
    birth_date      DATE                NULL,
    address         VARCHAR(255)        NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NULL     ON UPDATE CURRENT_TIMESTAMP,
    last_login_at   TIMESTAMP           NULL,
    CONSTRAINT fk_users_clinic
        FOREIGN KEY (clinic_id) REFERENCES clinics(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    KEY idx_users_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. doctors — perfil profissional 1:1 com users (role = doctor)
-- ---------------------------------------------------------------------
CREATE TABLE doctors (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id               INT UNSIGNED        NOT NULL UNIQUE, -- 1:1 com users
    clinic_id             INT UNSIGNED        NOT NULL,
    specialty_id          INT UNSIGNED        NOT NULL,
    crm                   VARCHAR(30)         NOT NULL UNIQUE,
    bio                   TEXT                NULL,
    appointment_duration  SMALLINT UNSIGNED   NOT NULL DEFAULT 30, -- minutos
    active                TINYINT(1)          NOT NULL DEFAULT 1,
    created_at            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctors_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_doctors_clinic
        FOREIGN KEY (clinic_id) REFERENCES clinics(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_doctors_specialty
        FOREIGN KEY (specialty_id) REFERENCES specialties(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. doctor_schedules — grade semanal recorrente de atendimento (1:N)
-- ---------------------------------------------------------------------
CREATE TABLE doctor_schedules (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED        NOT NULL,
    weekday     TINYINT UNSIGNED    NOT NULL, -- 0=domingo ... 6=sábado
    start_time  TIME                NOT NULL,
    end_time    TIME                NOT NULL,
    active      TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT fk_schedules_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_schedule_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. schedule_blocks — bloqueios pontuais da agenda (férias, folga etc.)
-- ---------------------------------------------------------------------
CREATE TABLE schedule_blocks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED        NOT NULL,
    block_date  DATE                NOT NULL,
    start_time  TIME                NOT NULL,
    end_time    TIME                NOT NULL,
    reason      VARCHAR(255)        NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blocks_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_block_period CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. appointment_slots — grade de horários gerados (disponível/bloqueado/reservado)
-- ---------------------------------------------------------------------
CREATE TABLE appointment_slots (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id     INT UNSIGNED        NOT NULL,
    slot_start    DATETIME            NOT NULL,
    slot_end      DATETIME            NOT NULL,
    status        ENUM('available','booked','blocked') NOT NULL DEFAULT 'available',
    block_reason  VARCHAR(255)        NULL,
    CONSTRAINT fk_slots_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_slot_doctor_start UNIQUE (doctor_id, slot_start),
    CONSTRAINT chk_slot_period CHECK (slot_end > slot_start)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. appointments — consultas agendadas (núcleo do sistema)
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_id        INT UNSIGNED        NOT NULL UNIQUE, -- 1:1 com o slot ocupado
    patient_id     INT UNSIGNED        NOT NULL,
    doctor_id      INT UNSIGNED        NOT NULL,
    clinic_id      INT UNSIGNED        NOT NULL,
    specialty_id   INT UNSIGNED        NOT NULL,
    status         ENUM('pending','confirmed','completed','cancelled','no_show')
                                       NOT NULL DEFAULT 'pending',
    modality       ENUM('presencial','teleconsulta') NOT NULL DEFAULT 'presencial',
    notes          TEXT                NULL,
    cancel_reason  VARCHAR(255)        NULL,
    confirmed_at   TIMESTAMP           NULL,
    cancelled_at   TIMESTAMP           NULL,
    completed_at   TIMESTAMP           NULL,
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP           NULL     ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appt_slot
        FOREIGN KEY (slot_id) REFERENCES appointment_slots(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_appt_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_appt_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_appt_clinic
        FOREIGN KEY (clinic_id) REFERENCES clinics(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_appt_specialty
        FOREIGN KEY (specialty_id) REFERENCES specialties(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. medical_records — prontuário eletrônico, 1:1 com appointments
-- ---------------------------------------------------------------------
CREATE TABLE medical_records (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT UNSIGNED        NOT NULL UNIQUE,
    patient_id      INT UNSIGNED        NOT NULL,
    doctor_id       INT UNSIGNED        NOT NULL,
    created_by      INT UNSIGNED        NOT NULL, -- usuário (médico) que registrou
    weight          DECIMAL(5,2)        NULL, -- kg
    height          DECIMAL(5,2)        NULL, -- cm
    temperature     DECIMAL(4,1)        NULL, -- °C
    heart_rate      SMALLINT UNSIGNED   NULL, -- bpm
    blood_pressure  VARCHAR(15)         NULL, -- ex: 120/80 mmHg
    symptoms        TEXT                NULL,
    diagnosis       TEXT                NULL,
    prescription    TEXT                NULL,
    follow_up       VARCHAR(255)        NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NULL     ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_record_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_record_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_record_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_record_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. payments — cobrança da consulta, 1:1 com appointments
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT UNSIGNED        NOT NULL UNIQUE,
    patient_id      INT UNSIGNED        NOT NULL,
    clinic_id       INT UNSIGNED        NOT NULL,
    amount          DECIMAL(10,2)       NOT NULL,
    method          ENUM('pix','card','cash','health_plan') NULL,
    status          ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_at         TIMESTAMP           NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NULL     ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payment_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payment_clinic
        FOREIGN KEY (clinic_id) REFERENCES clinics(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. notifications — avisos enviados a um usuário sobre uma consulta
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    appointment_id  INT UNSIGNED        NULL,
    type            ENUM('in_app','email','sms') NOT NULL DEFAULT 'in_app',
    title           VARCHAR(150)        NOT NULL,
    message         VARCHAR(500)        NOT NULL,
    status          ENUM('scheduled','sent','failed','read') NOT NULL DEFAULT 'scheduled',
    send_at         TIMESTAMP           NULL,
    sent_at         TIMESTAMP           NULL,
    read_at         TIMESTAMP           NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notif_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. password_resets — códigos de verificação para "Esqueci minha senha"
--     Um registro por solicitação; o código nunca é gravado em texto
--     puro (apenas o hash), e cada código tem validade curta.
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED        NOT NULL,
    code_hash    VARCHAR(255)        NOT NULL, -- hash do código de 5 dígitos (nunca texto puro)
    expires_at   DATETIME            NOT NULL,
    attempts     TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    consumed_at  DATETIME            NULL,
    created_at   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Índices auxiliares para consultas frequentes do sistema
CREATE INDEX idx_doctors_clinic          ON doctors(clinic_id);
CREATE INDEX idx_doctors_specialty       ON doctors(specialty_id);
CREATE INDEX idx_slots_doctor_status     ON appointment_slots(doctor_id, status);
CREATE INDEX idx_appt_patient            ON appointments(patient_id);
CREATE INDEX idx_appt_doctor_status      ON appointments(doctor_id, status);
CREATE INDEX idx_payments_status         ON payments(status);
CREATE INDEX idx_notifications_user      ON notifications(user_id, status);
CREATE INDEX idx_password_resets_user    ON password_resets(user_id, consumed_at, expires_at);

-- =====================================================================
-- DADOS DE EXEMPLO (INSERT INTO)
-- =====================================================================

-- 1. clinics
INSERT INTO clinics (name, cnpj, address, phone, whatsapp, email) VALUES
('Clínica Central', '00.000.000/0001-00', 'Rua das Flores, 120', '(11) 4000-1000', '551140001000', 'contato@clinicacentral.local'),
('Clínica Norte', '00.000.000/0002-00', 'Avenida Norte, 850', '(11) 4000-2000', '551140002000', 'contato@clinicanorte.local'),
('Clínica Sul', '00.000.000/0003-00', 'Rua do Sul, 45', '(11) 4000-3000', '551140003000', 'contato@clinicasul.local'),
('Clínica Leste', '00.000.000/0004-00', 'Avenida Leste, 900', '(11) 4000-4000', '551140004000', 'contato@clinicaleste.local');

-- 2. specialties
INSERT INTO specialties (name, description) VALUES
('Clínico geral', 'Atendimento médico inicial e acompanhamento.'),
('Cardiologia', 'Diagnóstico e tratamento de doenças do coração.'),
('Dermatologia', 'Cuidados com a pele, cabelo e unhas.'),
('Pediatria', 'Atendimento médico voltado para crianças e adolescentes.'),
('Ortopedia', 'Diagnóstico e tratamento do sistema musculoesquelético.');

-- 3. users (senha de demonstração para todos: "password", hash bcrypt)
INSERT INTO users (clinic_id, name, email, password_hash, role, phone, document, birth_date, address, status) VALUES
(1, 'Administrador da Clínica', 'admin@clinica.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '(11) 90000-0001', NULL, NULL, NULL, 'active'),
(1, 'Dra. Ana Souza', 'medico@clinica.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '(11) 90000-0002', NULL, NULL, NULL, 'active'),
(2, 'Dr. Carlos Lima', 'carlos.lima@clinicanorte.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '(11) 90000-0004', NULL, NULL, NULL, 'active'),
(1, 'Paciente de Demonstração', 'paciente.demo@clinica.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '(11) 90000-0003', '111.111.111-11', '1995-05-10', 'Endereço de demonstração', 'active'),
(1, 'João Pereira', 'joao.pereira@email.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '(11) 90000-0005', '222.222.222-22', '1988-03-22', 'Rua A, 10', 'active');

-- 4. doctors (user_id 2 e 3 são médicos)
INSERT INTO doctors (user_id, clinic_id, specialty_id, crm, bio, appointment_duration, active) VALUES
(2, 1, 1, 'CRM-SP 123456', 'Atendimento clínico com foco em acompanhamento preventivo.', 30, 1),
(3, 2, 2, 'CRM-SP 654321', 'Especialista em cardiologia clínica e preventiva.', 40, 1);

-- 5. doctor_schedules
INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, active) VALUES
(1, 1, '08:00:00', '12:00:00', 1),
(1, 3, '08:00:00', '12:00:00', 1),
(2, 2, '13:00:00', '18:00:00', 1),
(2, 4, '13:00:00', '18:00:00', 1);

-- 6. schedule_blocks
INSERT INTO schedule_blocks (doctor_id, block_date, start_time, end_time, reason) VALUES
(1, '2026-09-07', '08:00:00', '12:00:00', 'Feriado municipal'),
(2, '2026-09-14', '13:00:00', '18:00:00', 'Congresso médico');

-- 7. appointment_slots
INSERT INTO appointment_slots (doctor_id, slot_start, slot_end, status, block_reason) VALUES
(1, '2026-08-17 09:00:00', '2026-08-17 09:30:00', 'booked', NULL),
(1, '2026-08-17 09:30:00', '2026-08-17 10:00:00', 'available', NULL),
(1, '2026-08-17 10:00:00', '2026-08-17 10:30:00', 'blocked', 'Reunião interna'),
(2, '2026-08-18 14:00:00', '2026-08-18 14:40:00', 'booked', NULL),
(2, '2026-08-18 14:40:00', '2026-08-18 15:20:00', 'available', NULL);

-- 8. appointments
INSERT INTO appointments (slot_id, patient_id, doctor_id, clinic_id, specialty_id, status, notes, confirmed_at) VALUES
(1, 4, 1, 1, 1, 'confirmed', 'Consulta de demonstração.', '2026-08-14 22:57:21'),
(4, 5, 2, 2, 2, 'confirmed', 'Avaliação cardiológica de rotina.', '2026-08-15 10:00:00'),
(2, 5, 1, 1, 1, 'pending', 'Retorno de acompanhamento.', NULL);

-- 9. medical_records
INSERT INTO medical_records (appointment_id, patient_id, doctor_id, created_by, weight, height, temperature, heart_rate, blood_pressure, symptoms, diagnosis, prescription, follow_up) VALUES
(1, 4, 1, 2, 70.50, 175.00, 36.5, 72, '120/80 mmHg', 'Dor de cabeça leve e cansaço.', 'Cefaleia tensional.', 'Analgésico conforme necessidade.', 'Retorno em 15 dias.'),
(2, 5, 2, 3, 82.00, 178.00, 36.7, 68, '130/85 mmHg', 'Falta de ar leve ao esforço.', 'Pré-hipertensão.', 'Redução de sódio na dieta.', 'Solicitar exames laboratoriais.');

-- 10. payments
INSERT INTO payments (appointment_id, patient_id, clinic_id, amount, method, status, paid_at) VALUES
(1, 4, 1, 250.00, NULL, 'pending', NULL),
(2, 5, 2, 300.00, 'pix', 'paid', '2026-08-15 10:05:00'),
(3, 5, 1, 250.00, NULL, 'pending', NULL);

-- 11. notifications
INSERT INTO notifications (user_id, appointment_id, type, title, message, status, send_at, sent_at) VALUES
(2, 1, 'in_app', 'Agenda de demonstração', 'Esta consulta é um exemplo para apresentar o sistema.', 'sent', '2026-08-14 22:57:21', '2026-08-14 22:57:21'),
(4, 1, 'email', 'Consulta confirmada', 'Sua consulta foi confirmada para 17/08/2026 às 09h.', 'sent', '2026-08-14 23:00:00', '2026-08-14 23:00:00'),
(5, 2, 'sms', 'Consulta confirmada', 'Sua consulta com Dr. Carlos Lima foi confirmada.', 'sent', '2026-08-15 09:00:00', '2026-08-15 09:00:00'),
(5, 3, 'in_app', 'Consulta pendente', 'Sua consulta de retorno aguarda confirmação.', 'scheduled', '2026-08-16 08:00:00', NULL);
