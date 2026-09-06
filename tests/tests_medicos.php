<?php

/*
|--------------------------------------------------------------------------
| Mocks necessários para renderizar a tela
|--------------------------------------------------------------------------
*/

function clinics(): array
{
    return [
        [
            'id' => 1,
            'name' => 'Clínica Vital'
        ]
    ];
}

function specialties(): array
{
    return [
        [
            'id' => 1,
            'name' => 'Cardiologia'
        ]
    ];
}

function active_doctors(): array
{
    return [
        [
            'id' => 10,
            'name' => 'Dr. João Teste',
            'specialty_name' => 'Cardiologia',
            'crm' => '12345',
            'clinic_id' => 1,
            'specialty_id' => 1,
            'phone' => '(11) 99999-9999',
            'appointment_duration' => 30,
            'bio' => 'Médico de teste',
            'email' => 'joao@teste.com'
        ]
    ];
}

function current_user(): array
{
    return [
        'id' => 1,
        'name' => 'Administrador',
        'email' => 'admin@teste.com',
        'role' => 'admin'
    ];
}

function staff_users(): array
{
    return [
        [
            'id' => 10,
            'name' => 'Dr. João Teste',
            'email' => 'joao@teste.com',
            'crm' => '12345',
            'specialty_name' => 'Cardiologia',
            'is_admin' => false
        ]
    ];
}

function doctor_schedules(int $doctorId): array
{
    return [
        [
            'id' => 1,
            'weekday' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00'
        ]
    ];
}

function doctor_blocks(int $doctorId): array
{
    return [
        [
            'block_date' => '2026-09-10',
            'start_time' => '13:00:00',
            'end_time' => '15:00:00'
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

function weekday_name(int $weekday): string
{
    $days = [
        'Domingo',
        'Segunda-feira',
        'Terça-feira',
        'Quarta-feira',
        'Quinta-feira',
        'Sexta-feira',
        'Sábado'
    ];

    return $days[$weekday] ?? '';
}

function format_date(string $date): string
{
    return date('d/m/Y', strtotime($date));
}

function current_date_value(): string
{
    return '2026-09-05';
}

/*
|--------------------------------------------------------------------------
| Carrega a tela REAL do Vital Clinic
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../VitalClinic/pages/admin/medicos.php';


/*
|--------------------------------------------------------------------------
| Executa a tela e captura o HTML
|--------------------------------------------------------------------------
*/

ob_start();

render_admin_doctors();

$html = ob_get_clean();


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
| TESTES DA TELA
|--------------------------------------------------------------------------
*/

assert_test(
    str_contains($html, 'Médicos e agendas'),
    'Tela exibe o título Médicos e agendas'
);

assert_test(
    str_contains($html, 'Novo médico'),
    'Tela exibe o formulário de novo médico'
);

assert_test(
    str_contains($html, 'Cadastrar médico'),
    'Tela possui botão para cadastrar médico'
);

assert_test(
    str_contains($html, 'Médicos ativos'),
    'Tela exibe a seção de médicos ativos'
);

assert_test(
    str_contains($html, 'Dr. João Teste'),
    'Tela exibe o médico cadastrado'
);

assert_test(
    str_contains($html, 'Cardiologia'),
    'Tela exibe a especialidade do médico'
);

assert_test(
    str_contains($html, '12345'),
    'Tela exibe o CRM do médico'
);

assert_test(
    str_contains($html, 'Atendimento semanal'),
    'Tela exibe a seção de atendimento semanal'
);

assert_test(
    str_contains($html, 'Adicionar'),
    'Tela possui opção para adicionar horário'
);

assert_test(
    str_contains($html, 'Bloqueios'),
    'Tela exibe a seção de bloqueios'
);

assert_test(
    str_contains($html, 'Bloquear'),
    'Tela possui opção para bloquear horário'
);

assert_test(
    str_contains($html, 'Remover médico'),
    'Tela possui opção para remover médico'
);

assert_test(
    str_contains($html, 'Gestão de acessos'),
    'Tela exibe a gestão de acessos'
);

assert_test(
    str_contains($html, 'admin_create_doctor'),
    'Formulário possui ação de criação de médico'
);

assert_test(
    str_contains($html, 'admin_update_doctor'),
    'Formulário possui ação de atualização de médico'
);

assert_test(
    str_contains($html, 'admin_add_schedule'),
    'Formulário possui ação de adicionar horário'
);

assert_test(
    str_contains($html, 'admin_add_block'),
    'Formulário possui ação de bloqueio'
);

assert_test(
    str_contains($html, 'admin_delete_doctor'),
    'Formulário possui ação de remoção de médico'
);

assert_test(
    str_contains($html, 'admin_update_user_role'),
    'Tela possui ação de alteração de perfil'
);


/*
|--------------------------------------------------------------------------
| RESULTADO
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "==============================" . PHP_EOL;
echo "TESTE DA TELA DE MÉDICOS" . PHP_EOL;
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