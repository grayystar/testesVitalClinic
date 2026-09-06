<?php

session_start();

/*
|--------------------------------------------------------------------------
| Funções falsas (mocks) usadas apenas pelos testes
|--------------------------------------------------------------------------
*/

$fakeUser = null;
$updatedUserId = null;

function find_user_by_email(string $email): ?array
{
    global $fakeUser;
    return $fakeUser;
}

function repository_update_user(int $id, array $data): void
{
    global $updatedUserId;
    $updatedUserId = $id;
}

function now_sql(): string
{
    return '2026-09-05 16:00:00';
}

/*
|--------------------------------------------------------------------------
| Carrega o código real do Vital Clinic
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../VitalClinic/app/auth.php';


/*
|--------------------------------------------------------------------------
| Função simples para verificar os testes
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
| Usuário de teste
|--------------------------------------------------------------------------
*/

$password = 'Senha123!';

$validUser = [
    'id' => 10,
    'name' => 'Dr. Teste',
    'email' => 'medico@teste.com',
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'doctor',
    'status' => 'active'
];


/*
|--------------------------------------------------------------------------
| TESTE 1
| Login correto
|--------------------------------------------------------------------------
*/

$fakeUser = $validUser;
$updatedUserId = null;
$_SESSION = [];

$result = login_user(
    'medico@teste.com',
    'Senha123!',
    'doctor'
);

assert_test(
    $result === true,
    'Usuário consegue fazer login com dados corretos'
);

assert_test(
    isset($_SESSION['user_id']) && $_SESSION['user_id'] === 10,
    'ID do usuário é salvo na sessão'
);


/*
|--------------------------------------------------------------------------
| TESTE 2
| Senha incorreta
|--------------------------------------------------------------------------
*/

$fakeUser = $validUser;
$_SESSION = [];

$result = login_user(
    'medico@teste.com',
    'SenhaErrada',
    'doctor'
);

assert_test(
    $result === false,
    'Login é recusado quando a senha está incorreta'
);


/*
|--------------------------------------------------------------------------
| TESTE 3
| Perfil incorreto
|--------------------------------------------------------------------------
*/

$fakeUser = $validUser;
$_SESSION = [];

$result = login_user(
    'medico@teste.com',
    'Senha123!',
    'admin'
);

assert_test(
    $result === false,
    'Médico não pode entrar usando o perfil de administrador'
);


/*
|--------------------------------------------------------------------------
| TESTE 4
| Usuário inativo
|--------------------------------------------------------------------------
*/

$inactiveUser = $validUser;
$inactiveUser['status'] = 'inactive';

$fakeUser = $inactiveUser;
$_SESSION = [];

$result = login_user(
    'medico@teste.com',
    'Senha123!',
    'doctor'
);

assert_test(
    $result === false,
    'Usuário inativo não consegue fazer login'
);


/*
|--------------------------------------------------------------------------
| TESTE 5
| Usuário inexistente
|--------------------------------------------------------------------------
*/

$fakeUser = null;
$_SESSION = [];

$result = login_user(
    'naoexiste@teste.com',
    'Senha123!',
    'doctor'
);

assert_test(
    $result === false,
    'Usuário inexistente não consegue fazer login'
);


/*
|--------------------------------------------------------------------------
| TESTE 6
| Perfil inválido
|--------------------------------------------------------------------------
*/

$invalidRoleUser = $validUser;
$invalidRoleUser['role'] = 'patient';

$fakeUser = $invalidRoleUser;
$_SESSION = [];

$result = login_user(
    'medico@teste.com',
    'Senha123!',
    'patient'
);

assert_test(
    $result === false,
    'Usuário com perfil não permitido não consegue fazer login'
);


/*
|--------------------------------------------------------------------------
| Resultado final
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo "==============================" . PHP_EOL;
echo "RESULTADO DOS TESTES" . PHP_EOL;
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