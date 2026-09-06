<?php

/**
 * Envio de e-mail para o fluxo de autenticação (código de recuperação
 * de senha, etc.).
 *
 * Três transportes, controlados por config('mail.transport'):
 *
 *  - "smtp" (recomendado para envio real): conecta diretamente a um
 *    servidor SMTP (Gmail, SendGrid, Mailtrap, hospedagem, etc.) usando
 *    usuário/senha. Não depende de Composer nem de bibliotecas externas.
 *  - "mail": usa a função mail() nativa do PHP. Só funciona se o
 *    servidor já tiver um MTA (sendmail/postfix) configurado — não
 *    funciona no servidor embutido do PHP (`php -S`) nem na maioria das
 *    hospedagens sem configuração extra. Existe por compatibilidade.
 *  - "log" (padrão): NÃO envia de verdade. Grava a mensagem em
 *    storage/mail_outbox.log, simulando uma caixa de saída — útil para
 *    testar o fluxo sem depender de nenhum serviço de e-mail.
 *
 * Falhas de envio são sempre registradas em storage/mail_errors.log,
 * com a resposta exata do servidor SMTP, para facilitar o diagnóstico.
 *
 * Em todos os casos, também é registrada uma notificação in-app do tipo
 * "email" na tabela `notifications`, mantendo o mesmo padrão de
 * auditoria já usado pelo restante do sistema.
 */
function send_email(string $to, string $subject, string $body, ?int $userId = null): bool
{
    $transport = config('mail.transport') ?: 'log';

    switch ($transport) {
        case 'smtp':
            $sent = send_email_via_smtp($to, $subject, $body);
            break;
        case 'mail':
            $sent = send_email_via_php_mail($to, $subject, $body);
            break;
        default:
            $sent = send_email_via_log($to, $subject, $body);
    }

    if ($userId !== null) {
        repository_append('notifications', [
            'user_id' => $userId,
            'appointment_id' => null,
            'type' => 'email',
            'title' => $subject,
            'message' => $body,
            'status' => $sent ? 'sent' : 'failed',
            'send_at' => now_sql(),
            'sent_at' => $sent ? now_sql() : null,
            'read_at' => null,
            'created_at' => now_sql(),
        ]);
    }

    return $sent;
}

/* ---------------------------------------------------------------------
 * Transporte "smtp" — cliente SMTP mínimo via sockets (sem dependências)
 * ------------------------------------------------------------------- */

function send_email_via_smtp(string $to, string $subject, string $body): bool
{
    $host = (string) config('mail.smtp_host');
    $port = (int) (config('mail.smtp_port') ?: 587);
    $username = (string) config('mail.smtp_user');
    $password = (string) config('mail.smtp_pass');
    $encryption = strtolower((string) (config('mail.smtp_encryption') ?: 'tls')); // tls | ssl | none
    $timeout = (int) (config('mail.smtp_timeout') ?: 10);
    $fromAddress = (string) config('mail.from_address');
    $fromName = (string) config('mail.from_name');

    if ($host === '') {
        mail_log_error('SMTP não configurado: defina VCTCC_MAIL_SMTP_HOST (ver api.env.example).');
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout);
    if (!$socket) {
        mail_log_error(sprintf('Falha ao conectar em %s:%d - %s (errno %d)', $host, $port, $errstr, $errno));
        return false;
    }
    stream_set_timeout($socket, $timeout);

    $readResponse = static function () use ($socket): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // A última linha de uma resposta multi-linha tem um espaço
            // (não hífen) na 4ª posição, ex.: "250 OK" vs "250-...".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };

    $command = static function (string $line) use ($socket): void {
        fwrite($socket, $line . "\r\n");
    };

    $expect = static function (array $codes, string $context) use ($readResponse, $socket): bool {
        $response = $readResponse();
        $code = substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            mail_log_error("SMTP [{$context}] resposta inesperada: " . trim($response));
            fclose($socket);
            return false;
        }
        return true;
    };

    if (!$expect(['220'], 'conexão')) {
        return false;
    }

    $localName = $_SERVER['SERVER_NAME'] ?? 'localhost';

    $command('EHLO ' . $localName);
    if (!$expect(['250'], 'EHLO')) {
        return false;
    }

    if ($encryption === 'tls') {
        $command('STARTTLS');
        if (!$expect(['220'], 'STARTTLS')) {
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            mail_log_error('Não foi possível negociar TLS com o servidor SMTP.');
            fclose($socket);
            return false;
        }
        $command('EHLO ' . $localName);
        if (!$expect(['250'], 'EHLO pós-TLS')) {
            return false;
        }
    }

    if ($username !== '') {
        $command('AUTH LOGIN');
        if (!$expect(['334'], 'AUTH LOGIN')) {
            return false;
        }
        $command(base64_encode($username));
        if (!$expect(['334'], 'usuário SMTP')) {
            return false;
        }
        $command(base64_encode($password));
        if (!$expect(['235'], 'autenticação SMTP (usuário/senha incorretos ou senha de app necessária)')) {
            return false;
        }
    }

    $command('MAIL FROM:<' . $fromAddress . '>');
    if (!$expect(['250'], 'MAIL FROM')) {
        return false;
    }

    $command('RCPT TO:<' . $to . '>');
    if (!$expect(['250', '251'], 'RCPT TO')) {
        return false;
    }

    $command('DATA');
    if (!$expect(['354'], 'DATA')) {
        return false;
    }

    $headers = "From: {$fromName} <{$fromAddress}>\r\n"
        . "To: <{$to}>\r\n"
        . 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . 'Date: ' . date('r') . "\r\n";

    // "Dot-stuffing": linhas que começam com "." precisam ser escapadas
    // para não serem confundidas com o terminador da mensagem (regra do
    // protocolo SMTP, RFC 5321).
    $safeBody = preg_replace('/^\./m', '..', $body);

    $command($headers . "\r\n" . $safeBody . "\r\n.");
    if (!$expect(['250'], 'envio da mensagem')) {
        return false;
    }

    $command('QUIT');
    fclose($socket);

    return true;
}

/* ---------------------------------------------------------------------
 * Transporte "mail" — função nativa do PHP (requer MTA no servidor)
 * ------------------------------------------------------------------- */

function send_email_via_php_mail(string $to, string $subject, string $body): bool
{
    $fromAddress = (string) config('mail.from_address');
    $fromName = (string) config('mail.from_name');
    $headers = 'From: ' . $fromName . ' <' . $fromAddress . '>' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8';

    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        $lastError = error_get_last();
        mail_log_error(
            'mail() nativo falhou (o servidor provavelmente não tem sendmail/MTA configurado). '
            . 'Detalhe: ' . ($lastError['message'] ?? 'desconhecido')
            . '. Considere usar transport=smtp em vez de "mail".'
        );
    }

    return $sent;
}

/* ---------------------------------------------------------------------
 * Transporte "log" — simulação para desenvolvimento/homologação
 * ------------------------------------------------------------------- */

function send_email_via_log(string $to, string $subject, string $body): bool
{
    $line = sprintf(
        "[%s] Para: %s | Assunto: %s\n%s\n%s\n\n",
        now_sql(),
        $to,
        $subject,
        $body,
        str_repeat('-', 60)
    );

    return mail_write_log('mail_outbox.log', $line);
}

/* ---------------------------------------------------------------------
 * Utilidades de log (diagnóstico de falhas de envio)
 * ------------------------------------------------------------------- */

function mail_log_error(string $message): void
{
    mail_write_log('mail_errors.log', '[' . now_sql() . '] ' . $message . "\n");
}

function mail_write_log(string $filename, string $content): bool
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return @file_put_contents($dir . '/' . $filename, $content, FILE_APPEND | LOCK_EX) !== false;
}
