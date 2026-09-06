<?php

/*
|--------------------------------------------------------------------------
| Mocks usados somente neste teste
|--------------------------------------------------------------------------
*/

function appointments_for_admin(array $filters): array
{
    return [
        [
            'id' => 1,
            'patient_name' => 'Maria Teste',
            'doctor_name' => 'Dr. João Teste',
            'date' => '2026-09-10',
            'time' => '09:00',
            'status' => 'confirmed'
        ]
    ];
}

function active_doctors(): array
{
    return [
        [
            'id' => 10,
            'name' => 'Dr. João Teste',
            'crm' => '12345',
            'specialty_name' => 'Cardiologia'
        ]
    ];
}

function patient_list(): array
{
    return [
        [
            'id' => 20,
            'name' => 'Maria Teste',
            'email' => 'maria@teste.com',
            'document' => '12345678900',
            'phone' => '(11) 99999-9999',
            'total_appointments' => 3,
            'no_shows' => 0
        ]
    ];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_test" value="token">';
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pendente',
        'confirmed' => 'Confirmada',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
        'no_show' => 'Não compareceu',
        default => $status
    };
}

function current_date_value(): string
{
    return '2026-09-05';
}

function format_time(string $time): string
{
    return date('H:i', strtotime($time));
}

function render_appointment_table(
    array $appointments,
    array $user,
    string $page
): void {
    echo '<div class="appointment-table">';

    foreach ($appointments as $appointment) {
        echo '<div class="appointment-row">';
        echo '<span>' . h($appointment['patient_name']) . '</span>';
        echo '<span>' . h($appointment['doctor_name']) . '</span>';
        echo '<span>' . h($appointment['date']) . '</span>';
        echo '<span>' . h($appointment['time']) . '</span>';
        echo '<span>' . h(status_label($appointment['status'])) . '</span>';
        echo '</div>';
    }

    echo '</div>';
}

function doctor_day_slots(int $doctorId, string $date): array
{
    return [
        [
            'slot_start' => '2026-09-10 08:00:00',
            'status' => 'available',
            'appointment_status' => '',
            'patient_name' => ''
        ],
        [
            'slot_start' => '2026-09-10 09:00:00',
            'status' => 'confirmed',
            'appointment_status' => 'confirmed',
            'patient_name' => 'Maria Teste'
        ],
        [
            'slot_start' => '2026-09-10 10:00:00',
            'status' => 'available',
            'appointment_status' => '',
            'patient_name' => ''
        ]
    ];
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string
    {
        return strtolower($value);
    }
}

/*
|--------------------------------------------------------------------------
| Carrega a tela REAL do Vital Clinic
|--------------------------------------------------------------------------
*/


require_once __DIR__ . '/../VitalClinic/pages/admin/consultas.php';


/*
|--------------------------------------------------------------------------
| Função de teste
|--------------------------------------------------------------------------
*/

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $description): void
{
    global $passed, $failed;

    if ($condition) {
        echo "[PASS] $description" . PHP_EOL;
        $passed++;
    } else {
        echo "[FAIL] $description" . PHP_EOL;
        $failed++;
    }
}


/*
|--------------------------------------------------------------------------
| TESTE 1 - tela inicial
|--------------------------------------------------------------------------
*/

$_GET = [];

ob_start();

render_admin_appointments();

$html = ob_get_clean();

assert_test(
    str_contains($html, 'Consultas'),
    'Tela exibe o título Consultas'
);

assert_test(
    str_contains($html, '+ Adicionar Nova Consulta'),
    'Tela possui botão para adicionar nova consulta'
);

assert_test(
    str_contains($html, 'Data'),
    'Tela possui filtro por data'
);

assert_test(
    str_contains($html, 'Status'),
    'Tela possui filtro por status'
);

assert_test(
    str_contains($html, 'Médico'),
    'Tela possui filtro por médico'
);

assert_test(
    str_contains($html, 'Filtrar'),
    'Tela possui botão de filtro'
);

assert_test(
    str_contains($html, 'Maria Teste'),
    'Consulta exibe o paciente'
);

assert_test(
    str_contains($html, 'Dr. João Teste'),
    'Consulta exibe o médico'
);

assert_test(
    str_contains($html, 'Confirmada'),
    'Consulta exibe o status da consulta'
);


/*
|--------------------------------------------------------------------------
| TESTE 2 - opções de status
|--------------------------------------------------------------------------
*/

assert_test(
    str_contains($html, 'Pendente'),
    'Filtro possui opção Pendente'
);

assert_test(
    str_contains($html, 'Confirmada'),
    'Filtro possui opção Confirmada'
);

assert_test(
    str_contains($html, 'Concluída'),
    'Filtro possui opção Concluída'
);

assert_test(
    str_contains($html, 'Cancelada'),
    'Filtro possui opção Cancelada'
);

assert_test(
    str_contains($html, 'Não compareceu'),
    'Filtro possui opção Não compareceu'
);


/*
|--------------------------------------------------------------------------
| TESTE 3 - modal de nova consulta
|--------------------------------------------------------------------------
*/

assert_test(
    str_contains($html, 'new-appointment-modal'),
    'Tela possui modal de nova consulta'
);

assert_test(
    str_contains($html, 'Adicionar nova consulta'),
    'Modal possui título de nova consulta'
);

assert_test(
    str_contains($html, 'admin_create_appointment'),
    'Formulário possui ação de criação de consulta'
);

assert_test(
    str_contains($html, 'patient_id'),
    'Formulário possui campo de paciente'
);

assert_test(
    str_contains($html, 'doctor_id'),
    'Formulário possui campo de médico'
);

assert_test(
    str_contains($html, 'new-appointment-date'),
    'Formulário possui campo de data'
);

assert_test(
    str_contains($html, 'presencial'),
    'Formulário possui opção presencial'
);

assert_test(
    str_contains($html, 'teleconsulta'),
    'Formulário possui opção de teleconsulta'
);

assert_test(
    str_contains($html, 'Agendar consulta'),
    'Formulário possui botão Agendar consulta'
);


/*
|--------------------------------------------------------------------------
| TESTE 4 - dados de autocomplete
|--------------------------------------------------------------------------
*/

assert_test(
    str_contains($html, 'Maria Teste'),
    'Autocomplete contém paciente disponível'
);

assert_test(
    str_contains($html, 'Dr. João Teste'),
    'Autocomplete contém médico disponível'
);

assert_test(
    str_contains($html, '12345678900'),
    'Autocomplete contém documento do paciente'
);

assert_test(
    str_contains($html, '12345'),
    'Autocomplete contém CRM do médico'
);


/*
|--------------------------------------------------------------------------
| TESTE 5 - agenda completa do médico
|--------------------------------------------------------------------------
*/

$_GET = [
    'date' => '2026-09-10',
    'doctor_id' => 10,
    'status' => ''
];

ob_start();

render_admin_appointments();

$html = ob_get_clean();

assert_test(
    str_contains($html, 'Agenda completa do médico'),
    'Com data e médico selecionados, a agenda completa é exibida'
);

assert_test(
    str_contains($html, '08:00'),
    'Agenda exibe horário disponível'
);

assert_test(
    str_contains($html, '09:00'),
    'Agenda exibe horário de consulta'
);

assert_test(
    str_contains($html, 'Maria Teste'),
    'Agenda exibe paciente com consulta marcada'
);


/*
|--------------------------------------------------------------------------
| RESULTADO
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "==============================" . PHP_EOL;
echo "TESTE DA TELA DE CONSULTAS" . PHP_EOL;
echo "==============================" . PHP_EOL;
echo "Testes aprovados: $passed" . PHP_EOL;
echo "Testes reprovados: $failed" . PHP_EOL;

if ($failed === 0) {
    echo PHP_EOL;
    echo "TODOS OS TESTES PASSARAM!" . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
echo "EXISTEM TESTES COM ERRO!" . PHP_EOL;
exit(1);