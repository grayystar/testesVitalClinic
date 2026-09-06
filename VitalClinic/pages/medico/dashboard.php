<?php

function render_doctor_dashboard(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    if (!$doctor) {
        echo '<section class="panel"><p>Médico não encontrado.</p></section>';
        return;
    }

    $date = $_GET['date'] ?? current_date_value();
    $slots = doctor_day_slots((int) $doctor['id'], $date);
    $appointments = appointments_for_admin(['date' => $date, 'doctor_id' => $doctor['id']]);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Médico</p>
            <h1>Agenda diária</h1>
            <p class="muted"><?= h($doctor['specialty_name']) ?> - <?= h($doctor['clinic_name']) ?></p>
        </div>
        <form method="get" class="date-switch">
            <input type="hidden" name="page" value="dashboard">
            <input type="date" name="date" value="<?= h($date) ?>">
            <button class="button" type="submit">Ver</button>
        </form>
    </section>

    <section class="grid two">
        <div class="panel">
            <h2>Consultas</h2>
            <?php render_appointment_table($appointments, $user, 'dashboard'); ?>
        </div>
        <div class="panel">
            <h2>Linha do dia</h2>
            <div class="timeline">
                <?php foreach ($slots as $slot): ?>
                    <div class="timeline-row <?= h($slot['status']) ?>">
                        <time><?= h(format_time($slot['slot_start'])) ?></time>
                        <span>
                            <?= h(status_label($slot['appointment_status'] ?: $slot['status'])) ?>
                            <?= $slot['patient_name'] ? ' - ' . h($slot['patient_name']) : '' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
