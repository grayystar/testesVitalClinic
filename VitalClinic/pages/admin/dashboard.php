<?php

function render_admin_dashboard(): void
{
    $metrics = dashboard_metrics();
    $today = current_date_value();
    ensure_slots_for_all($today, $today);
    $appointments = appointments_for_admin(['date' => $today]);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Administração</p>
            <h1>Painel da clínica</h1>
        </div>
        <a class="button primary" href="<?= h(app_url(['page' => 'admin_reports'])) ?>">Ver relatórios</a>
    </section>
    <section class="grid stats">
        <div class="stat"><span>Hoje</span><strong><?= (int) $metrics['today'] ?></strong></div>
        <div class="stat"><span>Pendentes</span><strong><?= (int) $metrics['pending'] ?></strong></div>
        <div class="stat"><span>Pacientes</span><strong><?= (int) $metrics['patients'] ?></strong></div>
        <div class="stat"><span>Médicos</span><strong><?= (int) $metrics['doctors'] ?></strong></div>
    </section>
    <section class="panel">
        <h2>Agenda de hoje</h2>
        <?php render_appointment_table($appointments, ['role' => 'admin', 'id' => 0], 'dashboard'); ?>
    </section>
    <?php
}
