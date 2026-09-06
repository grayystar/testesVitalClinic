<?php
// Esse arquivo tem uma responsabilidade, de abrir e devolver uma conexão com o banco, 
// pra qualquer outro arquivo usar

// cria uma função chamada db. O : PDO no final é uma "promessa de tipo": 
// diz "essa função sempre devolve um objeto do tipo PDO". PDO é uma classe pronta
// do próprio PHP, o nome vem de PHP Data Objects, e é o jeito padrão do PHP conversar
// com bancos de dados (funciona com MySQL, mas também com outros tipos de banco, mudando só o "endereço").
function db(): PDO
{
    // Normalmente, toda vez que uma função termina, as variáveis dela são esquecidas. static 
    // muda isso: essa variável $pdo lembra o valor entre uma chamada e outra da mesma função,
    // durante a mesma execução da página. Serve pra evitar abrir uma conexão nova toda vez que 
    // alguma parte do código pedir o banco, a gente abre UMA vez só e reaproveita
    static $pdo = null;

    // Se $pdo já é uma conexão de verdade (não é mais null), devolve ela direto, sem abrir outra".
    // Isso é o que faz o "lembrar" do static valer a pena.
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // chama uma função config() (que a gente ainda vai criar, no próximo arquivo) pedindo só o pedaço 
    // 'db' daquele array que o config.php devolveu. Isso guarda em $cfg os dados de host, porta, nome,
    //  usuário e senha.
    $cfg = config('db');

    // Monta uma string (texto) no formato exato que o PDO exige pra saber ONDE conectar. sprintf é tipo 
    // um "preenche lacunas": os %s e %d no texto viram os valores que vêm depois, na ordem (%s espera 
    // texto, %d espera número). O resultado fica algo tipo: mysql:host=127.0.0.1;port=3306;dbname=vitalclinic;charset=utf8mb4. 
    // Isso se chama DSN (Data Source Name) — o "endereço completo" do banco.
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    // Isso é tratamento de erro. O PHP tenta rodar o que está dentro do try; 
    // se der algum erro ali dentro, em vez de travar o site com uma tela feia, 
    // ele "pula" pro catch e a gente decide o que fazer. É tipo um plano B.
    try {
        // Aqui a conexão de verdade acontece: cria um novo objeto PDO passando o endereço, 
        // usuário, senha e um array de opções extras.
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            // Diz "se der erro de SQL, lança uma exceção" (um erro "capturável", que o try/catch consegue pegar)
            // em vez de só devolver false silenciosamente, o que seria fácil de passar despercebido.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Diz "quando eu buscar dados, devolve como array associativo" (tipo $linha['name'])
            // em vez de um objeto estranho ou array numerado.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Desliga uma "simulação" que o PDO às vezes faz e deixa a proteção contra SQL injection (um tipo de ataque)
            // mais forte de verdade, usando o mecanismo nativo do MySQL.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    // Se a conexão falhar (banco desligado, senha errada...), a gente pega aquele erro técnico 
    // do PDO e relança como um erro "nosso" (RuntimeException), com uma mensagem mais amigável. 
    // Isso vai deixar a gente, lá no index.php, mostrar uma telinha bonitinha de "erro de conexão" 
    // em vez do site quebrar feio.  
    } catch (PDOException $e) {
        throw new RuntimeException('Não foi possível conectar ao banco de dados MySQL: ' . $e->getMessage());
    }
    // Devolve a conexão pronta pra quem chamou a função db().
    return $pdo;
}

// Esta função serve para proteger os dados na hora do envio e recebimento, caso o sistema caia e os dados
// estão sendo enviados, para evitar perda ou ele para os dois ou ele faz os dois(envio e recebimento).
// Isso é uma transação.
// Recebe como argumento uma função (callable quer dizer "algo que pode ser chamado como função"). A ideia é
// : você me diz TUDO que precisa acontecer junto, e eu garanto que ou acontece tudo, ou nada.
function db_transaction(callable $fn)
{
    $pdo = db();
    // Avisa o banco "a partir de agora, guarda tudo que eu fizer, mas não aplica de verdade ainda".
    $pdo->beginTransaction();
    try {
        // Executa a função que foi passada (o "pacote de ações"), guardando o que ela devolver.
        $result = $fn($pdo);
        // Se chegou até aqui sem erro, confirma tudo de uma vez — "pode aplicar de verdade".
        $pdo->commit();
        return $result;
    // Se deu qualquer erro no meio do caminho, desfaz tudo que tinha sido feito (rollBack, tipo um
    // "Ctrl+Z" do banco) e relança o erro pra quem chamou saber que algo deu errado.
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}