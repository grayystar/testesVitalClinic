<?php

/*
|--------------------------------------------------------------------------
| Mocks usados somente neste teste
|--------------------------------------------------------------------------
*/

function patient_list(): array
{
    return [
        [
            'id' => 30,
            'name' => 'Maria Teste',
            'email' => 'maria@teste.com',
            'total_appointments' => 5,
            'no_shows' => 1
        ]
    ];
}

function repository_find_user(int $patientId): ?array
{
    if ($patientId !== 30) {
        return null;
    }

    return [
        'id' => 30,
        'name' => 'Maria Teste',
        'email' => 'maria@teste.com',
        'phone' => '(11) 98888-8888',
        'document' => '12345678900',
        'birth_date' => '2000-05-10',
        'address' => 'Rua Teste, 100',
        'status' => 'active',
        'role' => 'patient'
    ];
}

function clinics(): array
{
    return [
        [
            'id' => 1,
            'name' => 'Clínica Vital'
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

function app_url(array $params = []): string
{
    return 'index.php?' . http_build_query($params);
}

/*
|--------------------------------------------------------------------------
| Mock do histórico de consultas
|--------------------------------------------------------------------------
*/

function appointments_for_user(array $patient, string $mode): array
{
    return [
        [
            'id' => 1,
            'date' => '2026-09-10',
            'time' => '09:00',
            'doctor_name' => 'Dr. João Teste',
            'status' => 'confirmed'
        ]
    ];
}

function render_appointment_table(
    array $appointments,
    array $user,
    string $page
): void {
    echo '<div class="appointment-table">';

    foreach ($appointments as $appointment) {
        echo '<div>';
        echo h($appointment['doctor_name']);
        echo '</div>';
    }

    echo '</div>';
}


/*
|--------------------------------------------------------------------------
| Carrega a tela REAL do Vital Clinic
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../VitalClinic/pages/admin/pacientes.php';


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
| TESTE 1 — lista de pacientes
|--------------------------------------------------------------------------
*/

$_GET = [];

ob_start();

render_admin_patients();

$html = ob_get_clean();

assert_test(
    str_contains($html, 'Pacientes'),
    'Tela exibe o título Pacientes'
);

assert_test(
    str_contains($html, 'Lista de pacientes'),
    'Tela exibe a lista de pacientes'
);

assert_test(
    str_contains($html, 'Maria Teste'),
    'Paciente aparece na lista'
);

assert_test(
    str_contains($html, 'maria@teste.com'),
    'E-mail do paciente aparece na lista'
);

assert_test(
    str_contains($html, '5 consultas'),
    'Quantidade de consultas do paciente é exibida'
);

assert_test(
    str_contains($html, '1 faltas'),
    'Quantidade de faltas do paciente é exibida'
);

assert_test(
    str_contains($html, 'Histórico'),
    'Tela possui botão de histórico'
);


/*
|--------------------------------------------------------------------------
| TESTE 2 — cadastro de paciente
|--------------------------------------------------------------------------
*/

$_GET = [];

ob_start();

render_admin_patients();

$html = ob_get_clean();

assert_test(
    str_contains($html, 'Cadastrar paciente'),
    'Tela possui formulário de cadastro de paciente'
);

assert_test(
    str_contains($html, 'name="email"'),
    'Cadastro possui campo de e-mail'
);

assert_test(
    str_contains($html, 'name="password"'),
    'Cadastro possui campo de senha'
);

assert_test(
    str_contains($html, 'name="phone"'),
    'Cadastro possui campo de telefone'
);

assert_test(
    str_contains($html, 'name="document"'),
    'Cadastro possui campo de documento'
);

assert_test(
    str_contains($html, 'name="birth_date"'),
    'Cadastro possui campo de nascimento'
);

assert_test(
    str_contains($html, 'name="address"'),
    'Cadastro possui campo de endereço'
);

assert_test(
    str_contains($html, 'admin_create_patient'),
    'Formulário possui ação de criação de paciente'
);


/*
|--------------------------------------------------------------------------
| TESTE 3 — paciente selecionado
|--------------------------------------------------------------------------
*/

$_GET = [
    'patient_id' => 30
];

ob_start();

render_admin_patients();

$html = ob_get_clean();

assert_test(
    str_contains($html, 'Paciente selecionado'),
    'Tela exibe a seção de paciente selecionado'
);

assert_test(
    str_contains($html, 'admin_update_patient'),
    'Formulário possui ação de atualização do paciente'
);

assert_test(
    str_contains($html, 'Salvar paciente'),
    'Tela possui botão para salvar paciente'
);

assert_test(
    str_contains($html, 'Maria Teste'),
    'Nome do paciente selecionado é exibido'
);

assert_test(
    str_contains($html, 'Histórico'),
    'Histórico do paciente selecionado é exibido'
);


/*
|--------------------------------------------------------------------------
| RESULTADO
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "==============================" . PHP_EOL;
echo "TESTE DA TELA DE PACIENTES" . PHP_EOL;
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