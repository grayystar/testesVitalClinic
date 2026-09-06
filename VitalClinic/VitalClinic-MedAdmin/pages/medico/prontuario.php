<?php

function render_doctor_detail(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    $appointmentId = (int) ($_GET['appointment_id'] ?? 0);
    $appointment = appointment_by_id($appointmentId);

    if (!$doctor || !$appointment || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
        abort_forbidden();
    }

    $records = medical_records_for_patient((int) $appointment['patient_id'], (int) $doctor['id']);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Consulta</p>
            <h1>Detalhes da consulta</h1>
        </div>
        <?php if (in_array($appointment['status'], ['pending', 'confirmed'], true)): ?>
            <a class="button primary" href="<?= h(app_url(['page' => 'doctor_consultation', 'appointment_id' => $appointment['id']])) ?>">Iniciar consulta</a>
        <?php endif; ?>
    </section>

    <section class="grid two detail-grid">
        <div class="panel">
            <h2><?= h($appointment['patient_name']) ?></h2>
            <p class="muted"><?= h(age_from_birth($appointment['patient_birth_date'])) ?></p>
            <div class="split-line"></div>
            <p><strong>Telefone:</strong><br><?= h($appointment['patient_phone'] ?: '-') ?></p>
            <p><strong>E-mail:</strong><br><?= h($appointment['patient_email']) ?></p>
            <p><strong>Endereço:</strong><br><?= h($appointment['patient_address'] ?: '-') ?></p>
            <p><strong>CPF:</strong><br><?= h($appointment['patient_document'] ?: '-') ?></p>
        </div>

        <div class="panel">
            <h2>Informações da consulta</h2>
            <div class="grid two">
                <div class="mini-card">
                    <span>Horário</span>
                    <strong><?= h(format_time($appointment['slot_start'])) ?></strong>
                </div>
                <div class="mini-card">
                    <span>Tipo</span>
                    <strong><?= h($appointment['specialty_name']) ?></strong>
                </div>
            </div>
            <?php if (!empty($appointment['notes'])): ?>
                <div class="notice">
                    <strong>Observações prévias:</strong>
                    <p><?= h($appointment['notes']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2>Consultas recentes</h2>
        <?php render_medical_record_cards($records); ?>
    </section>
    <?php
}
function render_doctor_consultation(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    $appointmentId = (int) ($_GET['appointment_id'] ?? 0);
    $appointment = appointment_by_id($appointmentId);

    if (!$doctor || !$appointment || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
        abort_forbidden();
    }

    if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
        flash('error', 'Esta consulta não está aberta para atendimento.');
        redirect(['page' => 'doctor_detail', 'appointment_id' => $appointmentId]);
    }

    $record = medical_record_by_appointment($appointmentId) ?: [];
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Atendimento</p>
            <h1>Consulta em andamento</h1>
            <p class="muted"><?= h($appointment['patient_name']) ?> - <?= h(format_datetime($appointment['slot_start'])) ?></p>
        </div>
    </section>

    <form method="post" class="consultation-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_medical_record">
        <input type="hidden" name="appointment_id" value="<?= (int) $appointmentId ?>">
        <input type="hidden" name="page_after" value="doctor_consultation">
        <section class="grid two">
            <div class="grid">
                <div class="panel">
                    <h2>Check-up geral</h2>
                    <div class="grid two">
                        <label>Peso (kg) <input name="weight" value="<?= h($record['weight'] ?? '') ?>" placeholder="70.5"></label>
                        <label>Altura (cm) <input name="height" value="<?= h($record['height'] ?? '') ?>" placeholder="175"></label>
                        <label>Temperatura (°C) <input name="temperature" value="<?= h($record['temperature'] ?? '') ?>" placeholder="36.5"></label>
                        <label>Frequência cardíaca <input name="heart_rate" value="<?= h($record['heart_rate'] ?? '') ?>" placeholder="72 bpm"></label>
                    </div>
                    <label>Pressão arterial <input name="blood_pressure" value="<?= h($record['blood_pressure'] ?? '') ?>" placeholder="120/80 mmHg"></label>
                </div>
                <div class="panel">
                    <h2>Sintomas principais</h2>
                    <textarea name="symptoms" rows="8" placeholder="Descreva os principais sintomas relatados pelo paciente"><?= h($record['symptoms'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="grid">
                <div class="panel">
                    <h2>Diagnóstico</h2>
                    <textarea name="diagnosis" rows="5" placeholder="Diagnóstico preliminar ou confirmado"><?= h($record['diagnosis'] ?? '') ?></textarea>
                </div>
                <div class="panel">
                    <h2>Prescrição médica</h2>
                    <textarea name="prescription" rows="7" placeholder="Medicamentos prescritos, dosagem e instruções"><?= h($record['prescription'] ?? '') ?></textarea>
                    <label>Retorno/Acompanhamento
                        <input name="follow_up" value="<?= h($record['follow_up'] ?? '') ?>" placeholder="Retorno em 15 dias, solicitar exames laboratoriais.">
                    </label>
                </div>
            </div>
        </section>
        <div class="form-actions">
            <a class="button danger" href="<?= h(app_url(['page' => 'doctor_detail', 'appointment_id' => $appointmentId])) ?>">Cancelar</a>
            <button class="button primary" type="submit" data-confirm="Encerrar consulta e salvar no histórico do paciente?">Encerrar</button>
        </div>
    </form>
    <?php
}
