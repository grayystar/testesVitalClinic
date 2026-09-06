<?php

// current_user() responde a pergunta "quem está logado agora?", devolve os dados da pessoa, ou null.
function current_user(): ?array
{
    static $user = false;
    //   "static" faz essa variável lembrar o valor de uma chamada pra outra, na mesma página.
    //   Começa em "false" só como marcador de "ainda não conferi", é diferente de "null", que a
    //   gente já vai usar mais embaixo com o significado "conferi, e não tem ninguém logado".
    //   Precisamos desses DOIS valores diferentes pra função saber se já rodou antes ou não.

    if ($user !== false) {
        return $user;
    }
    //   se $user já não é mais "false" (ou seja, essa função já rodou antes nessa mesma página),
    //   devolve o valor que já foi descoberto, sem checar tudo de novo à toa

    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }
    //   $_SESSION é o "crachá" temporário do navegador. empty(...) pergunta "essa posição não
    //   existe, ou está vazia?". Se não tiver 'user_id' guardado, ninguém está logado:
    //   guarda null (pra lembrar da próxima vez que a função rodar) e devolve null

    $candidate = repository_find_user((int) $_SESSION['user_id']);
    //   se chegou até aqui, tem um id guardado na sessão. Busca no banco quem tem esse id.
    //   chamamos de "$candidate" (candidato) porque ainda falta conferir se é válido

    if (!$candidate || $candidate['status'] !== 'active' || $candidate['role'] !== 'patient') {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }
    //   três checagens ligadas por "||" (OU), se qualquer uma for verdadeira, cai aqui dentro:
    //   1) !$candidate        - não achou ninguém no banco com esse id
    //   2) status !== 'active' - a conta existe, mas foi desativada
    //   3) role !== 'patient'  - a conta existe e tá ativa, mas não é de paciente
    //      (pode ser admin/médico tentando entrar nesse site, que é só pra paciente)
    //   em qualquer um desses casos: apaga o id da sessão e devolve null

    $user = repository_user_with_clinic($candidate);
    return $user;
    //   passou em tudo: é um paciente de verdade e ativo. Pega os dados já com o nome da
    //   clínica grudado, guarda (pra lembrar) e devolve
}

// require_login() é um "porteiro": só deixa passar quem tiver logado. Quem não tiver, vai pro login.
function require_login(): array
{
    $user = current_user();
    //   pergunta "quem está logado?"

    if (!$user) {
        flash('error', 'Entre para continuar.');
        redirect(['page' => 'login']);
    }
    //   se ninguém estiver logado (null conta como "falso" em PHP): guarda um aviso e manda
    //   pra tela de login. redirect() sempre termina com "exit" lá dentro, então se cair
    //   aqui, a função para nesse ponto, nunca chega na linha debaixo

    return $user;
    //   se chegou até aqui, tem alguém logado, devolve os dados dessa pessoa
}

// login_user() confere e-mail + senha e, se bater, "loga" a pessoa. Devolve true ou false.
function login_user(string $email, string $password): bool
{
    $candidate = find_user_by_email($email);
    //   busca no banco alguém com esse e-mail

    if ($candidate && (!in_array($candidate['status'], ['active'], true) || $candidate['role'] !== 'patient')) {
        $candidate = null;
    }
    //   se achou alguém, mas essa conta não está ativa OU não é de paciente, a gente finge
    //   que não achou nada (joga null em $candidate). É de propósito: não damos pista tipo
    //   "esse e-mail existe, só que é de médico" pra quem estiver tentando adivinhar contas

    if (!$candidate || !password_verify($password, $candidate['password_hash'])) {
        return false;
    }
    //   duas checagens com "||" (OU): não achou ninguém válido, OU a senha digitada não bate
    //   com o hash guardado. Se qualquer uma for verdade, devolve false e PARA aqui

    $_SESSION['user_id'] = (int) $candidate['id'];
    //   esse é o momento exato em que o login acontece de verdade: guarda o id na sessão.
    //   a partir daqui, current_user() vai encontrar esse id e reconhecer a pessoa

    repository_update_user((int) $candidate['id'], ['last_login_at' => now_sql()]);
    //   atualiza no banco "quando foi o último login", com a data/hora de agora

    return true;
    //   avisa quem chamou a função que o login deu certo
}

// logout_user() desfaz o login: só remove o id da sessão.
function logout_user(): void
{
    unset($_SESSION['user_id']);
    //   apaga essa posição da sessão, sem ela, current_user() não acha mais ninguém.
    //   é a mesma coisa que "esquecer quem você era"
}

// register_patient() cadastra um paciente novo, depois de conferir se os dados estão OK.
function register_patient(array $data): int
{
    $name = trim((string) ($data['name'] ?? ''));
    //   pega o nome do array $data. "?? ''" quer dizer "se não existir essa posição, usa
    //   texto vazio em vez de dar erro". trim() tira espaço do início/fim (" Maria " vira "Maria")

    if ($name === '') {
        throw new RuntimeException('Informe seu nome completo.');
    }
    //   se depois de tirar espaço não sobrou nada, o nome tava vazio: erro, e para aqui

    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }
    //   filter_var(valor, FILTER_VALIDATE_EMAIL) é uma função pronta do PHP que confere se o
    //   texto tem cara de e-mail de verdade (tem @, tem domínio...). O "!" na frente inverte:
    //   "se NÃO for válido..." = erro

    if (strlen((string) ($data['password'] ?? '')) < 6) {
        throw new RuntimeException('A senha deve ter pelo menos 6 caracteres.');
    }
    //   strlen() conta quantos caracteres tem o texto. Menos de 6, erro

    if (email_in_use($data['email'])) {
        throw new RuntimeException('Este e-mail já está cadastrado.');
    }
    //   chama a função do repository.php que confere se esse e-mail já existe. Se existir,
    //   não deixa cadastrar de novo (e-mail é único no nosso sistema)

    return repository_append('users', [
        'name' => $name,
        'email' => strtolower(trim($data['email'])),
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
    //  passou em todas as checagens: monta um array com os dados prontos e chama
    //   repository_append('users', [...]) pra fazer o INSERT de verdade. Explicando cada chave:
    //   'name'          - o nome já limpo
    //   'email'         - em minúsculo e sem espaço nas pontas
    //   'password_hash' - aqui a senha vira hash, nunca guardamos a senha "crua"
    //   'role'          - sempre 'patient': esse cadastro NUNCA cria admin nem médico
    //   'phone'/'document'/'birth_date'/'address' - "$data['phone'] ?: null" quer dizer
    //       "se vier vazio, guarda null em vez de string vazia" (campos opcionais)
    //   'clinic_id'     - mesma lógica: se não escolheu clínica, fica null
    //   'status'        - toda conta nova já nasce 'active'
    //   'created_at'    - data/hora de agora
    //   'updated_at' e 'last_login_at' - começam null, porque ainda não aconteceram
    //   repository_append devolve o id novo gerado pelo banco, e é isso que a função devolve
}