<?php

function config(?string $key = null)
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_version(): string
{
    return 'v.2.05';
}

function app_url(array $params = []): string
{
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

/**
 * Gera a URL de um arquivo estático (imagens, css, js) acrescentando
 * a data de modificação do arquivo como versão (?v=...).
 * Isso evita que o navegador ou o service worker sirvam uma versão
 * antiga em cache quando o arquivo é substituído no servidor.
 */
function asset_url(string $relativePath): string
{
    $fullPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    $version = is_file($fullPath) ? filemtime($fullPath) : time();

    return $relativePath . '?v=' . $version;
}

function is_ajax_request(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function send_json(array $payload, int $statusCode): void
{
    // Descarta qualquer coisa que já tenha sido "impressa" antes disso
    // (avisos/notices do PHP, espaços em branco antes do <?php de algum
    // arquivo, etc.) — sem isso, um simples warning solto em qualquer
    // arquivo incluído quebraria o JSON (o texto do aviso ficaria colado
    // ANTES das chaves, e o navegador não conseguiria interpretar como
    // JSON válido).
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function redirect(array $params = []): void
{
    if (is_ajax_request()) {
        // Requisições disparadas via fetch() (ex: modal de nova consulta)
        // não devem receber um redirecionamento HTTP de verdade — em vez
        // disso, respondemos com JSON e devolvemos as mensagens "flash"
        // (que normalmente só apareceriam depois do reload da página)
        // dentro do próprio corpo da resposta.
        //
        // Algumas rotinas (ex: verify_csrf(), ao expirar a sessão) usam
        // flash('error', ...) + redirect() para sinalizar falha, mesmo
        // fora de uma exceção — por isso inspecionamos o conteúdo do
        // flash aqui para decidir o status HTTP correto, em vez de
        // assumir sucesso sempre que redirect() for chamado.
        $flashMessages = take_flash();
        $hasError = false;
        foreach ($flashMessages as $item) {
            if (($item['type'] ?? '') === 'error') {
                $hasError = true;
                break;
            }
        }

        send_json(['success' => !$hasError, 'flash' => $flashMessages], $hasError ? 400 : 200);
    }

    header('Location: ' . app_url($params));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Sua sessao expirou. Tente novamente.');
        redirect(['page' => $_GET['page'] ?? 'dashboard']);
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return (new DateTime($value))->format('d/m/Y H:i');
}

function format_date(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return (new DateTime($value))->format('d/m/Y');
}

function format_time(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return (new DateTime($value))->format('H:i');
}

function format_money($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function weekday_name(int $weekday): string
{
    $names = ['Domingo', 'Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];

    return $names[$weekday] ?? '-';
}

function status_label(string $status): string
{
    $labels = [
        'pending' => 'Pendente',
        'confirmed' => 'Confirmada',
        'completed' => 'Realizada',
        'cancelled' => 'Cancelada',
        'no_show' => 'Ausencia',
        'available' => 'Livre',
        'booked' => 'Reservado',
        'blocked' => 'Bloqueado',
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function current_date_value(): string
{
    return (new DateTime())->format('Y-m-d');
}

function now_sql(): string
{
    return (new DateTime())->format('Y-m-d H:i:s');
}

function abort_forbidden(): void
{
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}
