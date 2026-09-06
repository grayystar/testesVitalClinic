-- =====================================================================
-- VITAL CLINIC (VCTCC) — ESTRUTURA DO BANCO (CREATE DATABASE / CREATE TABLE)
-- Só a estrutura: banco, tabelas, chaves estrangeiras e índices.
-- Os dados (INSERT) ficam em um arquivo separado: vitalclinic_dados.sql
-- (rode este arquivo primeiro, e só depois o de dados).
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
    -- Pergunta de segurança: único método de verificação de identidade
    -- em "Esqueci minha senha" (substituiu o antigo código por e-mail).
    -- A resposta NUNCA é armazenada em texto puro — apenas seu hash
    -- (password_hash), assim como a senha do usuário.
    security_question      VARCHAR(255) NULL,
    security_answer_hash   VARCHAR(255) NULL,
    -- 0 = ainda não viu o tutorial de primeiro acesso; 1 = já viu/pulou.
    tutorial_seen   TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    -- 0 = ainda não aceitou os Termos de Uso/Privacidade; 1 = já aceitou.
    -- Bloqueia o uso do painel até ser marcado como 1 (ver modal em
    -- render_terms_modal(), em index.php).
    terms_accepted  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
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

-- Observação: a antiga tabela `password_resets` (códigos de verificação
-- por e-mail) foi REMOVIDA deste schema. A recuperação de senha agora
-- usa exclusivamente a Pergunta de Segurança (colunas `security_question`
-- e `security_answer_hash` em `users`, acima).

-- Índices auxiliares para consultas frequentes do sistema
CREATE INDEX idx_doctors_clinic          ON doctors(clinic_id);
CREATE INDEX idx_doctors_specialty       ON doctors(specialty_id);
CREATE INDEX idx_slots_doctor_status     ON appointment_slots(doctor_id, status);
CREATE INDEX idx_appt_patient            ON appointments(patient_id);
CREATE INDEX idx_appt_doctor_status      ON appointments(doctor_id, status);
CREATE INDEX idx_payments_status         ON payments(status);
CREATE INDEX idx_notifications_user      ON notifications(user_id, status);
