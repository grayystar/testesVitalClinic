<?php
// Arquivo de teste temporário — APAGAR depois de usar.
// Roda a MESMA lógica de login_user(), mas imprimindo cada etapa,
// pra descobrir exatamente onde o processo está falhando.

require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/repository.php';

$email = 'admin@clinica.local';
$senhaDigitada = 'Senha123!';
$expectedRole = 'admin';

echo '<h2>Diagnóstico passo a passo do login</h2>';

try {
    $candidate = find_user_by_email($email);
} catch (Throwable $e) {
    die('<p style="color:red"><strong>Erro ao conectar/consultar o banco:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>');
}

echo '<p><strong>1) find_user_by_email("' . htmlspecialchars($email) . '") encontrou algo?</strong> ';
echo $candidate ? 'SIM' : '<span style="color:red">NÃO — usuário não encontrado com esse e-mail!</span>';
echo '</p>';

if ($candidate) {
    echo '<pre>' . htmlspecialchars(print_r($candidate, true)) . '</pre>';

    echo '<p><strong>2) Status é "active"?</strong> ' . ($candidate['status'] === 'active' ? 'SIM' : '<span style="color:red">NÃO (status atual: ' . htmlspecialchars($candidate['status']) . ')</span>') . '</p>';

    echo '<p><strong>3) Role é admin ou doctor?</strong> ' . (in_array($candidate['role'], ['admin', 'doctor'], true) ? 'SIM (' . htmlspecialchars($candidate['role']) . ')' : '<span style="color:red">NÃO (role atual: ' . htmlspecialchars($candidate['role']) . ')</span>') . '</p>';

    echo '<p><strong>4) Role bate com o esperado ("' . htmlspecialchars($expectedRole) . '")?</strong> ' . ($candidate['role'] === $expectedRole ? 'SIM' : '<span style="color:red">NÃO</span>') . '</p>';

    $senhaOk = password_verify($senhaDigitada, $candidate['password_hash']);
    echo '<p><strong>5) password_verify("' . htmlspecialchars($senhaDigitada) . '", hash_do_banco)?</strong> ' . ($senhaOk ? '<span style="color:green">SIM — bate certinho</span>' : '<span style="color:red">NÃO</span>') . '</p>';
}
