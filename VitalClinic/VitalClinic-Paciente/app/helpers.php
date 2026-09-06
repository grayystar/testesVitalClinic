<?php
// Esse arquivo possui várias funções que serão utilizadas por outros arquivos, isso evita ter
// que copiar e colar o mesmo código em vários lugares. Princípio DRY: "não se repita".

function config(?string $key = null)
{
    static $config = null;
    //   "static" faz essa variável lembrar o valor entre uma chamada e outra da função,
    //   assim a gente só abre o arquivo config.php UMA vez, não toda hora que alguém pedir algo

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    //   se ainda não carregamos nada (primeira vez que a função roda), importa o config.php
    //   e guarda o array que ele devolve dentro de $config

    if ($key === null) {
        return $config;
    }
    //   se ninguém pediu uma chave específica (chamou só config(), sem nada dentro dos
    //   parênteses), devolve o array INTEIRO de configurações

    $value = $config;
    //   cria uma variável auxiliar, começando com todo o array de configuração,
    //   pra ir "andando" dentro dele daqui pra frente

    foreach (explode('.', $key) as $part) {
        //   explode('.', $key) quebra o texto da chave em pedaços, separando por ponto.
        //   exemplo: se $key = 'db.host', o resultado é o array ['db', 'host'].
        //   o foreach passa por cada pedaço, um de cada vez, guardando em $part

        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        //   confere: se $value ainda é um array (dá pra "entrar" nele)? e se esse pedaço
        //   existe como chave lá dentro? se qualquer coisa der errado, devolve null e para

        $value = $value[$part];
        //   "desce" um nível: pega só o pedaço de dentro do array correspondente a essa chave.
        //   na 1ª volta (com $part = 'db'), $value vira o array de dentro de 'db'.
        //   na 2ª volta (com $part = 'host'), $value vira o texto do host de verdade
    }

    return $value;
    //   depois de passar por todos os pedaços da chave, devolve o valor final encontrado
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    //   (string) $value converte o valor pra texto, por garantia.
    //   htmlspecialchars() troca caracteres perigosos por versões seguras (ex: "<" vira "&lt;").
    //   ENT_QUOTES faz isso valer também pra aspas simples e duplas, não só < e >.
    //   'UTF-8' é o "alfabeto de caracteres" usado (o mesmo do resto do site, com acento etc)
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    //   se ainda NÃO existe um token guardado na sessão dessa pessoa, cria um novo:
    //   random_bytes(32) gera 32 bytes totalmente aleatórios (impossível de adivinhar).
    //   bin2hex(...) converte esses bytes num texto de letras/números, mais fácil de guardar

    return $_SESSION['csrf_token'];
    //   devolve o token, o que acabou de ser criado, ou um que já existia de antes
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    //   monta um pedaço de HTML pronto: um campo escondido do formulário, com o valor
    //   sendo o token de segurança. O "." cola (concatena) pedaços de texto.
    //   Passamos o token por h() também, por costume, nunca custa nada
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    //   pega o token que veio junto do formulário enviado. Se não veio nada, usa texto vazio

    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Sua sessão expirou. Tente novamente.');
        redirect(['page' => $_GET['page'] ?? 'dashboard']);
    }
    //   duas checagens com "||" (OU): 1) não veio token nenhum, OU 2) o token que veio
    //   não bate com o que está guardado na sessão (hash_equals compara sem vazar pista
    //   pelo tempo, como já vimos). Se qualquer uma for verdade: avisa e redireciona,
    //   interrompendo a ação que a pessoa estava tentando fazer
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    //   "[]" depois de $_SESSION['flash'] significa "acrescenta no fim da lista", sem
    //   apagar o que já tinha. Cada item guardado é um array com o tipo ('error'/'success')
    //   e o texto da mensagem. Guardamos numa lista porque pode ter mais de uma acumulada
}

function take_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    //   pega a lista de mensagens guardada. Se não tiver nenhuma, usa lista vazia

    unset($_SESSION['flash']);
    //   apaga essa posição da sessão, é isso que garante que a mensagem só apareça uma vez

    return $messages;
    //   devolve a lista que foi pega, antes de ser apagada
}

function app_url(array $params = []): string
{
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
    //   sempre começa com 'index.php'. Depois, um ternário (condição ? seVerdade : seFalso):
    //   se $params tem alguma coisa dentro: gruda "?" + http_build_query($params)
    //   (isso transforma ['page' => 'consultas'] no texto "page=consultas").
    //   se $params estiver vazio: gruda só texto vazio, não muda nada.
    //   exemplo: app_url(['page' => 'login']) = "index.php?page=login"
}

function asset_url(string $relativePath): string
{
    $fullPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    //   monta o caminho completo do arquivo no computador (não é a URL ainda, é o "endereço
    //   físico"). __DIR__ é a pasta de app/. '/../' sobe uma pasta (vai pra raiz do projeto).
    //   ltrim(..., '/') tira uma "/" do começo, se tiver, pra não duplicar barra

    $version = is_file($fullPath) ? filemtime($fullPath) : time();
    //   outro ternário: se o arquivo existe de verdade (is_file), pega a data da última
    //   modificação dele (filemtime). Se não existir, usa a hora atual como plano B

    return $relativePath . '?v=' . $version;
    //   devolve o caminho original (esse sim usado na URL), com "?v=123456" grudado,
    //   isso força o navegador a buscar de novo se o arquivo mudou
}

function is_ajax_request(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    //   $_SERVER guarda informações técnicas do pedido que chegou. 'HTTP_X_REQUESTED_WITH'
    //   é um "cabeçalho" que o JavaScript manda quando faz um fetch() (a gente configura
    //   isso no JS, mais pra frente). "?? ''" evita erro se esse cabeçalho não vier.
    //   o resultado da comparação "===" já é true ou false, e é isso que a função devolve
}

function send_json(array $payload, int $statusCode): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    //   ob_get_level() diz quantas "camadas" de buffer de saída estão ligadas (lembra do
    //   ob_start() do index.php?). Esse while fecha uma camada de cada vez, descartando
    //   o que tinha guardado, até não sobrar nenhuma, garante que nada seja impresso
    //   antes do JSON que vamos mandar

    header('Content-Type: application/json; charset=utf-8');
    //   avisa o navegador: "o que vou mandar agora é JSON, não HTML"

    http_response_code($statusCode);
    //   define o código de status HTTP (200 = deu certo, 400 = erro do usuário, etc,
    //   o JavaScript usa isso pra saber se deu certo ou não)

    echo json_encode($payload);
    //   json_encode() transforma o array PHP $payload num texto em formato JSON.
    //   echo imprime esse texto, é a resposta de verdade que o navegador recebe

    exit;
    //   para a execução aqui, pra garantir que nada mais seja impresso depois do JSON
}

function redirect(array $params = []): void
{
    if (is_ajax_request()) {
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
    //   se o pedido veio de JavaScript, não dá pra mandar um redirecionamento de página de
    //   verdade, em vez disso: pega as mensagens guardadas, passa por cada uma (foreach)
    //   procurando alguma do tipo 'error'; se achar, marca $hasError = true e já sai do
    //   loop (break). Depois manda um JSON dizendo se deu certo (o contrário de ter erro),
    //   junto com as mensagens e o código HTTP certo

    header('Location: ' . app_url($params));
    //   se NÃO veio de JavaScript: manda o cabeçalho HTTP "Location", o comando de
    //   verdade que diz pro navegador "vai pra essa outra página"

    exit;
    //   para a execução é essencial, senão o código de baixo rodaria mesmo depois
    //   de já ter mandado redirecionar
}

function post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
    //   de dentro pra fora: $_POST[$key] ?? $default pega o valor daquele campo do
    //   formulário, ou usa $default (vazio, por padrão) se não vier nada.
    //   (string) garante que é texto. trim() tira espaço em branco do início/fim
}

function format_datetime(?string $value): string
{
    if (!$value) return '-';
    //   se não veio valor nenhum (null ou vazio, que contam como "falso"), devolve só um
    //   travessão, mostra "sem data" de um jeito limpo, em vez de dar erro

    return (new DateTime($value))->format('d/m/Y H:i');
    //   "new DateTime($value)" cria um objeto que entende aquele texto de data (tipo
    //   "2026-08-31 09:00:00") e sabe formatar. ->format('d/m/Y H:i') pede o formato
    //   brasileiro: dia/mês/ano hora:minuto
}

function format_date(?string $value): string
{
    if (!$value) return '-';
    return (new DateTime($value))->format('d/m/Y');
    //   mesma ideia da de cima, mas só a data, sem hora
}

function format_time(?string $value): string
{
    if (!$value) return '-';
    return (new DateTime($value))->format('H:i');
    //   mesma ideia, mas só a hora
}

function status_label(string $status): string
{
    $labels = [
        'pending' => 'Aguardando confirmação',
        'confirmed' => 'Confirmada',
        'completed' => 'Realizada',
        'cancelled' => 'Cancelada',
        'no_show' => 'Ausência',
    ];
    //   um "dicionário de tradução": de um lado o código técnico guardado no banco,
    //   do outro o texto que o paciente vai realmente ler na tela

    return $labels[$status] ?? ucfirst($status);
    //   tenta achar a tradução de $status dentro do dicionário. Se esse status não
    //   estiver na lista, o "??" cai no plano B: ucfirst() deixa só a primeira letra
    //   maiúscula do texto original, em vez de quebrar o site
}

function current_date_value(): string
{
    return (new DateTime())->format('Y-m-d');
    //   "new DateTime()" SEM nada dentro pega a data/hora ATUAL. format('Y-m-d') devolve
    //   só a data, no formato que o banco espera (ano-mês-dia)
}

function now_sql(): string
{
    return (new DateTime())->format('Y-m-d H:i:s');
    //   mesma ideia, mas com hora/minuto/segundo, formato completo de data/hora,
    //   igual às colunas TIMESTAMP/DATETIME do MySQL esperam
}

function app_version(): string
{
    return 'paciente-v.1.0';
    //   só devolve um texto fixo, sem lógica nenhuma, uma etiqueta de versão pro rodapé
}

function abort_forbidden(): void
{
    http_response_code(403);
    //   define o código HTTP 403, que quer dizer "Proibido", usado quando alguém está
    //   logado, mas não tem permissão pra fazer aquela ação

    echo 'Acesso negado.';
    //   imprime uma mensagem simples na tela

    exit;
    //   para a execução, ninguém vê mais nada da página depois disso
}