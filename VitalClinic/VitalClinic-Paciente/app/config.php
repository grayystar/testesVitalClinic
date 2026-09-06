<?php
// Toda página PHP começa assim. É o jeito de avisar "a partir 
// daqui é código PHP, não é HTML puro"

// return [ ... ]; isso é o coração do arquivo. Em PHP, um arquivo
// pode simplesmente "devolver" um valor quando outro arquivo pedir 
// pra abri-lo (com require, que a gente vai usar depois). Aqui, o 
// valor devolvido é um array associativo, como uma caixa organizada 
// com etiquetas: em vez de caixa[0], caixa[1], você acessa por nome, 
// tipo caixa['app_name']. Isso deixa muito mais fácil de ler do que decorar números de posição.
return [
    // A etiqueta app_name guarda o nome do site. A gente vai usar 
    // isso depois pra mostrar o título da página, por exemplo.
    'app_name' => 'Vital Clinic — Paciente',
    // Define o fuso horário. Isso importa porque, sem avisar isso 
    // pro PHP, ele às vezes assume o fuso de Greenwich (Inglaterra), 
    // e aí toda hora de consulta apareceria errada.
    'timezone' => 'America/Sao_Paulo',

    // É um array dentro do array (chamamos de array aninhado). Ele 
    // guarda só as informações de conexão com o banco
    'db' => [
        // Aqui tem duas coisas novas. getenv('VCTCC_DB_HOST') tenta 
        // ler uma variável de ambiente chamada VCTCC_DB_HOST, é tipo 
        // uma "configuração secreta" guardada fora do código, no próprio 
        // computador/servidor, em vez de escrita direto no arquivo. Fazemos 
        // assim por segurança: se você um dia subir esse código pro GitHub, 
        // a senha do banco não vai junto, exposta pra qualquer um ver. O ?: 
        // depois é o "operador Elvis", parece estranho, mas é simples: significa 
        // "se o que está à esquerda for vazio/falso, usa o valor da direita". 
        // Ou seja: "tenta pegar a variável de ambiente; se não existir, usa 
        // 127.0.0.1 como padrão" (que é o endereço do seu próprio computador,
        //  é onde o MySQL do XAMPP roda).
        'host'    => getenv('VCTCC_DB_HOST') ?: '127.0.0.1',
        // Mesma lógica, mas pra porta de conexão (3306 é a porta padrão do MySQL). 
        // O (int) na frente converte o texto pra número inteiro, porque getenv() 
        // sempre devolve texto, e a conexão espera um número.
        'port'    => (int) (getenv('VCTCC_DB_PORT') ?: 3306),
        // O nome do banco.
        'name'    => getenv('VCTCC_DB_NAME') ?: 'vitalclinic',
        // 'user' e 'pass' são o usuário e senha do MySQL. Como uso o XAMPP padrão, 
        // geralmente é usuário root e senha vazia, por isso os padrões são esses.
        'user'    => getenv('VCTCC_DB_USER') ?: 'root',
        'pass'    => getenv('VCTCC_DB_PASS') ?: '',
        // define a "tabela de caracteres" usada. utf8mb4 é o mais completo hoje em
        // dia (aceita acento, emoji, tudo). Sem isso configurado direito, nomes com 
        // acento podem salvar bugado no banco.
        'charset' => 'utf8mb4',
    ],

    // Guarda as regras de negócio do site, coisas como "só pode cancelar consulta com 
    // 24 horas de antecedência" (cancel_before_hours), "só pode remarcar com 24h" 
    // (reschedule_before_hours) e "só pode agendar até 60 dias no futuro" (booking_max_days). 
    // Colocar esses números aqui, em vez de espalhados pelo código, significa que se um dia a 
    // clínica quiser mudar a regra pra 48h, você muda em UM lugar só, não precisa caçar em vários arquivos.
    'rules' => [
        'cancel_before_hours' => 24,
        'reschedule_before_hours' => 24,
        'booking_max_days' => 60,
    ],
];