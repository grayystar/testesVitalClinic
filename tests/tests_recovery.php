<?php

session_start();

/*
|--------------------------------------------------------------------------
| Mocks usados somente pelos testes
|--------------------------------------------------------------------------
*/

$fakeUser = null;
$fakeResetCode = '12345';
$resetCodeVerified = false;
$resetPasswordCalled = false;
$resetPasswordUserId = null;
$resetPasswordValue = null;
$emailSent = false;

function find_user_by_email(string $email): ?array
{
    global $fakeUser;
    return $fakeUser;
}

function create_password_reset_code(int $userId): string
{
    global $fakeResetCode;
    return $fakeResetCode;
}

function send_email(
    string $to,
    string $subject,
    string $body,
    int $userId
): void {
    global $emailSent;
    $emailSent = true;
}

function verify_password_reset_code(int $userId, string $code): bool
{
    global $fakeResetCode, $resetCodeVerified;

    if ($code === $fakeResetCode) {
        $resetCodeVerified = true;
        return true;
    }

    return false;
}

function reset_user_password(int $userId, string $password): void
{
    global $resetPasswordCalled;
    global $resetPasswordUserId;
    global $resetPasswordValue;

    $resetPasswordCalled = true;
    $resetPasswordUserId = $userId;
    $resetPasswordValue = $password;
}

function config(string $key)
{
    return match ($key) {
        'rules.password_reset_expires_minutes' => 15,
        'rules.password_reset_code_length' => 5,
        'app_name' => 'Vital Clinic',
        default => null,
    };
}

/*
|--------------------------------------------------------------------------
| Carrega o código REAL do Vital Clinic
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../VitalClinic/app/auth.php';


/*
|--------------------------------------------------------------------------
| Controle dos testes
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

$fakeUser = [
    'id' => 20,
    'name' => 'Dr. Teste',
    'email' => 'medico@teste.com',
    'password_hash' => password_hash('Senha123!', PASSWORD_DEFAULT),
    'role' => 'doctor',
    'status' => 'active'
];


/*
|--------------------------------------------------------------------------
| TESTE 1
| Solicitação de recuperação para usuário válido
|--------------------------------------------------------------------------
*/

$_SESSION = [];
$emailSent = false;

request_password_reset('medico@teste.com');

assert_test(
    isset($_SESSION['pwd_reset']),
    'Solicitação de recuperação cria o estado na sessão'
);

assert_test(
    $_SESSION['pwd_reset']['email'] === 'medico@teste.com',
    'E-mail informado é armazenado na sessão'
);

assert_test(
    $_SESSION['pwd_reset']['user_id'] === 20,
    'ID do usuário é associado à recuperação'
);

assert_test(
    $emailSent === true,
    'Código de recuperação é enviado para usuário válido'
);


/*
|--------------------------------------------------------------------------
| TESTE 2
| Código correto
|--------------------------------------------------------------------------
*/

$resetCodeVerified = false;

$result = confirm_password_reset_code('12345');

assert_test(
    $result === true,
    'Código correto é aceito'
);

assert_test(
    $_SESSION['pwd_reset']['verified'] === true,
    'Sessão marca o código como validado'
);


/*
|--------------------------------------------------------------------------
| TESTE 3
| Código incorreto
|--------------------------------------------------------------------------
*/

$_SESSION = [
    'pwd_reset' => [
        'email' => 'medico@teste.com',
        'user_id' => 20,
        'verified' => false,
        'requested_at' => time()
    ]
];

$result = confirm_password_reset_code('99999');

assert_test(
    $result === false,
    'Código incorreto é recusado'
);


/*
|--------------------------------------------------------------------------
| TESTE 4
| Verificar se pode cadastrar nova senha
|--------------------------------------------------------------------------
*/

$_SESSION = [
    'pwd_reset' => [
        'email' => 'medico@teste.com',
        'user_id' => 20,
        'verified' => true,
        'requested_at' => time()
    ]
];

assert_test(
    password_reset_can_set_new_password() === true,
    'Usuário validado pode definir uma nova senha'
);


/*
|--------------------------------------------------------------------------
| TESTE 5
| Senhas diferentes
|--------------------------------------------------------------------------
*/

$resetPasswordCalled = false;

try {
    complete_password_reset('NovaSenha123', 'SenhaDiferente');

    assert_test(
        false,
        'Recuperação deve recusar senhas diferentes'
    );
} catch (RuntimeException $e) {
    assert_test(
        $e->getMessage() === 'As senhas não coincidem.',
        'Sistema recusa quando as senhas não coincidem'
    );
}


/*
|--------------------------------------------------------------------------
| TESTE 6
| Alteração de senha concluída
|--------------------------------------------------------------------------
*/

$_SESSION = [
    'pwd_reset' => [
        'email' => 'medico@teste.com',
        'user_id' => 20,
        'verified' => true,
        'requested_at' => time()
    ]
];

$resetPasswordCalled = false;
$resetPasswordUserId = null;
$resetPasswordValue = null;

complete_password_reset(
    'NovaSenha123',
    'NovaSenha123'
);

assert_test(
    $resetPasswordCalled === true,
    'Sistema chama a alteração da senha'
);

assert_test(
    $resetPasswordUserId === 20,
    'Senha é alterada para o usuário correto'
);

assert_test(
    $resetPasswordValue === 'NovaSenha123',
    'Nova senha é enviada corretamente'
);

assert_test(
    !isset($_SESSION['pwd_reset']),
    'Sessão de recuperação é limpa após a alteração'
);


/*
|--------------------------------------------------------------------------
| TESTE 7
| Usuário inexistente
|--------------------------------------------------------------------------
*/

$fakeUser = null;
$_SESSION = [];
$emailSent = false;

request_password_reset('naoexiste@teste.com');

assert_test(
    $_SESSION['pwd_reset']['user_id'] === null,
    'Usuário inexistente não recebe ID de recuperação'
);

assert_test(
    $emailSent === false,
    'Nenhum e-mail é enviado para usuário inexistente'
);


/*
|--------------------------------------------------------------------------
| Resultado
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