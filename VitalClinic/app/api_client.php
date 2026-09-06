<?php

function api_is_enabled(): bool
{
    return config('data.mode') === 'api' && (string) config('data.api_base_url') !== '';
}

function api_request(string $method, string $path, ?array $payload = null): array
{
    $baseUrl = (string) config('data.api_base_url');
    if ($baseUrl === '') {
        throw new RuntimeException('A API central ainda não foi configurada.');
    }

    $url = $baseUrl . '/' . ltrim($path, '/');
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    $token = (string) config('data.api_token');
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $body = null;
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => max(1, (int) config('data.timeout')),
        CURLOPT_TIMEOUT => max(1, (int) config('data.timeout')),
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false || $error !== '') {
        throw new RuntimeException('Não foi possível conectar à API central.');
    }

    $decoded = json_decode((string) $responseBody, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? '') : '';
        throw new RuntimeException($message ?: 'A API central retornou um erro.');
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('A API central retornou uma resposta inválida.');
    }

    return $decoded;
}

function api_state(): array
{
    $response = api_request('GET', 'v1/state');
    return $response['data'] ?? $response;
}

function api_save_state(array $state): void
{
    api_request('PUT', 'v1/state', ['data' => $state]);
}
