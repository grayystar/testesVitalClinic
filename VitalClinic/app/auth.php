<?php

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }

    $candidate = repository_find_user((int) $_SESSION['user_id']);
    if (!$candidate || $candidate['status'] !== 'active' || !in_array($candidate['role'], ['admin', 'doctor'], true)) {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }

    $user = repository_user_with_clinic($candidate);
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Entre para continuar.');
        redirect(['page' => 'login']);
    }
    return $user;
}

function require_role($roles): array
{
    $user = require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($user['role'], $roles, true)) {
        abort_forbidden();
    }
    return $user;
}

function login_user(string $email, string $password, string $expectedRole = ''): bool
{
    $candidate = find_user_by_email($email);
    if ($candidate && (!in_array($candidate['status'], ['active'], true) || !in_array($candidate['role'], ['admin', 'doctor'], true))) {
        $candidate = null;
    }

    // Seleção de perfil (Clínica/Médico) na tela de login: além da senha
    // correta, a role do usuário precisa bater com o botão escolhido.
    // Isso evita, por exemplo, que a conta de um médico entre pela opção
    // "Clínica" (ADM) só porque acertou usuário/senha.
    if ($candidate && $expectedRole !== '' && $candidate['role'] !== $expectedRole) {
        $candidate = null;
    }

    if (!$candidate || !password_verify($password, $candidate['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = (int) $candidate['id'];
    repository_update_user((int) $candidate['id'], ['last_login_at' => now_sql()]);
    return true;
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
}

function register_patient(array $data): int
{
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }
    if (strlen($data['password']) < 6) {
        throw new RuntimeException('A senha deve ter pelo menos 6 caracteres.');
    }
    if (email_in_use($data['email'])) {
        throw new RuntimeException('Este e-mail já está cadastrado.');
    }

    return repository_append('users', [
        'name' => $data['name'],
        'email' => strtolower($data['email']),
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'role' => 'patient',
        'phone' => $data['phone'] ?: null,
        'document' => $data['document'] ?: null,
        'birth_date' => $data['birth_date'] ?: null,
        'address' => $data['address'] ?: null,
        'clinic_id' => $data['clinic_id'] ?: null,
        'status' => 'active',
        'created_at' => now_sql(),
        'updated_at' => null,
        'last_login_at' => null,
    ]);
}

/* ---------------------------------------------------------------------
 * "Esqueci minha senha" — estado do fluxo fica em $_SESSION['pwd_reset'],
 * nunca em cookies/URL, e nunca contém o código em si (apenas o
 * user_id, uma vez que o código já foi validado no banco).
 * ------------------------------------------------------------------- */

function password_reset_pending(): ?array
{
    return $_SESSION['pwd_reset'] ?? null;
}

function clear_password_reset(): void
{
    unset($_SESSION['pwd_reset']);
}

/**
 * Passo 1: usuário informa o e-mail cadastrado.
 *
 * Por segurança, a resposta ao usuário é sempre a mesma mensagem
 * genérica de sucesso, exista ou não aquele e-mail no sistema — assim
 * ninguém consegue usar esta tela para descobrir quais e-mails estão
 * cadastrados (enumeração de contas). O código só é de fato gerado e
 * enviado quando o e-mail pertence a um usuário ativo (admin/médico).
 */
function request_password_reset(string $email): void
{
    $email = strtolower(trim($email));

    $_SESSION['pwd_reset'] = [
        'email' => $email,
        'user_id' => null,
        'verified' => false,
        'requested_at' => time(),
    ];

    $candidate = find_user_by_email($email);
    $eligible = $candidate
        && $candidate['status'] === 'active'
        && in_array($candidate['role'], ['admin', 'doctor'], true);

    if (!$eligible) {
        return;
    }

    $code = create_password_reset_code((int) $candidate['id']);
    $_SESSION['pwd_reset']['user_id'] = (int) $candidate['id'];

    $minutes = (int) (config('rules.password_reset_expires_minutes') ?: 15);
    send_email(
        $candidate['email'],
        'Código de recuperação de senha - ' . config('app_name'),
        sprintf(
            "Olá, %s!\n\nUse o código abaixo para redefinir sua senha no %s:\n\n%s\n\nEste código expira em %d minutos. Se você não solicitou esta alteração, ignore esta mensagem.",
            $candidate['name'],
            config('app_name'),
            $code,
            $minutes
        ),
        (int) $candidate['id']
    );
}

/**
 * Passo 2: usuário informa o código de 5 dígitos recebido por e-mail.
 */
function confirm_password_reset_code(string $code): bool
{
    $pending = password_reset_pending();
    if (!$pending || empty($pending['user_id'])) {
        // Sem solicitação válida em andamento (e-mail inexistente na
        // etapa anterior, sessão expirada, ou acesso direto à URL).
        return false;
    }

    $ok = verify_password_reset_code((int) $pending['user_id'], $code);
    if ($ok) {
        $_SESSION['pwd_reset']['verified'] = true;
        $_SESSION['pwd_reset']['verified_at'] = time();
    }

    return $ok;
}

function password_reset_can_set_new_password(): bool
{
    $pending = password_reset_pending();
    return $pending !== null && !empty($pending['user_id']) && !empty($pending['verified']);
}

/**
 * Passo 3: código validado, usuário cadastra a nova senha.
 */
function complete_password_reset(string $password, string $confirmPassword): void
{
    if (!password_reset_can_set_new_password()) {
        throw new RuntimeException('Código não validado. Reinicie o processo de recuperação de senha.');
    }
    if ($password !== $confirmPassword) {
        throw new RuntimeException('As senhas não coincidem.');
    }

    $pending = password_reset_pending();
    reset_user_password((int) $pending['user_id'], $password);
    clear_password_reset();
}
