<?php

function render_doctor_appointments(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    if (!$doctor) {
        echo '<section class="panel"><p>Médico não encontrado.</p></section>';
        return;
    }

    $date = $_GET['date'] ?? current_date_value();
    $appointments = appointments_for_admin(['date' => $date, 'doctor_id' => $doctor['id']]);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Médico</p>
            <h1>Consultas</h1>
        </div>
        <form method="get" class="date-switch">
            <input type="hidden" name="page" value="doctor_appointments">
            <input type="date" name="date" value="<?= h($date) ?>">
            <button class="button" type="submit">Ver</button>
        </form>
    </section>
    <section class="panel">
        <?php render_appointment_table($appointments, $user, 'doctor_appointments'); ?>
    </section>
    <?php
}
