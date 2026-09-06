<?php

// Essa lista guarda o NOME de todas as tabelas que nosso código tem permissão de mexer.
// É tipo uma "lista de convidados": só quem está escrito aqui pode entrar.
const REPOSITORY_TABLES = [
    'clinics', 'specialties', 'users', 'doctors', 'doctor_schedules',
    'schedule_blocks', 'appointment_slots', 'appointments',
    'medical_records', 'payments', 'notifications', 'password_resets',
];

// Essa função só confere se o nome de tabela que chegou é um nome permitido. Não devolve nada (void).
function repo_assert_table(string $table): void
{
    // in_array($table, REPOSITORY_TABLES, true) pergunta: "$table está dentro da lista REPOSITORY_TABLES?"
    // o "true" no final pede comparação rígida (compara tipo e valor, não só "parece igual")
    // o "!" na frente inverte a pergunta: "SE NÃO estiver na lista..."
    if (!in_array($table, REPOSITORY_TABLES, true)) {
        // ...aí a gente joga um erro (throw) e a execução para bem aqui, com essa mensagem de aviso
        throw new RuntimeException('Tabela desconhecida: ' . $table);
    }
    // se a tabela for válida, a função só termina aqui, sem fazer mais nada
    // (repara: não tem "return", o tipo dela é "void", ou seja, "essa função não devolve valor nenhum")
}

// Busca uma linha de uma tabela, pelo id dela. Devolve um array com os dados, ou null se não achar nada.
function repository_find(string $table, int $id): ?array
{
    repo_assert_table($table);
    // primeiro confere se pode mexer nessa tabela, se não puder, já para tudo aqui, nem chega na linha de baixo

    $stmt = db()->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    // monta o comando SQL: "SELECT * FROM nome_da_tabela WHERE id = ? LIMIT 1"
    // "*" quer dizer "todas as colunas". "?" é um espaço reservado pro valor do id, que ainda vai chegar.
    // prepare() só monta o comando, ainda não roda ele de verdade.

    $stmt->execute([$id]);
    // agora roda o comando de verdade, encaixando $id no lugar daquele "?"

    $row = $stmt->fetch();
    // pega a primeira (e única, por causa do LIMIT 1) linha que veio como resultado

    return $row ?: null;
    // se não achou nenhuma linha, fetch() devolve "false", o "?:" troca esse "false" por "null"
    // (fica mais fácil de checar depois com "if ($usuario) { ... }")
}

// O "C" de CRUD (Create). Insere uma linha nova numa tabela e devolve o id que o banco gerou pra ela.
function repository_append(string $table, array $data): int
{
    repo_assert_table($table);
    // confere se pode mexer nessa tabela

    unset($data['id']);
    //   remove a chave 'id' do array $data, CASO ela exista por acaso.
    //   a gente nunca deixa esse array escolher o id, quem gera o id é o próprio banco, sozinho
    //   (é o AUTO_INCREMENT que vemos no desenho das tabelas)

    $columns = array_keys($data);
    //   pega só os NOMES das colunas (as "chaves" do array).
    //   exemplo: se $data = ['name' => 'Maria', 'email' => 'maria@x.com'],
    //   então $columns vira ['name', 'email']

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    //   essa linha faz duas coisas, de dentro pra fora:
    //   1) array_fill(0, count($columns), '?') cria um array com um "?" repetido, uma vez pra cada coluna.
    //      se tem 2 colunas, vira ['?', '?']
    //   2) implode(', ', [...]) junta esse array numa string só, separando por vírgula: "?, ?"

    $columnList = implode('`, `', $columns);
    //   junta os NOMES das colunas com crase+vírgula+crase entre eles: "name`, `email"
    //   (as crases de fora, que já estão escritas na linha de baixo, fecham o "sanduíche")
    //   crase é como o MySQL reconhece "isso aqui é nome de coluna", evita confusão com outras palavras

    $stmt = db()->prepare("INSERT INTO `$table` (`$columnList`) VALUES ($placeholders)");
    //   junta tudo isso e monta o comando final, algo tipo:
    //   INSERT INTO `users` (`name`, `email`) VALUES (?, ?)

    $stmt->execute(array_values($data));
    //   array_values($data) pega só os valores do array, na mesma ordem das colunas: ['Maria', 'maria@x.com']
    //   execute() roda o comando de verdade, encaixando cada valor no "?" correspondente

    return (int) db()->lastInsertId();
    //   pergunta pro banco "qual foi o último id que você acabou de gerar?" e devolve como número inteiro
}

// O "U" de CRUD (Update). Atualiza uma linha que já existe, só nas colunas que a gente mandar mudar.
function repository_replace(string $table, int $id, array $data): void
{
    repo_assert_table($table);
    //   confere se pode mexer nessa tabela

    unset($data['id']);
    //   tira o 'id' do array de mudanças, não faz sentido "atualizar o id pra ele mesmo"

    if (!$data) {
        return;
    }
    //   se depois de tirar o 'id' não sobrou NADA no array (array vazio conta como "falso" em PHP),
    //   não tem o que atualizar, a função para aqui, sem fazer nenhum UPDATE

    $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($data)));
    //   essa é a linha mais "esquisita", vamos com calma:
    //   array_keys($data) pega os nomes das colunas a mudar, ex: ['name', 'phone']
    //   array_map(...) roda uma mini-função em CADA item desse array e monta um array novo com os resultados
    //   "static fn(string $c): string => "`$c` = ?"" é uma ARROW FUNCTION, um jeito curto de escrever
    //   uma função de uma linha: ela recebe um nome de coluna ($c) e devolve o texto "`coluna` = ?".
    //   Repara que não escrevemos "return", tudo que vem depois do "=>" já é devolvido sozinho.
    //   Exemplo: pra ['name', 'phone'], o resultado do array_map vira ['`name` = ?', '`phone` = ?']
    //   e o implode(', ', ...) junta isso: "`name` = ?, `phone` = ?"

    $stmt = db()->prepare("UPDATE `$table` SET $set WHERE id = ?");
    //  monta o comando final, tipo: UPDATE `users` SET `name` = ?, `phone` = ? WHERE id = ?

    $stmt->execute([...array_values($data), $id]);
    //   array_values($data) pega os VALORES na mesma ordem das colunas: ['Maria', '11999990000']
    //   os "..." (spread operator) "espalham" esses valores soltos dentro de um array novo,
    //   e colocamos $id por último, porque o último "?" do comando (o do WHERE id = ?) precisa dele
    //   no fim, o array vira algo tipo: ['Maria', '11999990000', 4]
}

// Não existe repository_delete() de propósito! Nunca apagamos uma linha de verdade, só trocamos o
// "status" pra 'inactive' ou 'cancelled'. Isso se chama "soft delete" (exclusão suave): o histórico
// nunca se perde, só fica marcado como cancelado/inativo.

// Um "apelido" mais fácil de ler pra "busca um usuário pelo id".
function repository_find_user(int $id): ?array
{
    return repository_find('users', $id);
    //  só chama a função genérica de busca, já dizendo "procura na tabela 'users'"
}

// Pega os dados de um usuário e "gruda" nele o NOME da clínica (não só o número do clinic_id).
function repository_user_with_clinic(array $user): array
{
    $clinic = !empty($user['clinic_id']) ? repository_find('clinics', (int) $user['clinic_id']) : null;
    //   isso é um "if resumido numa linha só", chamado operador ternário: condição ? seVerdadeiro : seFalso
    //   se $user['clinic_id'] tiver algum valor (não vazio, não zero, não nulo):
    //      busca essa clínica na tabela 'clinics'
    //   senão:
    //      $clinic vira null (esse paciente não tem clínica vinculada)

    $user['clinic_name'] = $clinic['name'] ?? null;
    //   cria uma NOVA posição no array $user, chamada 'clinic_name'
    //   se $clinic existir e tiver 'name', usa esse nome; senão (??), usa null

    return $user;
    //  devolve o array $user só que agora com essa informação extra dentro
}

// Atualiza um usuário, e sempre grava também a data/hora da última mudança.
function repository_update_user(int $id, array $changes): void
{
    $changes['updated_at'] = now_sql();
    //   adiciona (ou substitui) a chave 'updated_at' dentro do array $changes,
    //   com a data/hora de agora (a função now_sql() a gente já criou no helpers.php)

    repository_replace('users', $id, $changes);
    //   chama a função de UPDATE genérica, já dizendo "é na tabela 'users'"
}

// Busca um usuário pelo E-MAIL em vez de pelo id, usada no login e no cadastro.
function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    //  monta o comando: busca em 'users' onde o email bate com o valor que vamos mandar

    $stmt->execute([strtolower($email)]);
    //  strtolower($email) converte o e-mail pra minúsculo antes de buscar
    //   (assim "Maria@Email.com" e "maria@email.com" são tratados como o mesmo e-mail)

    $row = $stmt->fetch();
    //  pega a linha encontrada (se encontrou)

    return $row ?: null;
    //  se não achou, devolve null; se achou, devolve o array com os dados do usuário
}

// Confere se um e-mail já está cadastrado (usada no cadastro, pra não deixar duplicar).
function email_in_use(string $email): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?)');
    //   COUNT(*) conta QUANTAS linhas batem com essa condição, em vez de trazer os dados inteiros
    //   LOWER(email) e LOWER(?) convertem os dois lados pra minúsculo antes de comparar

    $stmt->execute([$email]);
    //   executa, encaixando $email no lugar do "?"

    return (int) $stmt->fetchColumn() > 0;
    //   fetchColumn() pega só o primeiro valor do resultado (o número que o COUNT(*) devolveu)
    //   (int) converte esse valor pra número inteiro de verdade
    //   "> 0" transforma isso numa pergunta de SIM/NÃO: "esse número é maior que zero?"
    //   se for (achou pelo menos 1), devolve true (e-mail já em uso); senão, devolve false
}

function next_appointment_for_patient(int $pacienteId): ?array
{
    $sql = "
        SELECT
            a.id,
            a.status,
            a.modality,
            slot.slot_start,
            slot.slot_end,
            medico.name AS doctor_name,
            esp.name AS specialty_name,
            clin.name AS clinic_name
        FROM appointments a
        JOIN appointment_slots slot ON slot.id = a.slot_id
        JOIN doctors doc ON doc.id = a.doctor_id
        JOIN users medico ON medico.id = doc.user_id
        JOIN specialties esp ON esp.id = a.specialty_id
        JOIN clinics clin ON clin.id = a.clinic_id
        WHERE a.patient_id = ?
          AND a.status IN ('pending', 'confirmed')
          AND slot.slot_start >= ?
        ORDER BY slot.slot_start ASC
        LIMIT 1
    ";
    //   vamos por partes, porque é grande:
    //   SELECT a.id, a.status...  - escolhe quais colunas queremos no resultado. O "a."
    //     na frente de cada uma diz DE QUAL TABELA aquela coluna vem (porque várias
    //     tabelas grudadas podem ter colunas de mesmo nome, tipo "id" ou "name", sem
    //     esse prefixo, o banco não saberia qual "name" você quer: o do médico, da
    //     especialidade ou da clínica).

    //   "AS doctor_name"  - apelido pro resultado. Sem isso, a coluna viria só como
    //     "name", e como TEMOS VÁRIAS colunas chamadas "name" nessa consulta (médico,
    //     especialidade, clínica), ficaria impossível saber qual é qual no resultado
    //     em PHP. Com o apelido, cada uma sai com um nome único.

    //   FROM appointments a  - começa pela tabela appointments, e já apelidamos ela de
    //     "a" (só pra não escrever "appointments." toda hora).

    //   JOIN appointment_slots slot ON slot.id = a.slot_id  - "gruda" a tabela de
    //     horários, usando a regra de que o "id" do slot tem que ser igual ao
    //     "slot_id" guardado na consulta, é assim que o banco sabe como ligar as
    //     duas tabelas, linha com linha.

    //   As próximas linhas de JOIN repetem essa mesma ideia, encadeando: da consulta
    //     pro médico (doctors), do médico pro usuário dele (users, que tem o nome),
    //     da consulta pra especialidade, da consulta pra clínica.

    //   WHERE a.patient_id = ?  - filtra só as consultas DESSE paciente.

    //   AND a.status IN ('pending', 'confirmed')  - só consultas que ainda vão
    //     acontecer (não cancelada, não já realizada). IN(...) é um jeito curto de
    //     escrever "status = 'pending' OU status = 'confirmed'".

    //   AND slot.slot_start >= ?  - só horários que ainda não passaram (maior ou
    //     igual a agora)
    //   ORDER BY slot.slot_start ASC  - ordena da data mais próxima pra mais distante
    //     (ASC = ascendente, crescente)

    //   LIMIT 1  - pega só a primeira linha desse resultado ordenado, ou seja, a
    //     consulta futura mais próxima de acontecer

    $stmt = db()->prepare($sql);
    //   prepara a consulta (mesma ideia de sempre: os "?" viram "buracos" que só
    //   depois recebem valor de verdade, evitando injeção de SQL)

    $stmt->execute([$pacienteId, now_sql()]);
    //   preenche os dois "?" na ordem que aparecem: primeiro o id do paciente,
    //   depois a data/hora de agora (usando aquele now_sql() que já criamos)

    $linha = $stmt->fetch();
    //   fetch() (sem "All" no final) pega só uma linha do resultado, já sabemos que
    //   só pode vir 0 ou 1 linha, por causa do LIMIT 1

    return $linha ?: null;
    //   se não achou nenhuma consulta futura, fetch() devolve "false". O operador
    //   Elvis "?:" troca esse "false" por "null", que é mais claro de entender
    //   quando outra parte do código ler isso (null = "não tem próxima consulta")
}

function appointments_for_patient(int $pacienteId, array $statuses, string $ordem = 'ASC'): array
{
    $ordem = strtoupper($ordem) === 'DESC' ? 'DESC' : 'ASC';
    //   ORDER BY não dá pra parametrizar com "?" (placeholders só valem pra valores,
    //   não pra palavras-chave do SQL como ASC/DESC). Então, em vez de colar
    //   $ordem direto no SQL (o que abriria brecha pra SQL Injection),
    //   a gente confere manualmente: só aceita 'DESC' de verdade, qualquer outra
    //   coisa vira 'ASC', é a mesma ideia de "lista de permissão" que usamos no
    //   repo_assert_table() lá na parte 1 do repository.php

    $interrogacoes = implode(',', array_fill(0, count($statuses), '?'));
    //   array_fill(0, count($statuses), '?') cria um array cheio de "?", repetido
    //   uma vez pra cada status que a gente recebeu. Ex.: se $statuses tiver 2
    //   itens, isso vira ['?', '?']. implode(',', ...) junta esse array numa
    //   string separada por vírgula: "?,?". Isso é necessário porque o "IN (...)"
    //   do SQL precisa de um "?" pra caada valor da lista, e a gente não sabe de
    //   antemão quantos status vão vir, então montamos essa parte dinamicamente

    $sql = "
        SELECT
            a.id,
            a.status,
            a.modality,
            slot.slot_start,
            slot.slot_end,
            medico.name AS doctor_name,
            esp.name AS specialty_name,
            clin.name AS clinic_name
        FROM appointments a
        JOIN appointment_slots slot ON slot.id = a.slot_id
        JOIN doctors doc ON doc.id = a.doctor_id
        JOIN users medico ON medico.id = doc.user_id
        JOIN specialties esp ON esp.id = a.specialty_id
        JOIN clinics clin ON clin.id = a.clinic_id
        WHERE a.patient_id = ?
          AND a.status IN ($interrogacoes)
        ORDER BY slot.slot_start $ordem
    ";
    //   é quase o mesmo JOIN de antes, só trocamos "LIMIT 1" por nada (queremos
    //   TODAS as linhas agora) e "AND a.status IN ('pending','confirmed')" por
    //   "AND a.status IN ($interrogacoes)", os status agora vêm de fora, como
    //   parâmetro, em vez de fixos dentro da função

    $stmt = db()->prepare($sql);

    $stmt->execute([$pacienteId, ...$statuses]);
    //   o "..." (spread operator, já vimos ele na parte 1 do repository.php) pega
    //   o array $statuses e "espalha" cada item dele como um parâmetro separado,
    //   na ordem. Ex.: se $statuses = ['pending', 'confirmed'], isso equivale a
    //   escrever execute([$pacienteId, 'pending', 'confirmed']), preenchendo,
    //   nessa ordem, o "?" do patient_id e os "?,?" do IN(...)

    return $stmt->fetchAll();
    //   fetchAll() (com "All" no final, diferente do fetch() de antes) pega TODAS
    //   as linhas do resultado, devolvendo um array de arrays, um item por consulta
}

function cancel_appointment_patient(int $consultaId, int $pacienteId): void
{
    // Busca a consulta no banco, já trazendo o horário de início (que mora na tabela
    // appointment_slots, lembra do JOIN). Precisamos desse horário
    // pra conferir a regra das 24 horas mais abaixo.
    $sql = "
        SELECT a.id, a.status, a.patient_id, a.slot_id, slot.slot_start
        FROM appointments a
        JOIN appointment_slots slot ON slot.id = a.slot_id
        WHERE a.id = ?
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([$consultaId]);
    $consulta = $stmt->fetch();

    // Se não achou nenhuma linha com esse id, $consulta vem como "false".
    // Pode acontecer de alguém tentar cancelar um id que não existe (digitando
    // direto na URL, por exemplo), então a gente barra aqui.
    if (!$consulta) {
        throw new RuntimeException('Consulta não encontrada.');
    }

    // SEGURANÇA: confere se essa consulta realmente pertence ao paciente que está
    // pedindo o cancelamento. Sem essa checagem, qualquer pessoa logada poderia
    // cancelar a consulta de outro paciente só trocando o número do id no formulário.
    // (int) garante que estamos comparando número com número, não string com número.
    if ((int) $consulta['patient_id'] !== $pacienteId) {
        throw new RuntimeException('Você não tem permissão para cancelar essa consulta.');
    }

    // Não faz sentido cancelar uma consulta que já foi cancelada antes, ou que já
    // aconteceu (status 'completed'). Só deixamos cancelar quem ainda está
    // 'pending' (aguardando confirmação) ou 'confirmed' (confirmada).
    if (!in_array($consulta['status'], ['pending', 'confirmed'], true)) {
        throw new RuntimeException('Essa consulta não pode mais ser cancelada.');
    }

    // Monta dois "relógios": um com a hora de agora, outro com a hora que a
    // consulta está marcada pra começar. DateTime é uma classe pronta do PHP pra
    // trabalhar com datas sem a gente ter que fazer conta de calendário na mão
    // (quantos dias tem cada mês, ano bissexto, etc. Ela já sabe disso tudo).
    $agora = new DateTime();
    $inicioConsulta = new DateTime($consulta['slot_start']);

    // getTimestamp() transforma a data em um número: quantos segundos se passaram
    // desde 01/01/1970 até aquele momento (é assim que o computador "entende" datas
    // por baixo dos panos). Subtraindo um do outro, descobrimos quantos segundos
    // faltam até a consulta; dividindo por 3600 (segundos numa hora), isso vira horas.
    $horasAteConsulta = ($inicioConsulta->getTimestamp() - $agora->getTimestamp()) / 3600;

    // Regra da clínica: só pode cancelar com pelo menos 24h de antecedência. Se
    // faltar menos que isso (ou se a consulta já passou, o que dá um número
    // negativo aqui), barra o cancelamento.
    if ($horasAteConsulta < 24) {
        throw new RuntimeException('Só é possível cancelar com pelo menos 24 horas de antecedência.');
    }

    // Chegou até aqui? Então pode cancelar de verdade. Precisamos mudar DUAS
    // tabelas: marcar a consulta como cancelada E liberar o horário (slot) pra
    // outro paciente poder agendar nele. Usamos uma transação pra garantir que as
    // duas mudanças aconteçam juntas, se uma der certo e a outra falhar (o banco
    // cair no meio do caminho, por exemplo), desfazemos tudo, em vez de deixar o
    // banco "pela metade" (consulta cancelada, mas horário continua preso).
    $pdo = db();

    try {
        // beginTransaction() é como dizer "a partir de agora, guarda tudo em
        // rascunho, não grava de verdade ainda até eu mandar confirmar"
        $pdo->beginTransaction();

        // muda o status da consulta pra 'cancelled'
        $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->execute(['cancelled', $consultaId]);

        // libera o horário de volta, pra aparecer como disponível pra outros
        // pacientes que forem agendar depois
        $stmt = $pdo->prepare('UPDATE appointment_slots SET status = ? WHERE id = ?');
        $stmt->execute(['available', $consulta['slot_id']]);

        // as duas mudanças deram certo, então agora manda gravar de verdade no
        // banco, "assina embaixo do rascunho"
        $pdo->commit();
    } catch (Exception $e) {
        // se QUALQUER uma das duas linhas acima falhar, cai aqui: desfaz
        // qualquer mudança de rascunho que tenha sido feita (rollBack = "joga
        // fora o rascunho, volta tudo como estava antes de começar")
        $pdo->rollBack();

        // relança um erro (com uma mensagem amigável) pra quem chamou essa
        // função saber que algo deu errado, em vez de fingir que cancelou
        throw new RuntimeException('Não foi possível cancelar a consulta. Tente novamente.');
    }
}

// AGENDAR
function active_doctors_with_details(): array
{
    // busca todos os médicos ATIVOS (doc.active = 1), já trazendo junto o nome
    // dele (que mora em users), o nome da especialidade e o nome da clínica,
    // a mesma ideia de JOIN que já vimos nas consultas, só que agora partindo
    // da tabela doctors, não da appointments
    $sql = "
        SELECT doc.id AS doctor_id, medico.name AS doctor_name,
               esp.name AS specialty_name, clin.name AS clinic_name
        FROM doctors doc
        JOIN users medico ON medico.id = doc.user_id
        JOIN specialties esp ON esp.id = doc.specialty_id
        JOIN clinics clin ON clin.id = doc.clinic_id
        WHERE doc.active = 1
        ORDER BY medico.name ASC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    // repara que aqui não passamos nenhum "?" pra preencher, porque essa
    // consulta não depende de nenhum dado de fora (não tem WHERE com variável),
    // então execute() pode ser chamado sem nenhum array dentro
    return $stmt->fetchAll();
}

function find_doctor_details(int $medicoId): ?array
{
    // a mesma busca de cima, mas filtrando por UM médico específico (usada na
    // tela 2, pra mostrar o nome dele no topo). fetch() (sem "All") porque
    // só esperamos, no máximo, uma linha de volta
    $sql = "
        SELECT doc.id AS doctor_id, medico.name AS doctor_name,
               esp.name AS specialty_name, clin.name AS clinic_name
        FROM doctors doc
        JOIN users medico ON medico.id = doc.user_id
        JOIN specialties esp ON esp.id = doc.specialty_id
        JOIN clinics clin ON clin.id = doc.clinic_id
        WHERE doc.id = ? AND doc.active = 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([$medicoId]);
    $linha = $stmt->fetch();
    return $linha ?: null;
    // "?: null" é o operador Elvis de novo: se fetch() devolver false (não achou
    // ninguém), vira null; se achou, devolve a linha normalmente
}

function available_slots_for_doctor(int $medicoId): array
{
    // busca os horários LIVRES desse médico, só os que ainda vão acontecer
    // (slot_start >= NOW(), NOW() é uma função do próprio MySQL que pega a
    // data/hora atual do servidor do banco), ordenados do mais próximo pro
    // mais distante
    $sql = "
        SELECT id, slot_start, slot_end
        FROM appointment_slots
        WHERE doctor_id = ? AND status = 'available' AND slot_start >= NOW()
        ORDER BY slot_start ASC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([$medicoId]);
    return $stmt->fetchAll();
}

function book_appointment_patient(int $pacienteId, int $slotId): void
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // SELECT ... FOR UPDATE trava essa linha específica do banco: enquanto
        // essa transação não terminar (commit ou rollback), mais ninguém
        // consegue ler essa mesma linha pra tentar marcar nela também. É isso
        // que impede dois pacientes marcarem o mesmo horário ao mesmo tempo
        $stmt = $pdo->prepare('
            SELECT id, doctor_id, status
            FROM appointment_slots
            WHERE id = ?
            FOR UPDATE
        ');
        $stmt->execute([$slotId]);
        $slot = $stmt->fetch();

        if (!$slot) {
            throw new RuntimeException('Horário não encontrado.');
        }

        // se, quando a gente finalmente conseguiu "abrir a gaveta", o status
        // não for mais 'available', é porque alguém foi mais rápido e já
        // marcou esse horário antes da gente, barra com uma mensagem clara
        if ($slot['status'] !== 'available') {
            throw new RuntimeException('Esse horário não está mais disponível. Escolha outro.');
        }

        // precisamos saber o clinic_id e o specialty_id desse médico, porque a
        // tabela appointments exige essas duas colunas preenchidas também
        $stmtMedico = $pdo->prepare('SELECT clinic_id, specialty_id FROM doctors WHERE id = ?');
        $stmtMedico->execute([$slot['doctor_id']]);
        $medico = $stmtMedico->fetch();

        if (!$medico) {
            throw new RuntimeException('Médico não encontrado.');
        }

        // cria a consulta de verdade, com status inicial 'pending' (aguardando
        // confirmação da clínica/médico) e modalidade fixa 'presencial' por
        // enquanto
        $stmtInsert = $pdo->prepare('
            INSERT INTO appointments (slot_id, patient_id, doctor_id, clinic_id, specialty_id, status, modality)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmtInsert->execute([
            $slotId,
            $pacienteId,
            $slot['doctor_id'],
            $medico['clinic_id'],
            $medico['specialty_id'],
            'pending',
            'presencial',
        ]);

        // marca o horário como ocupado, pra ele sumir da lista de disponíveis
        // (tanto pra esse paciente quanto pra qualquer outro que olhar depois)
        $stmtOcupa = $pdo->prepare('UPDATE appointment_slots SET status = ? WHERE id = ?');
        $stmtOcupa->execute(['booked', $slotId]);

        // as duas mudanças deram certo: grava tudo de vez e destranca a linha
        $pdo->commit();
    } catch (Exception $e) {
        // desfaz qualquer mudança de rascunho e destranca a linha
        $pdo->rollBack();

        // se o erro já é uma das nossas RuntimeException "com mensagem
        // amigável" (horário sumiu, não encontrado...), repassa ela do jeito
        // que está, pra pessoa ver o motivo exato
        if ($e instanceof RuntimeException) {
            throw $e;
        }

        // qualquer outro erro inesperado (tipo o banco caiu no meio do
        // caminho) vira uma mensagem genérica, pra não expor detalhes técnicos
        throw new RuntimeException('Não foi possível marcar a consulta. Tente novamente.');
    }
}

function update_patient_profile(int $pacienteId, array $dados): void
{
    // trim() tira espaços em branco do início/fim (tipo se a pessoa digitou
    // " Maria " sem querer, com espaço sobrando). (string) garante que, mesmo
    // se a chave não vier no array, viramos uma string vazia em vez de dar erro
    $nome = trim((string) ($dados['name'] ?? ''));
    $email = trim((string) ($dados['email'] ?? ''));
    $telefone = trim((string) ($dados['phone'] ?? ''));
    $documento = trim((string) ($dados['document'] ?? ''));
    $dataNascimento = trim((string) ($dados['birth_date'] ?? ''));
    $endereco = trim((string) ($dados['address'] ?? ''));

    if ($nome === '') {
        throw new RuntimeException('Informe seu nome.');
    }

    // filter_var(..., FILTER_VALIDATE_EMAIL) é uma função pronta do PHP pra
    // conferir se um texto tem cara de e-mail (tem "@", tem domínio depois...).
    // Se o texto não passar nesse formato, ela devolve "false"
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }

    // confere se já existe outra conta usando esse e-mail (a coluna email é
    // UNIQUE no banco, então se a gente nem checar isso, o UPDATE lá embaixo
    // ia estourar um erro feio do MySQL em vez de uma mensagem amigável)
    $usuarioComEsseEmail = find_user_by_email($email);

    // se achou alguém com esse e-mail, mas o id dessa pessoa é DIFERENTE do
    // id de quem está editando o próprio perfil, é porque o e-mail já é de
    // outra conta (se for o mesmo id, tudo bem, é a pessoa mantendo o
    // próprio e-mail sem mudar nada)
    if ($usuarioComEsseEmail && (int) $usuarioComEsseEmail['id'] !== $pacienteId) {
        throw new RuntimeException('Esse e-mail já está sendo usado por outra conta.');
    }

    $sql = "
        UPDATE users
        SET name = ?, email = ?, phone = ?, document = ?, birth_date = ?, address = ?
        WHERE id = ?
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        $nome,
        $email,
        // os campos opcionais: se a pessoa deixou em branco, guardamos NULL
        // no banco em vez de uma string vazia "", fica mais limpo (diz "não
        // preenchido" em vez de "preenchido com nada")
        $telefone !== '' ? $telefone : null,
        $documento !== '' ? $documento : null,
        $dataNascimento !== '' ? $dataNascimento : null,
        $endereco !== '' ? $endereco : null,
        $pacienteId,
    ]);
}

// NOTIFICAÇÕES
function notifications_for_patient(int $pacienteId): array
{
    // busca os avisos desse paciente que já foram enviados (status 'sent'(já enviado))
    // ou que a pessoa já tinha visto antes ('read' (a pessoa já viu)), do mais recente pro
    // mais antigo
    $sql = "
        SELECT id, title, message, status, sent_at
        FROM notifications
        WHERE user_id = ? AND status IN ('sent', 'read')
        ORDER BY sent_at DESC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([$pacienteId]);
    return $stmt->fetchAll();
}

function mark_notifications_as_read(int $pacienteId): void
{
    // marca como "lidas" todas as notificações desse paciente que ainda
    // estavam como 'sent' (ou seja: enviadas, mas a pessoa ainda não tinha
    // entrado nessa tela pra ver). NOW() pega a data/hora atual do MySQL,
    // registrando exatamente quando ela "leu"
    $sql = "UPDATE notifications SET status = 'read', read_at = NOW() WHERE user_id = ? AND status = 'sent'";
    $stmt = db()->prepare($sql);
    $stmt->execute([$pacienteId]);
}