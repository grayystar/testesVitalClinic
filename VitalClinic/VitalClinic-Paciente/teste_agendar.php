<?php
// teste_agendar.php — arquivo temporário só pra testar as funções novas

require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/repository.php';

echo "<h2>Médicos ativos:</h2>";
$medicos = active_doctors_with_details();
foreach ($medicos as $medico) {
    echo "id {$medico['doctor_id']}: {$medico['doctor_name']} — {$medico['specialty_name']} — {$medico['clinic_name']}<br>";
}

// troca esse número pelo id de algum médico que apareceu na lista acima
$medicoTesteId = 1;

echo "<h2>Horários livres do médico id {$medicoTesteId}:</h2>";
$horarios = available_slots_for_doctor($medicoTesteId);
if (empty($horarios)) {
    echo "Nenhum horário livre encontrado pra esse médico.";
}
foreach ($horarios as $horario) {
    echo "slot id {$horario['id']}: {$horario['slot_start']} até {$horario['slot_end']}<br>";
}