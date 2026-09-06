<?php

return [
    'app_name' => 'Vital Clinic',
    'timezone' => 'America/Sao_Paulo',
    // Conexão com o banco de dados MySQL "vitalclinic" (veja vitalclinic_schema.sql).
    // Pode ser sobrescrita por variáveis de ambiente ou diretamente aqui.
    'db' => [
        'host'    => getenv('VCTCC_DB_HOST') ?: '127.0.0.1',
        'port'    => (int) (getenv('VCTCC_DB_PORT') ?: 3306),
        'name'    => getenv('VCTCC_DB_NAME') ?: 'vitalclinic',
        'user'    => getenv('VCTCC_DB_USER') ?: 'root',
        'pass'    => getenv('VCTCC_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'data' => [
        // demo: modo legado, usa data/demo-state.json (mantido apenas como referência).
        // api: as operações deverão ser encaminhadas para a API central.
        // mysql: modo atual — o site lê e grava diretamente no banco MySQL.
        'mode' => getenv('VCTCC_DATA_MODE') ?: 'mysql',
        'api_base_url' => rtrim(getenv('VCTCC_API_URL') ?: '', '/'),
        'api_token' => getenv('VCTCC_API_TOKEN') ?: '',
        'timeout' => (int) (getenv('VCTCC_API_TIMEOUT') ?: 8),
    ],
    'brand' => [
        'logo' => 'assets/brand/vital-clinic-logo.svg',
        'mark' => 'assets/brand/vital-clinic-mark.svg',
    ],
    'rules' => [
        'cancel_before_hours' => 24,
        'reschedule_before_hours' => 24,
        'booking_max_days' => 60,
        // Regras do fluxo "Esqueci minha senha" (baseado em pergunta de
        // segurança — não há mais envio de código por e-mail).
        // Máximo de tentativas erradas na pergunta de segurança antes de
        // bloquear essa etapa na sessão atual (o usuário reinicia
        // informando o e-mail novamente).
        'security_answer_max_attempts' => 5,
        // Classificação de movimentação mensal (tela de Relatórios):
        // com MENOS que "movement_low" consultas no mês, a clínica é
        // classificada como "Baixa movimentação"; com "movement_high"
        // ou mais, como "Alta movimentação"; entre os dois valores,
        // "Boa movimentação". Ajuste esses números conforme o porte
        // real da sua clínica.
        'movement_low' => 40,
        'movement_high' => 120,
    ],
    // Lista fixa de perguntas de segurança que o usuário pode escolher no
    // seu perfil. Guardamos o texto exato (não apenas uma chave) na coluna
    // users.security_question, então alterar os textos aqui não afeta
    // quem já cadastrou uma pergunta.
    'security_questions' => [
        'Qual o nome do seu primeiro animal de estimação?',
        'Qual o nome de solteira da sua mãe?',
        'Qual foi o nome da sua primeira escola?',
        'Qual é a sua cidade natal?',
        'Qual foi o modelo do seu primeiro carro?',
        'Qual o nome do seu melhor amigo de infância?',
    ],
    // Envio de e-mail (código de recuperação de senha, etc.)
    'mail' => [
        'from_address' => getenv('VCTCC_MAIL_FROM') ?: 'no-reply@vitalclinic.local',
        'from_name' => getenv('VCTCC_MAIL_FROM_NAME') ?: 'Vital Clinic',
        // "smtp" = envia de verdade via um servidor SMTP (Gmail, SendGrid,
        //          Mailtrap, hospedagem, etc.) — recomendado para produção.
        // "mail" = usa a função mail() nativa do PHP (requer MTA já
        //          configurado no servidor; não funciona com `php -S`).
        // "log"  = não envia nada de verdade; grava em
        //          storage/mail_outbox.log (padrão, ideal para dev/teste).
        'transport' => getenv('VCTCC_MAIL_TRANSPORT') ?: 'log',
        'smtp_host' => getenv('VCTCC_MAIL_SMTP_HOST') ?: '',
        'smtp_port' => (int) (getenv('VCTCC_MAIL_SMTP_PORT') ?: 587),
        'smtp_user' => getenv('VCTCC_MAIL_SMTP_USER') ?: '',
        'smtp_pass' => getenv('VCTCC_MAIL_SMTP_PASS') ?: '',
        // 'tls' (porta 587, o mais comum), 'ssl' (porta 465) ou 'none'
        'smtp_encryption' => getenv('VCTCC_MAIL_SMTP_ENCRYPTION') ?: 'tls',
        'smtp_timeout' => (int) (getenv('VCTCC_MAIL_SMTP_TIMEOUT') ?: 10),
    ],
];