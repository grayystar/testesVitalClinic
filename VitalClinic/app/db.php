<?php

/**
 * Conexão única (singleton) com o banco de dados MySQL "vitalclinic".
 *
 * As credenciais vêm de app/config.php (chave 'db'), que por sua vez lê das
 * variáveis de ambiente VCTCC_DB_HOST, VCTCC_DB_PORT, VCTCC_DB_NAME,
 * VCTCC_DB_USER e VCTCC_DB_PASS. Veja api.env.example.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config('db');

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Convertido para RuntimeException para ser capturado por
        // render_install_error() em index.php e exibir uma mensagem amigável.
        throw new RuntimeException('Não foi possível conectar ao banco de dados MySQL: ' . $e->getMessage());
    }

    return $pdo;
}

/**
 * Executa uma função dentro de uma transação, com rollback automático em
 * caso de exceção. Retorna o valor de retorno da função.
 */
function db_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
