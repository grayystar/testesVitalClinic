<?php

function render_doctor_patient_history(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    $patientId = (int) ($_GET['patient_id'] ?? 0);
    if (!$doctor) {
        abort_forbidden();
    }

    $patient = patient_detail_for_doctor((int) $doctor['id'], $patientId);
    if (!$patient) {
        abort_forbidden();
    }

    $records = medical_records_for_patient($patientId, (int) $doctor['id']);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Histórico</p>
            <h1><?= h($patient['name']) ?></h1>
            <p class="muted"><?= h(age_from_birth($patient['birth_date'])) ?> - <?= h($patient['phone'] ?: '-') ?></p>
        </div>
        <a class="button" href="<?= h(app_url(['page' => 'doctor_patients'])) ?>">Voltar</a>
    </section>
    <section class="panel">
        <?php render_medical_record_cards($records); ?>
    </section>
    <?php
}
function render_medical_record_cards(array $records): void
{
    if (!$records) {
        echo '<p class="muted">Nenhum registro clínico encontrado.</p>';
        return;
    }

    foreach ($records as $index => $record): ?>
        <article class="record-card">
            <div>
                <h3><?= h($record['diagnosis'] ? 'Consulta de rotina' : 'Registro clínico') ?></h3>
                <p class="muted"><?= h(format_date($record['slot_start'] ?? $record['created_at'])) ?> - <?= h($record['doctor_name'] ?? '') ?></p>
                <div class="record-grid">
                    <div>
                        <strong>Sinais vitais</strong>
                        <span>Peso: <?= h($record['weight'] ?: '-') ?></span>
                        <span>Altura: <?= h($record['height'] ?: '-') ?></span>
                        <span>Temperatura: <?= h($record['temperature'] ?: '-') ?></span>
                        <span>Pressão: <?= h($record['blood_pressure'] ?: '-') ?></span>
                    </div>
                    <div>
                        <strong>Sintomas</strong>
                        <p><?= h($record['symptoms'] ?: '-') ?></p>
                        <strong>Diagnóstico</strong>
                        <p><?= h($record['diagnosis'] ?: '-') ?></p>
                        <strong>Prescrição</strong>
                        <p><?= h($record['prescription'] ?: '-') ?></p>
                    </div>
                </div>
            </div>
            <strong>Registro <?= count($records) - $index ?></strong>
        </article>
    <?php endforeach;
}
