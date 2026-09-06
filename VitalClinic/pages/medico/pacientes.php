<?php

function render_doctor_patients(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    if (!$doctor) {
        echo '<section class="panel"><p>Médico não encontrado.</p></section>';
        return;
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    $patients = doctor_patient_list((int) $doctor['id'], $search);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Médico</p>
            <h1>Meus pacientes</h1>
        </div>
    </section>
    <section class="panel">
        <form method="get" class="filters">
            <input type="hidden" name="page" value="doctor_patients">
            <label>Buscar paciente <input name="q" value="<?= h($search) ?>" placeholder="Buscar paciente por nome"></label>
            <button class="button" type="submit">Buscar</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Idade</th>
                        <th>Telefone</th>
                        <th>Última consulta</th>
                        <th>Próxima consulta</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?= h($patient['name']) ?></td>
                            <td><?= h(age_from_birth($patient['birth_date'])) ?></td>
                            <td><?= h($patient['phone'] ?: '-') ?></td>
                            <td><?= h(format_date($patient['last_appointment'])) ?></td>
                            <td><?= h(format_date($patient['next_appointment'])) ?></td>
                            <td><a class="button small" href="<?= h(app_url(['page' => 'doctor_patient_history', 'patient_id' => $patient['id']])) ?>">Ver histórico</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}
