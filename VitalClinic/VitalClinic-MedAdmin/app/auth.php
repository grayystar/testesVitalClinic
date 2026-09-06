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

/**
 * Tenta autenticar o usuário e devolve o MOTIVO da falha (ou null se
 * deu certo). Isso existe porque, neste painel, quem loga é sempre uma
 * conta provisionada pela própria clínica (admin/médico) — diferente
 * do "esqueci minha senha" (que é intencionalmente genérico pra não
 * permitir descobrir quais e-mails existem), aqui não há motivo pra
 * esconder qual foi o problema: ajuda a clínica a se auto-corrigir sem
 * precisar abrir o banco de dados.
 */
function attempt_login(string $email, string $password, string $expectedRole = ''): ?string
{
    $email = strtolower(trim($email));
    $candidate = find_user_by_email($email);

    if (!$candidate || !in_array($candidate['role'], ['admin', 'doctor'], true)) {
        return 'not_found';
    }
    if ($candidate['status'] !== 'active') {
        return 'inactive';
    }
    if ($expectedRole !== '' && $candidate['role'] !== $expectedRole) {
        return 'wrong_role';
    }
    if (!password_verify($password, $candidate['password_hash'])) {
        return 'wrong_password';
    }

    $_SESSION['user_id'] = (int) $candidate['id'];
    repository_update_user((int) $candidate['id'], ['last_login_at' => now_sql()]);
    return null;
}

function login_user(string $email, string $password, string $expectedRole = ''): bool
{
    return attempt_login($email, $password, $expectedRole) === null;
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
 * nunca em cookies/URL. A verificação de identidade é feita 100% pela
 * pergunta de segurança cadastrada no perfil do usuário (não há mais
 * envio de código por e-mail neste fluxo).
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
 * genérica, exista ou não aquele e-mail no sistema — assim ninguém
 * consegue usar esta tela para descobrir quais e-mails estão
 * cadastrados (enumeração de contas). A pergunta de segurança só é
 * de fato carregada na sessão quando o e-mail pertence a um usuário
 * ativo (admin/médico) que já cadastrou uma.
 */
function request_password_reset(string $email): void
{
    $email = strtolower(trim($email));

    $_SESSION['pwd_reset'] = [
        'email' => $email,
        'user_id' => null,
        'verified' => false,
        'security_question' => null,
        'security_attempts' => 0,
        'requested_at' => time(),
    ];

    $candidate = find_user_by_email($email);
    $eligible = $candidate
        && $candidate['status'] === 'active'
        && in_array($candidate['role'], ['admin', 'doctor'], true);

    if (!$eligible) {
        return;
    }

    $question = get_user_security_question((int) $candidate['id']);
    if ($question === null) {
        // Conta existe, mas não tem pergunta de segurança cadastrada —
        // não há como confirmar a identidade por este fluxo. Não
        // guardamos o user_id na sessão, então a etapa seguinte mostra a
        // mensagem genérica de "não foi possível continuar".
        return;
    }

    $_SESSION['pwd_reset']['user_id'] = (int) $candidate['id'];
    $_SESSION['pwd_reset']['security_question'] = $question;
}

function password_reset_can_set_new_password(): bool
{
    $pending = password_reset_pending();
    return $pending !== null && !empty($pending['user_id']) && !empty($pending['verified']);
}

/**
 * Indica se a conta em recuperação tem uma pergunta de segurança
 * cadastrada e disponível para responder nesta etapa.
 */
function password_reset_has_security_question(): bool
{
    $pending = password_reset_pending();
    return $pending !== null && !empty($pending['user_id']) && !empty($pending['security_question']);
}

/**
 * Passo 2: usuário responde a pergunta de segurança cadastrada no perfil.
 *
 * Regras de segurança:
 *  - comparação sempre feita com password_verify()/hash, nunca em texto
 *    puro (ver verify_user_security_answer());
 *  - número de tentativas erradas é limitado (contador na sessão do
 *    próprio fluxo de reset, configurável em
 *    rules.security_answer_max_attempts); ao esgotar as tentativas,
 *    o usuário precisa reiniciar o processo informando o e-mail
 *    novamente.
 */
function confirm_password_reset_security_answer(string $answer): bool
{
    $pending = password_reset_pending();
    if (!$pending || empty($pending['user_id']) || empty($pending['security_question'])) {
        return false;
    }

    $maxAttempts = (int) (config('rules.security_answer_max_attempts') ?: 5);
    if ((int) ($pending['security_attempts'] ?? 0) >= $maxAttempts) {
        return false;
    }

    $ok = verify_user_security_answer((int) $pending['user_id'], $answer);
    if ($ok) {
        $_SESSION['pwd_reset']['verified'] = true;
        $_SESSION['pwd_reset']['verified_at'] = time();
    } else {
        $_SESSION['pwd_reset']['security_attempts'] = (int) ($pending['security_attempts'] ?? 0) + 1;
    }

    return $ok;
}

/**
 * Passo 3: pergunta de segurança validada, usuário cadastra a nova senha.
 */
function complete_password_reset(string $password, string $confirmPassword): void
{
    if (!password_reset_can_set_new_password()) {
        throw new RuntimeException('Identidade não confirmada. Reinicie o processo de recuperação de senha.');
    }
    if ($password !== $confirmPassword) {
        throw new RuntimeException('As senhas não coincidem.');
    }

    $pending = password_reset_pending();
    reset_user_password((int) $pending['user_id'], $password);
    clear_password_reset();
}