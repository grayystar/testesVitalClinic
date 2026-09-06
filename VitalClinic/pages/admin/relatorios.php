<?php

function render_admin_reports(): void
{
    $from = $_GET['from'] ?? (new DateTime('first day of this month'))->format('Y-m-d');
    $to = $_GET['to'] ?? (new DateTime('last day of this month'))->format('Y-m-d');
    $report = report_data($from, $to);
    $summary = $report['summary'];
    $slots = $report['slots'];
    $total = (int) ($summary['total'] ?? 0);
    $noShows = (int) ($summary['no_shows'] ?? 0);
    $totalSlots = (int) ($slots['total_slots'] ?? 0);
    $bookedSlots = (int) ($slots['booked_slots'] ?? 0);
    $noShowRate = $total ? round(($noShows / $total) * 100, 1) : 0;
    $occupancy = $totalSlots ? round(($bookedSlots / $totalSlots) * 100, 1) : 0;
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Administração</p>
            <h1>Relatórios</h1>
        </div>
    </section>
    <section class="panel">
        <form method="get" class="filters">
            <input type="hidden" name="page" value="admin_reports">
            <label>De <input type="date" name="from" value="<?= h($from) ?>"></label>
            <label>Até <input type="date" name="to" value="<?= h($to) ?>"></label>
            <button class="button" type="submit">Atualizar</button>
        </form>
        <div class="grid stats">
            <div class="stat"><span>Consultas</span><strong><?= $total ?></strong></div>
            <div class="stat"><span>Faltas</span><strong><?= $noShowRate ?>%</strong></div>
            <div class="stat"><span>Ocupação</span><strong><?= $occupancy ?>%</strong></div>
            <div class="stat"><span>Bloqueios</span><strong><?= (int) ($slots['blocked_slots'] ?? 0) ?></strong></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Médico</th>
                        <th>Consultas</th>
                        <th>Realizadas</th>
                        <th>Faltas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['by_doctor'] as $row): ?>
                        <tr>
                            <td><?= h($row['doctor_name']) ?></td>
                            <td><?= (int) $row['total'] ?></td>
                            <td><?= (int) $row['completed'] ?></td>
                            <td><?= (int) $row['no_shows'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <table>
        </div>
    </section>
    <?php
}
