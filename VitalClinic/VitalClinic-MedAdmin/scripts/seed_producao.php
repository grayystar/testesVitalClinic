<?php

declare(strict_types=1);

/**
 * =====================================================================
 * VITAL CLINIC — Seed de simulação de produção
 * =====================================================================
 *
 * Popula o banco `vitalclinic` com um volume de dados fictícios, mas
 * verossímeis, para simular o uso diário real de uma rede com várias
 * clínicas: administradores e médicos (perfis ADM=1 / Médico=0, via
 * coluna gerada `users.is_admin`), dezenas de pacientes e um histórico
 * amplo de consultas em diferentes status (pendente, confirmada,
 * concluída, cancelada, falta), com datas passadas e futuras em
 * relação ao momento em que o script é executado.
 *
 * Este script é ADITIVO: nunca apaga nem altera dados existentes. Ele
 * pode ser executado em um banco já criado pelo `vitalclinic_schema.sql`
 * (com ou sem o `seed_data_extra.sql` aplicado).
 *
 * Todos os registros gerados usam e-mail terminado em "@seed3.local" e
 * CNPJ com prefixo "98." para ficarem fáceis de identificar e remover
 * depois, caso necessário:
 *
 *   DELETE FROM users   WHERE email LIKE '%@seed3.local';
 *   DELETE FROM clinics WHERE cnpj  LIKE '98.%';
 *   -- os registros dependentes (doctors, appointments, payments,
 *   -- medical_records, notifications, appointment_slots) são apagados
 *   -- automaticamente por ON DELETE CASCADE.
 *
 * Senha de login de todos os usuários gerados: password
 *
 * -----------------------------------------------------------------
 * COMO RODAR (a partir da raiz do projeto, com Apache/MySQL já
 * ativos no XAMPP e as credenciais configuradas em app/config.php):
 *
 *   Windows (prompt de comando, na pasta do projeto):
 *     C:\xampp\php\php.exe scripts\seed_producao.php
 *
 *   Linux/Mac:
 *     php scripts/seed_producao.php
 *
 * Parâmetros opcionais (todos têm um valor padrão já pensado para
 * simular pelo menos 5 clínicas em operação real):
 *
 *   php scripts/seed_producao.php --clinics=6 --patients=150 --appointments=500
 *
 *   --clinics=N       Número de clínicas novas a criar (padrão: 6)
 *   --patients=N      Número de pacientes novos a criar (padrão: 150)
 *   --appointments=N  Número de consultas a gerar (padrão: 500)
 *   --past-days=N     Janela de dias no passado para consultas (padrão: 120)
 *   --future-days=N   Janela de dias no futuro para consultas (padrão: 45)
 * =====================================================================
 */

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/db.php';

/* ---------------------------------------------------------------------
 * 0. Parâmetros de linha de comando
 * ------------------------------------------------------------------- */

function cli_option(array $argv, string $name, int $default): int
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return max(0, (int) substr($arg, strlen("--{$name}=")));
        }
    }
    return $default;
}

$TOTAL_CLINICS      = cli_option($argv, 'clinics', 6);
$TOTAL_PATIENTS     = cli_option($argv, 'patients', 150);
$TOTAL_APPOINTMENTS = cli_option($argv, 'appointments', 500);
$PAST_DAYS          = cli_option($argv, 'past-days', 120);
$FUTURE_DAYS        = cli_option($argv, 'future-days', 45);

$EMAIL_DOMAIN = 'seed3.local';
$CNPJ_PREFIX  = '98';
$DEFAULT_PASSWORD_HASH = password_hash('password', PASSWORD_DEFAULT);

echo "=====================================================================\n";
echo " VitalClinic — Seed de simulação de produção\n";
echo "=====================================================================\n";
echo "Clínicas novas ...... {$TOTAL_CLINICS}\n";
echo "Pacientes novos ..... {$TOTAL_PATIENTS}\n";
echo "Consultas ........... {$TOTAL_APPOINTMENTS}\n";
echo "Janela passado ...... {$PAST_DAYS} dias\n";
echo "Janela futuro ........ {$FUTURE_DAYS} dias\n";
echo "---------------------------------------------------------------------\n";

/* ---------------------------------------------------------------------
 * 1. "Bancos" de dados fictícios (nomes, endereços, textos clínicos)
 * ------------------------------------------------------------------- */

const FIRST_NAMES_F = [
    'Maria', 'Ana', 'Francisca', 'Antônia', 'Adriana', 'Juliana', 'Márcia',
    'Fernanda', 'Patrícia', 'Aline', 'Sandra', 'Camila', 'Amanda', 'Bruna',
    'Jéssica', 'Leticia', 'Larissa', 'Vanessa', 'Renata', 'Beatriz',
    'Carla', 'Debora', 'Elaine', 'Fabiana', 'Gabriela', 'Helena', 'Isabela',
    'Luciana', 'Mariana', 'Natalia', 'Priscila', 'Silvana', 'Talita',
];
const FIRST_NAMES_M = [
    'José', 'João', 'Antônio', 'Francisco', 'Carlos', 'Paulo', 'Pedro',
    'Lucas', 'Marcos', 'Luiz', 'Gustavo', 'Rafael', 'Daniel', 'Marcelo',
    'Bruno', 'Eduardo', 'Felipe', 'Rodrigo', 'Igor', 'Otávio', 'Sérgio',
    'Roberto', 'Cristiano', 'Matheus', 'Thiago', 'Vinícius', 'André',
    'Fábio', 'Gabriel', 'Henrique', 'Leonardo', 'Ricardo', 'Vitor',
];
const LAST_NAMES = [
    'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira',
    'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins',
    'Carvalho', 'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira',
    'Barbosa', 'Rocha', 'Dias', 'Monteiro', 'Cardoso', 'Reis', 'Araújo',
    'Castro', 'Andrade', 'Nascimento', 'Machado', 'Marques', 'Nunes',
    'Freitas', 'Pinto', 'Teixeira', 'Ramos', 'Correia', 'Cavalcanti',
];
const STREET_TYPES = ['Rua', 'Avenida', 'Alameda', 'Travessa'];
const STREET_NAMES = [
    'das Flores', 'das Orquídeas', 'Rio Branco', 'Paulista', 'Brasil',
    'Tiradentes', 'das Nações', 'dos Ipês', 'Sete de Setembro',
    'XV de Novembro', 'das Acácias', 'Barão do Rio Branco', 'Getúlio Vargas',
    'Marechal Deodoro', 'dos Andradas', 'das Palmeiras',
];
const NEIGHBORHOODS = [
    'Centro', 'Jardim América', 'Vila Mariana', 'Boa Vista', 'Vila São José',
    'Jardim Europa', 'Parque Industrial', 'Cidade Nova', 'Santa Mônica',
    'Bela Vista', 'Novo Horizonte',
];
const CITIES_UF = [
    ['Campinas', 'SP'], ['São Paulo', 'SP'], ['Sorocaba', 'SP'],
    ['Ribeirão Preto', 'SP'], ['Santos', 'SP'], ['São José dos Campos', 'SP'],
];
const CLINIC_NAME_PREFIXES = [
    'Clínica', 'Instituto', 'Centro Médico', 'Espaço Saúde', 'Policlínica',
];
const CLINIC_NAME_SUFFIXES = [
    'Vitalità', 'Bem Viver', 'Nova Saúde', 'Renascer', 'Vida Plena',
    'Esperança', 'Harmonia', 'São Lucas', 'Santa Clara', 'Horizonte',
    'Primavera', 'Aurora',
];

/**
 * Textos clínicos (sintomas / diagnóstico / prescrição) agrupados por
 * especialidade, para deixar os prontuários minimamente coerentes com
 * quem atendeu a consulta. Uma entrada genérica cobre especialidades
 * sem um conjunto específico.
 */
const CLINICAL_NOTES = [
    'Cardiologia' => [
        ['Dor no peito ao esforço e falta de ar leve.', 'Hipertensão arterial leve.', 'Ajuste de anti-hipertensivo e redução de sódio na dieta.'],
        ['Palpitações ocasionais.', 'Arritmia sinusal benigna.', 'Solicitado holter de 24h para acompanhamento.'],
        ['Cansaço ao subir escadas.', 'Pré-hipertensão.', 'Orientação de atividade física leve e retorno em 60 dias.'],
    ],
    'Dermatologia' => [
        ['Manchas avermelhadas e coceira no braço.', 'Dermatite de contato.', 'Pomada corticoide tópica por 7 dias.'],
        ['Lesão de pele com crescimento recente.', 'Nevo displásico a investigar.', 'Encaminhado para biópsia.'],
        ['Acne persistente na face.', 'Acne vulgar moderada.', 'Tratamento tópico com retinoide e higiene facial adequada.'],
    ],
    'Pediatria' => [
        ['Febre baixa e tosse seca há 2 dias.', 'Infecção viral de vias aéreas superiores.', 'Sintomáticos e hidratação; retorno se piora.'],
        ['Baixo ganho de peso no último trimestre.', 'Acompanhamento de curva de crescimento.', 'Ajuste na dieta e retorno em 30 dias.'],
        ['Coceira no couro cabeludo.', 'Pediculose.', 'Xampu específico e orientação de higiene.'],
    ],
    'Ortopedia' => [
        ['Dor lombar após esforço físico.', 'Lombalgia mecânica.', 'Anti-inflamatório por 5 dias e fisioterapia.'],
        ['Dor no joelho ao caminhar.', 'Condropatia patelar leve.', 'Fortalecimento muscular com fisioterapia.'],
        ['Torção no tornozelo durante atividade esportiva.', 'Entorse de tornozelo grau I.', 'Repouso, gelo local e uso de tornozeleira elástica.'],
    ],
    'Clínico geral' => [
        ['Dor de cabeça recorrente e cansaço.', 'Cefaleia tensional.', 'Analgésico conforme necessidade e melhora do sono.'],
        ['Check-up de rotina, sem queixas.', 'Paciente hígido.', 'Manter hábitos saudáveis; retorno em 12 meses.'],
        ['Mal-estar geral e febre baixa.', 'Síndrome gripal.', 'Sintomáticos, repouso e hidratação.'],
    ],
];
const FOLLOW_UPS = [
    'Retorno em 15 dias.', 'Retorno em 30 dias.', 'Retorno em 60 dias.',
    'Solicitar exames laboratoriais de rotina.', 'Retorno se sintomas persistirem.',
    'Encaminhamento para especialista, se necessário.',
];
const CANCEL_REASONS = [
    'Paciente reagendará para outra data.', 'Imprevisto pessoal do paciente.',
    'Conflito de horário com o médico.', 'Paciente cancelou por telefone.',
    'Solicitação de reagendamento pela clínica.',
];
const NO_SHOW_NOTES = [
    'Paciente não compareceu e não avisou a clínica.',
    'Tentativa de contato sem sucesso; paciente faltou.',
];

/* ---------------------------------------------------------------------
 * 2. Helpers de geração de dados fictícios
 * ------------------------------------------------------------------- */

function pick(array $items)
{
    return $items[array_rand($items)];
}

function between(int $min, int $max): int
{
    return random_int($min, $max);
}

function fake_full_name(): array
{
    $isFemale = between(0, 1) === 0;
    $first = $isFemale ? pick(FIRST_NAMES_F) : pick(FIRST_NAMES_M);
    $last1 = pick(LAST_NAMES);
    $last2 = pick(LAST_NAMES);
    while ($last2 === $last1) {
        $last2 = pick(LAST_NAMES);
    }
    return [$isFemale, trim("{$first} {$last1} {$last2}")];
}

function fake_email(string $fullName, string $domain): string
{
    static $used = [];
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', remove_accents($fullName)));
    $slug = trim($slug, '.');
    do {
        $candidate = $slug . '.' . between(1000, 9999) . '@' . $domain;
    } while (isset($used[$candidate]));
    $used[$candidate] = true;
    return $candidate;
}

function remove_accents(string $text): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return $converted !== false ? $converted : $text;
}

function fake_phone(): string
{
    return sprintf('(%02d) 9%04d-%04d', pick([11, 13, 15, 19]), between(0, 9999), between(0, 9999));
}

function fake_address(): string
{
    $type = pick(STREET_TYPES);
    $name = pick(STREET_NAMES);
    $number = between(10, 2500);
    $neighborhood = pick(NEIGHBORHOODS);
    return "{$type} {$name}, {$number} - {$neighborhood}";
}

function fake_birth_date(int $minAge, int $maxAge): string
{
    $age = between($minAge, $maxAge);
    $date = (new DateTime())->modify("-{$age} years")->modify('-' . between(0, 364) . ' days');
    return $date->format('Y-m-d');
}

/** Gera um CPF numericamente válido (dígitos verificadores corretos). */
function fake_cpf(): string
{
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[] = between(0, 9);
    }
    $d1 = cpf_check_digit($n);
    $n[] = $d1;
    $d2 = cpf_check_digit($n);
    $n[] = $d2;
    return sprintf('%d%d%d.%d%d%d.%d%d%d-%d%d', ...$n);
}

function cpf_check_digit(array $digits): int
{
    $sum = 0;
    $weight = count($digits) + 1;
    foreach ($digits as $d) {
        $sum += $d * $weight--;
    }
    $mod = ($sum * 10) % 11;
    return $mod === 10 ? 0 : $mod;
}

/** Gera um CNPJ numericamente válido, sempre com o prefixo informado. */
function fake_cnpj(string $prefix): string
{
    // 8 dígitos identificam a empresa (2 do prefixo fixo + 6 aleatórios),
    // seguidos de 4 dígitos da filial (0001 = matriz).
    $companyRest = [];
    for ($i = 0; $i < 6; $i++) {
        $companyRest[] = between(0, 9);
    }
    $branch = [0, 0, 0, 1];
    $n = array_merge([(int) $prefix[0], (int) $prefix[1]], $companyRest, $branch);

    $d1 = cnpj_check_digit($n, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $n[] = $d1;
    $d2 = cnpj_check_digit($n, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $n[] = $d2;

    return sprintf('%d%d.%d%d%d.%d%d%d/%d%d%d%d-%d%d', ...$n);
}

function cnpj_check_digit(array $digits, array $weights): int
{
    $sum = 0;
    foreach ($digits as $i => $d) {
        $sum += $d * $weights[$i];
    }
    $mod = $sum % 11;
    return $mod < 2 ? 0 : 11 - $mod;
}

/* ---------------------------------------------------------------------
 * 3. Execução — tudo dentro de uma única transação
 * ------------------------------------------------------------------- */

$stats = [
    'clinics' => 0, 'admins' => 0, 'doctors' => 0, 'patients' => 0,
    'appointments' => 0, 'completed' => 0, 'cancelled' => 0,
    'no_show' => 0, 'pending' => 0, 'confirmed' => 0,
];

db_transaction(function (PDO $pdo) use (
    $TOTAL_CLINICS, $TOTAL_PATIENTS, $TOTAL_APPOINTMENTS,
    $PAST_DAYS, $FUTURE_DAYS, $EMAIL_DOMAIN, $CNPJ_PREFIX,
    $DEFAULT_PASSWORD_HASH, &$stats
): void {

    // --- Especialidades já existentes no banco (não recriamos) --------
    $specialties = $pdo->query('SELECT id, name FROM specialties')->fetchAll();
    if (!$specialties) {
        throw new RuntimeException('Nenhuma especialidade encontrada. Rode vitalclinic_schema.sql antes deste script.');
    }

    // --- 1. Clínicas ----------------------------------------------------
    $clinicIds = [];
    for ($i = 0; $i < $TOTAL_CLINICS; $i++) {
        $name = pick(CLINIC_NAME_PREFIXES) . ' ' . pick(CLINIC_NAME_SUFFIXES);
        [$city, $uf] = pick(CITIES_UF);
        $stmt = $pdo->prepare(
            'INSERT INTO clinics (name, cnpj, address, phone, whatsapp, email) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $cnpj = fake_cnpj($CNPJ_PREFIX);
        $phone = fake_phone();
        $slugDomain = strtolower(preg_replace('/[^a-z0-9]+/i', '', remove_accents($name)));
        $stmt->execute([
            "{$name} — {$city}/{$uf}",
            $cnpj,
            fake_address() . ", {$city}/{$uf}",
            $phone,
            '55' . preg_replace('/\D/', '', $phone),
            "contato@{$slugDomain}." . $EMAIL_DOMAIN,
        ]);
        $clinicIds[] = (int) $pdo->lastInsertId();
        $stats['clinics']++;
    }

    // --- 2. Administradores e médicos por clínica -----------------------
    $doctorIds = []; // id em `doctors`
    $doctorUserIds = []; // id em `users` (para notificações)
    $doctorSpecialty = []; // doctor_id => nome da especialidade
    $doctorClinic = []; // doctor_id => clinic_id
    $doctorDuration = []; // doctor_id => duração padrão da consulta

    foreach ($clinicIds as $clinicId) {
        // 1 a 2 administradores por clínica
        $adminCount = between(1, 2);
        for ($i = 0; $i < $adminCount; $i++) {
            [, $name] = fake_full_name();
            $pdo->prepare(
                'INSERT INTO users (clinic_id, name, email, password_hash, role, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $clinicId, $name, fake_email($name, $EMAIL_DOMAIN),
                $DEFAULT_PASSWORD_HASH, 'admin', fake_phone(), 'active',
            ]);
            $stats['admins']++;
        }

        // 2 a 4 médicos por clínica, com especialidades variadas
        $doctorCount = between(2, 4);
        for ($i = 0; $i < $doctorCount; $i++) {
            [$isFemale, $name] = fake_full_name();
            $title = $isFemale ? 'Dra.' : 'Dr.';
            $fullTitle = "{$title} {$name}";
            $specialty = pick($specialties);

            $pdo->prepare(
                'INSERT INTO users (clinic_id, name, email, password_hash, role, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $clinicId, $fullTitle, fake_email($name, $EMAIL_DOMAIN),
                $DEFAULT_PASSWORD_HASH, 'doctor', fake_phone(), 'active',
            ]);
            $doctorUserId = (int) $pdo->lastInsertId();

            $crm = null;
            do {
                $crm = 'CRM-SP ' . between(100000, 999999);
                $exists = $pdo->prepare('SELECT 1 FROM doctors WHERE crm = ?');
                $exists->execute([$crm]);
            } while ($exists->fetchColumn());

            $duration = pick([20, 30, 40]);
            $pdo->prepare(
                'INSERT INTO doctors (user_id, clinic_id, specialty_id, crm, bio, appointment_duration, active) VALUES (?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $doctorUserId, $clinicId, (int) $specialty['id'], $crm,
                'Atendimento em ' . $specialty['name'] . ', com foco em cuidado contínuo do paciente.',
                $duration,
            ]);
            $doctorId = (int) $pdo->lastInsertId();

            // Agenda semanal (2 a 3 dias fixos por semana)
            $weekdays = array_rand(array_flip([1, 2, 3, 4, 5]), between(2, 3));
            $weekdays = is_array($weekdays) ? $weekdays : [$weekdays];
            $shift = pick([['08:00:00', '12:00:00'], ['13:00:00', '18:00:00']]);
            foreach ($weekdays as $weekday) {
                $pdo->prepare(
                    'INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, active) VALUES (?, ?, ?, ?, 1)'
                )->execute([$doctorId, $weekday, $shift[0], $shift[1]]);
            }

            $doctorIds[] = $doctorId;
            $doctorUserIds[$doctorId] = $doctorUserId;
            $doctorSpecialty[$doctorId] = $specialty['name'];
            $doctorClinic[$doctorId] = $clinicId;
            $doctorDuration[$doctorId] = $duration;
            $stats['doctors']++;
        }
    }

    // --- 3. Pacientes -----------------------------------------------------
    $patientIds = [];
    for ($i = 0; $i < $TOTAL_PATIENTS; $i++) {
        [, $name] = fake_full_name();
        $clinicId = pick($clinicIds);
        $pdo->prepare(
            'INSERT INTO users (clinic_id, name, email, password_hash, role, phone, document, birth_date, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $clinicId, $name, fake_email($name, $EMAIL_DOMAIN), $DEFAULT_PASSWORD_HASH,
            'patient', fake_phone(), fake_cpf(), fake_birth_date(1, 90), fake_address(), 'active',
        ]);
        $patientId = (int) $pdo->lastInsertId();
        $patientIds[] = $patientId;
        $stats['patients']++;
    }

    // --- 4. Consultas (histórico amplo: passado e futuro) -----------------
    $now = new DateTime();

    for ($i = 0; $i < $TOTAL_APPOINTMENTS; $i++) {
        $isPast = between(0, 99) < 70; // 70% passado, 30% futuro
        $dayOffset = $isPast ? -between(1, $PAST_DAYS) : between(1, $FUTURE_DAYS);
        $slotDate = (clone $now)->modify("{$dayOffset} days");

        // Evita fins de semana, simulando expediente comercial normal
        while ((int) $slotDate->format('N') >= 6) {
            $slotDate->modify($dayOffset < 0 ? '-1 day' : '+1 day');
        }

        $hour = pick([8, 9, 10, 11, 13, 14, 15, 16, 17]);
        $minute = pick([0, 20, 30, 40]);
        $slotDate->setTime($hour, $minute, 0);

        $doctorId = pick($doctorIds);
        $duration = $doctorDuration[$doctorId];
        $slotEnd = (clone $slotDate)->modify("+{$duration} minutes");
        $clinicId = $doctorClinic[$doctorId];
        $specialtyName = $doctorSpecialty[$doctorId];

        // Paciente: qualquer paciente da rede pode ser atendido em
        // qualquer clínica/médico, como acontece numa rede real onde o
        // paciente escolhe livremente onde marcar a consulta.
        $patientId = pick($patientIds);

        // Tenta inserir o slot; em caso de colisão rara de horário
        // (mesmo médico + mesmo horário), tenta outro horário.
        $slotId = null;
        for ($attempt = 0; $attempt < 5 && $slotId === null; $attempt++) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO appointment_slots (doctor_id, slot_start, slot_end, status) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$doctorId, $slotDate->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s'), 'booked']);
                $slotId = (int) $pdo->lastInsertId();
            } catch (PDOException $e) {
                // Provável violação da UNIQUE (doctor_id, slot_start): tenta 15 min depois.
                $slotDate->modify('+15 minutes');
                $slotEnd = (clone $slotDate)->modify("+{$duration} minutes");
            }
        }
        if ($slotId === null) {
            continue; // não conseguiu um horário livre nesta rodada, pula
        }

        // Distribuição de status: consultas passadas tendem a estar
        // concluídas; consultas futuras ficam pendentes/confirmadas.
        if ($isPast) {
            $roll = between(0, 99);
            $status = $roll < 72 ? 'completed' : ($roll < 90 ? 'cancelled' : 'no_show');
        } else {
            $status = between(0, 1) === 0 ? 'pending' : 'confirmed';
        }
        $modality = between(0, 4) === 0 ? 'teleconsulta' : 'presencial';

        $notes = null;
        $cancelReason = null;
        $confirmedAt = null;
        $cancelledAt = null;
        $completedAt = null;

        switch ($status) {
            case 'completed':
                $notes = 'Consulta realizada normalmente.';
                $confirmedAt = (clone $slotDate)->modify('-' . between(1, 3) . ' days')->format('Y-m-d H:i:s');
                $completedAt = $slotDate->format('Y-m-d H:i:s');
                break;
            case 'cancelled':
                $cancelReason = pick(CANCEL_REASONS);
                $cancelledAt = (clone $slotDate)->modify('-' . between(1, 5) . ' days')->format('Y-m-d H:i:s');
                break;
            case 'no_show':
                $notes = pick(NO_SHOW_NOTES);
                $confirmedAt = (clone $slotDate)->modify('-2 days')->format('Y-m-d H:i:s');
                break;
            case 'confirmed':
                $notes = 'Consulta confirmada pelo paciente.';
                $confirmedAt = (clone $now)->modify('-' . between(0, 3) . ' days')->format('Y-m-d H:i:s');
                break;
            case 'pending':
                $notes = 'Consulta agendada, aguardando confirmação.';
                break;
        }

        $pdo->prepare(
            'INSERT INTO appointments (slot_id, patient_id, doctor_id, clinic_id, specialty_id, status, modality, notes, cancel_reason, confirmed_at, cancelled_at, completed_at)
             VALUES (?, ?, ?, ?, (SELECT id FROM specialties WHERE name = ? LIMIT 1), ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $slotId, $patientId, $doctorId, $clinicId, $specialtyName,
            $status, $modality, $notes, $cancelReason, $confirmedAt, $cancelledAt, $completedAt,
        ]);
        $appointmentId = (int) $pdo->lastInsertId();

        // Valor da consulta: varia por especialidade, dentro de uma faixa plausível
        $baseAmount = [
            'Clínico geral' => [150, 220],
            'Cardiologia' => [280, 420],
            'Dermatologia' => [220, 350],
            'Pediatria' => [180, 260],
            'Ortopedia' => [250, 400],
        ][$specialtyName] ?? [150, 300];
        $amount = between($baseAmount[0], $baseAmount[1]);

        if ($status === 'completed') {
            $method = pick(['pix', 'card', 'cash', 'health_plan']);
            $pdo->prepare(
                'INSERT INTO payments (appointment_id, patient_id, clinic_id, amount, method, status, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$appointmentId, $patientId, $clinicId, $amount, $method, 'paid', $completedAt]);

            // Prontuário
            [$symptoms, $diagnosis, $prescription] = pick(CLINICAL_NOTES[$specialtyName] ?? CLINICAL_NOTES['Clínico geral']);
            $pdo->prepare(
                'INSERT INTO medical_records (appointment_id, patient_id, doctor_id, created_by, weight, height, temperature, heart_rate, blood_pressure, symptoms, diagnosis, prescription, follow_up)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $appointmentId, $patientId, $doctorId, $doctorUserIds[$doctorId],
                round(between(500, 1100) / 10, 1), // 50.0 – 110.0 kg
                round(between(1400, 1950) / 10, 1), // 140.0 – 195.0 cm
                round(between(360, 378) / 10, 1),   // 36.0 – 37.8 °C
                between(58, 100),
                between(10, 14) . '0/' . between(6, 9) . '0 mmHg',
                $symptoms, $diagnosis, $prescription, pick(FOLLOW_UPS),
            ]);
        } elseif ($status === 'cancelled') {
            $pdo->prepare(
                'INSERT INTO payments (appointment_id, patient_id, clinic_id, amount, method, status, paid_at) VALUES (?, ?, ?, ?, NULL, ?, NULL)'
            )->execute([$appointmentId, $patientId, $clinicId, $amount, 'cancelled']);
        } else {
            $pdo->prepare(
                'INSERT INTO payments (appointment_id, patient_id, clinic_id, amount, method, status, paid_at) VALUES (?, ?, ?, ?, NULL, ?, NULL)'
            )->execute([$appointmentId, $patientId, $clinicId, $amount, 'pending']);
        }

        // Notificações (paciente + médico), coerentes com o status final
        $doctorUserId = $doctorUserIds[$doctorId];
        $formattedDate = $slotDate->format('d/m/Y \à\s H:i');
        $notifDefs = [
            'completed' => ['sent', 'Consulta realizada', "Consulta de {$formattedDate} concluída com sucesso."],
            'cancelled' => ['sent', 'Consulta cancelada', "Sua consulta de {$formattedDate} foi cancelada."],
            'no_show' => ['sent', 'Falta registrada', "Falta registrada na consulta de {$formattedDate}."],
            'confirmed' => ['sent', 'Consulta confirmada', "Sua consulta foi confirmada para {$formattedDate}."],
            'pending' => ['scheduled', 'Consulta pendente', "Sua consulta de {$formattedDate} aguarda confirmação."],
        ][$status];

        foreach ([$patientId, $doctorUserId] as $recipient) {
            $sendAt = $isPast || $status !== 'pending' ? $slotDate->format('Y-m-d H:i:s') : null;
            $pdo->prepare(
                'INSERT INTO notifications (user_id, appointment_id, type, title, message, status, send_at, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $recipient, $appointmentId, pick(['in_app', 'email', 'sms']),
                $notifDefs[1], $notifDefs[2], $notifDefs[0],
                $sendAt, $notifDefs[0] === 'sent' ? $sendAt : null,
            ]);
        }

        $stats['appointments']++;
        $stats[$status]++;

        if (($i + 1) % 50 === 0) {
            echo "  ... {$stats['appointments']} consultas geradas\n";
        }
    }
});

echo "---------------------------------------------------------------------\n";
echo "Concluído com sucesso!\n\n";
echo "Clínicas novas ........ {$stats['clinics']}\n";
echo "Administradores ....... {$stats['admins']}\n";
echo "Médicos ................ {$stats['doctors']}\n";
echo "Pacientes .............. {$stats['patients']}\n";
echo "Consultas totais ....... {$stats['appointments']}\n";
echo "  - concluídas ......... {$stats['completed']}\n";
echo "  - canceladas ......... {$stats['cancelled']}\n";
echo "  - faltas ............. {$stats['no_show']}\n";
echo "  - pendentes .......... {$stats['pending']}\n";
echo "  - confirmadas ........ {$stats['confirmed']}\n\n";
echo "Login de qualquer usuário gerado: e-mail dele + senha \"password\"\n";
echo "=====================================================================\n";
