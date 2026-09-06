<?php

/**
 * Repositório de dados — versão MySQL.
 *
 * Esta camada substitui o antigo armazenamento em JSON
 * (data/demo-state.json) por consultas reais ao banco "vitalclinic"
 * (ver vitalclinic_schema.sql), usando PDO (app/db.php).
 *
 * As assinaturas de todas as funções públicas foram mantidas idênticas
 * às da versão anterior, então nenhuma página em pages/ precisou ser
 * alterada — apenas a origem dos dados mudou.
 */

const REPOSITORY_TABLES = [
    'clinics', 'specialties', 'users', 'doctors', 'doctor_schedules',
    'schedule_blocks', 'appointment_slots', 'appointments',
    'medical_records', 'payments', 'notifications', 'password_resets',
];

function repo_assert_table(string $table): void
{
    if (!in_array($table, REPOSITORY_TABLES, true)) {
        throw new RuntimeException('Tabela desconhecida: ' . $table);
    }
}

/* ---------------------------------------------------------------------
 * Helpers genéricos de CRUD (usados pelas funções de negócio abaixo e,
 * por compatibilidade, também por app/auth.php).
 * ------------------------------------------------------------------- */

function repository_find(string $table, int $id): ?array
{
    repo_assert_table($table);
    $stmt = db()->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function repository_append(string $table, array $data): int
{
    repo_assert_table($table);
    unset($data['id']);
    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $columnList = implode('`, `', $columns);
    $stmt = db()->prepare("INSERT INTO `$table` (`$columnList`) VALUES ($placeholders)");
    $stmt->execute(array_values($data));
    return (int) db()->lastInsertId();
}

function repository_replace(string $table, int $id, array $data): void
{
    repo_assert_table($table);
    unset($data['id']);
    if (!$data) {
        return;
    }
    $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($data)));
    $stmt = db()->prepare("UPDATE `$table` SET $set WHERE id = ?");
    $stmt->execute([...array_values($data), $id]);
}

function repository_next_id(array $rows): int
{
    // Mantida apenas por compatibilidade histórica; o MySQL gera os IDs via
    // AUTO_INCREMENT (ver repository_append / db()->lastInsertId()).
    $ids = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows);
    return ($ids ? max($ids) : 0) + 1;
}

function repository_find_user(int $id): ?array
{
    return repository_find('users', $id);
}

function repository_find_doctor(int $id): ?array
{
    return repository_find('doctors', $id);
}

function repository_user_with_clinic(array $user): array
{
    $clinic = !empty($user['clinic_id']) ? repository_find('clinics', (int) $user['clinic_id']) : null;
    $user['clinic_name'] = $clinic['name'] ?? null;
    return $user;
}

function repository_doctor_row(array $doctor): array
{
    $user = repository_find_user((int) $doctor['user_id']) ?: [];
    $clinic = repository_find('clinics', (int) $doctor['clinic_id']) ?: [];
    $specialty = repository_find('specialties', (int) $doctor['specialty_id']) ?: [];
    return array_merge($doctor, [
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'clinic_name' => $clinic['name'] ?? '',
        'specialty_name' => $specialty['name'] ?? '',
    ]);
}

function repository_slot(int $id): ?array
{
    return repository_find('appointment_slots', $id);
}

function repository_find_appointment(int $id): ?array
{
    return repository_find('appointments', $id);
}

function repository_appointment_row(array $appointment): array
{
    $slot = repository_slot((int) $appointment['slot_id']) ?: [];
    $patient = repository_find_user((int) $appointment['patient_id']) ?: [];
    $doctor = repository_find_doctor((int) $appointment['doctor_id']) ?: [];
    $doctorUser = repository_find_user((int) ($doctor['user_id'] ?? 0)) ?: [];
    $clinic = repository_find('clinics', (int) $appointment['clinic_id']) ?: [];
    $specialty = repository_find('specialties', (int) $appointment['specialty_id']) ?: [];

    $stmt = db()->prepare('SELECT * FROM payments WHERE appointment_id = ? LIMIT 1');
    $stmt->execute([(int) $appointment['id']]);
    $payment = $stmt->fetch() ?: null;

    return array_merge($appointment, [
        'slot_start' => $slot['slot_start'] ?? null,
        'slot_end' => $slot['slot_end'] ?? null,
        'slot_status' => $slot['status'] ?? null,
        'patient_name' => $patient['name'] ?? '',
        'patient_email' => $patient['email'] ?? '',
        'patient_phone' => $patient['phone'] ?? '',
        'patient_document' => $patient['document'] ?? '',
        'patient_birth_date' => $patient['birth_date'] ?? null,
        'patient_address' => $patient['address'] ?? '',
        'doctor_name' => $doctorUser['name'] ?? '',
        'doctor_email' => $doctorUser['email'] ?? '',
        'doctor_phone' => $doctorUser['phone'] ?? '',
        'doctor_document' => $doctorUser['document'] ?? '',
        'doctor_address' => $doctorUser['address'] ?? '',
        'doctor_crm' => $doctor['crm'] ?? '',
        'clinic_name' => $clinic['name'] ?? '',
        'specialty_name' => $specialty['name'] ?? '',
        'payment_status' => $payment['status'] ?? 'pending',
        'amount' => $payment['amount'] ?? 250.00,
        'payment_method' => $payment['method'] ?? null,
        'paid_at' => $payment['paid_at'] ?? null,
    ]);
}

function repository_update_user(int $id, array $changes): void
{
    $changes['updated_at'] = now_sql();
    repository_replace('users', $id, $changes);
}

function ensure_runtime_schema(): void
{
    // Garante que a conexão com o MySQL está disponível e que a tabela
    // principal existe. Qualquer falha aqui é convertida em RuntimeException
    // e tratada por render_install_error() em index.php.
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
    } catch (PDOException $e) {
        throw new RuntimeException(
            'O banco de dados "vitalclinic" não está acessível ou não foi criado. ' .
            'Execute o script vitalclinic_schema.sql e confira as credenciais em app/config.php. ' .
            'Detalhe: ' . $e->getMessage()
        );
    }
}

/* ---------------------------------------------------------------------
 * Cadastros de apoio
 * ------------------------------------------------------------------- */

function clinics(): array
{
    return db()->query('SELECT * FROM clinics ORDER BY name')->fetchAll();
}

function specialties(): array
{
    return db()->query('SELECT * FROM specialties ORDER BY name')->fetchAll();
}

function active_doctors(array $filters = []): array
{
    $sql = 'SELECT d.*, u.name AS name, u.email AS email, u.phone AS phone,
                   c.name AS clinic_name, sp.name AS specialty_name
            FROM doctors d
            JOIN users u ON u.id = d.user_id
            JOIN clinics c ON c.id = d.clinic_id
            JOIN specialties sp ON sp.id = d.specialty_id
            WHERE d.active = 1 AND u.status = "active"';
    $params = [];

    if (!empty($filters['clinic_id'])) {
        $sql .= ' AND d.clinic_id = ?';
        $params[] = (int) $filters['clinic_id'];
    }
    if (!empty($filters['specialty_id'])) {
        $sql .= ' AND d.specialty_id = ?';
        $params[] = (int) $filters['specialty_id'];
    }
    if (!empty($filters['doctor_id'])) {
        $sql .= ' AND d.id = ?';
        $params[] = (int) $filters['doctor_id'];
    }
    if (!empty($filters['search'])) {
        // Busca por nome, CRM ou especialidade — cobre os três jeitos
        // mais comuns de alguém procurar um médico na lista.
        $sql .= ' AND (u.name LIKE ? OR d.crm LIKE ? OR sp.name LIKE ?)';
        $term = '%' . $filters['search'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= ' ORDER BY u.name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Lista todos os usuários "de sistema" (admin + doctor) para a tela de
 * Gestão de Acessos. Pacientes não entram aqui: eles não fazem login
 * no painel administrativo e não podem receber privilégio de ADM.
 */
function staff_users(): array
{
    $sql = 'SELECT u.id, u.name, u.email, u.role, u.is_admin, u.status,
                   d.crm AS crm, sp.name AS specialty_name
            FROM users u
            LEFT JOIN doctors d ON d.user_id = u.id
            LEFT JOIN specialties sp ON sp.id = d.specialty_id
            WHERE u.role IN ("admin", "doctor")
            ORDER BY u.role DESC, u.name';
    return db()->query($sql)->fetchAll();
}

function count_active_admins(): int
{
    $sql = 'SELECT COUNT(*) FROM users WHERE role = "admin" AND status = "active"';
    return (int) db()->query($sql)->fetchColumn();
}

/**
 * Regra de negócio da concessão/revogação de privilégio de ADM.
 *
 * - Só pode ser chamada por quem já é admin (garantido no controller
 *   via require_role('admin'), antes mesmo de chegar aqui).
 * - Só admite alternar entre 'admin' e 'doctor': paciente nunca vira
 *   ADM por essa rota, e um paciente nunca poderia ser encontrado por
 *   staff_users() de qualquer forma.
 * - Um admin não pode alterar a própria role (evita o próprio usuário
 *   se auto-rebaixar sem querer e travar o acesso à tela).
 * - Não é permitido remover o último admin ativo do sistema (evita
 *   ficar sem nenhum ADM capaz de reverter a alteração).
 */
function update_user_role(int $targetUserId, string $newRole, int $actingUserId): array
{
    if (!in_array($newRole, ['admin', 'doctor'], true)) {
        throw new RuntimeException('Perfil de acesso inválido.');
    }

    if ($targetUserId === $actingUserId) {
        throw new RuntimeException('Você não pode alterar o seu próprio nível de acesso.');
    }

    $target = repository_find_user($targetUserId);
    if (!$target || !in_array($target['role'], ['admin', 'doctor'], true)) {
        throw new RuntimeException('Usuário não encontrado.');
    }

    if ($target['role'] === $newRole) {
        return $target;
    }

    if ($target['role'] === 'admin' && $newRole === 'doctor' && count_active_admins() <= 1) {
        throw new RuntimeException('Não é possível remover o último administrador do sistema.');
    }

    if ($newRole === 'doctor' && !doctor_by_user($targetUserId)) {
        throw new RuntimeException('Este usuário não possui um cadastro de médico (CRM/especialidade) para voltar ao perfil de médico.');
    }

    repository_replace('users', $targetUserId, [
        'role' => $newRole,
        'updated_at' => now_sql(),
    ]);

    return repository_find_user($targetUserId) ?? $target;
}

function doctor_by_user(int $userId): ?array
{
    foreach (active_doctors() as $doctor) {
        if ((int) $doctor['user_id'] === $userId) {
            return $doctor;
        }
    }
    return null;
}

function doctor_detail(int $doctorId): ?array
{
    return active_doctors(['doctor_id' => $doctorId])[0] ?? null;
}

/* ---------------------------------------------------------------------
 * Agenda: horários recorrentes, bloqueios e slots
 * ------------------------------------------------------------------- */

function slot_overlaps_blocks(DateTime $start, DateTime $end, array $blocks): ?array
{
    foreach ($blocks as $block) {
        $blockStart = new DateTime($block['block_date'] . ' ' . $block['start_time']);
        $blockEnd = new DateTime($block['block_date'] . ' ' . $block['end_time']);
        if ($start < $blockEnd && $end > $blockStart) {
            return $block;
        }
    }
    return null;
}

function ensure_slots(int $doctorId, string $fromDate, string $toDate): void
{
    $doctor = repository_find_doctor($doctorId);
    if (!$doctor || !(int) ($doctor['active'] ?? 0)) {
        return;
    }

    $scheduleStmt = db()->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND active = 1');
    $scheduleStmt->execute([$doctorId]);
    $schedules = $scheduleStmt->fetchAll();

    $blockStmt = db()->prepare('SELECT * FROM schedule_blocks WHERE doctor_id = ? AND block_date >= ? AND block_date <= ?');
    $blockStmt->execute([$doctorId, $fromDate, $toDate]);
    $blocks = $blockStmt->fetchAll();

    $existingStmt = db()->prepare('SELECT slot_start FROM appointment_slots WHERE doctor_id = ?');
    $existingStmt->execute([$doctorId]);
    $existing = array_fill_keys(
        array_map(static fn(string $s): string => $doctorId . '|' . $s, array_column($existingStmt->fetchAll(), 'slot_start')),
        true
    );

    $duration = max(10, (int) ($doctor['appointment_duration'] ?? 30));
    $current = new DateTime($fromDate);
    $endDate = (new DateTime($toDate))->modify('+1 day');
    $newSlots = [];

    while ($current < $endDate) {
        $weekday = (int) $current->format('w');
        foreach ($schedules as $schedule) {
            if ((int) $schedule['weekday'] !== $weekday) {
                continue;
            }
            $slotStart = new DateTime($current->format('Y-m-d') . ' ' . $schedule['start_time']);
            $limit = new DateTime($current->format('Y-m-d') . ' ' . $schedule['end_time']);
            while ($slotStart < $limit) {
                $slotEnd = (clone $slotStart)->modify('+' . $duration . ' minutes');
                if ($slotEnd > $limit) {
                    break;
                }
                $start = $slotStart->format('Y-m-d H:i:s');
                $key = $doctorId . '|' . $start;
                if (!isset($existing[$key])) {
                    $block = slot_overlaps_blocks($slotStart, $slotEnd, $blocks);
                    $newSlots[] = [
                        'doctor_id' => $doctorId,
                        'slot_start' => $start,
                        'slot_end' => $slotEnd->format('Y-m-d H:i:s'),
                        'status' => $block ? 'blocked' : 'available',
                        'block_reason' => $block['reason'] ?? null,
                    ];
                    $existing[$key] = true;
                }
                $slotStart = $slotEnd;
            }
        }
        $current->modify('+1 day');
    }

    if (!$newSlots) {
        return;
    }

    db_transaction(function () use ($newSlots): void {
        foreach ($newSlots as $slot) {
            repository_append('appointment_slots', $slot);
        }
    });
}

function ensure_slots_for_all(string $fromDate, string $toDate): void
{
    foreach (active_doctors() as $doctor) {
        ensure_slots((int) $doctor['id'], $fromDate, $toDate);
    }
}

function available_slots(int $doctorId, string $date): array
{
    ensure_slots($doctorId, $date, $date);
    $stmt = db()->prepare(
        'SELECT id, slot_start, slot_end FROM appointment_slots
         WHERE doctor_id = ? AND DATE(slot_start) = ? AND status = "available" AND slot_start > NOW()
         ORDER BY slot_start'
    );
    $stmt->execute([$doctorId, $date]);
    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'slot_start' => $row['slot_start'],
        'slot_end' => $row['slot_end'],
    ], $stmt->fetchAll());
}

/**
 * Dias da semana (0=domingo ... 6=sábado, mesma convenção de
 * doctor_schedules.weekday) em que o médico tem algum bloco de
 * atendimento ativo cadastrado. Usado só para o destaque visual do
 * calendário do modal "Nova consulta" — a checagem definitiva de
 * horário livre continua sendo available_slots(), acima.
 */
function doctor_working_weekdays(int $doctorId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT weekday FROM doctor_schedules WHERE doctor_id = ? AND active = 1'
    );
    $stmt->execute([$doctorId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'weekday'));
}

function doctor_day_slots(int $doctorId, string $date): array
{
    ensure_slots($doctorId, $date, $date);
    $sql = 'SELECT s.*, a.id AS appointment_id, a.status AS appointment_status,
                   p.name AS patient_name, p.phone AS patient_phone
            FROM appointment_slots s
            LEFT JOIN appointments a ON a.slot_id = s.id AND a.status != "cancelled"
            LEFT JOIN users p ON p.id = a.patient_id
            WHERE s.doctor_id = ? AND DATE(s.slot_start) = ?
            ORDER BY s.slot_start';
    $stmt = db()->prepare($sql);
    $stmt->execute([$doctorId, $date]);
    return array_map(static function (array $row): array {
        $row['appointment_id'] = $row['appointment_id'] !== null ? (int) $row['appointment_id'] : null;
        return $row;
    }, $stmt->fetchAll());
}

function can_change_appointment(string $slotStart, int $hours): bool
{
    return (new DateTime())->modify('+' . $hours . ' hours') <= new DateTime($slotStart);
}

/* ---------------------------------------------------------------------
 * Notificações
 * ------------------------------------------------------------------- */

function create_notification(int $userId, ?int $appointmentId, string $type, string $title, string $message, ?string $sendAt = null): void
{
    repository_append('notifications', [
        'user_id' => $userId,
        'appointment_id' => $appointmentId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'status' => 'sent',
        'send_at' => $sendAt ?: now_sql(),
        'sent_at' => now_sql(),
        'read_at' => null,
        'created_at' => now_sql(),
    ]);
}

function notifications_for_user(int $userId): array
{
    $sql = 'SELECT n.*, a.status AS appointment_status, s.slot_start AS slot_start
            FROM notifications n
            LEFT JOIN appointments a ON a.id = n.appointment_id
            LEFT JOIN appointment_slots s ON s.id = a.slot_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT 80';
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function unread_notifications_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function mark_notifications_read(int $userId): void
{
    $stmt = db()->prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([now_sql(), $userId]);
}

/* ---------------------------------------------------------------------
 * Consultas (appointments)
 * ------------------------------------------------------------------- */

function doctor_user_id(int $doctorId): ?int
{
    $doctor = repository_find_doctor($doctorId);
    return $doctor ? (int) $doctor['user_id'] : null;
}

function create_appointment(
    int $patientId,
    int $doctorId,
    int $slotId,
    string $notes = '',
    string $modality = 'presencial',
    string $status = 'pending'
): int {
    return db_transaction(function () use ($patientId, $doctorId, $slotId, $notes, $modality, $status): int {
        $slotStmt = db()->prepare('SELECT * FROM appointment_slots WHERE id = ? FOR UPDATE');
        $slotStmt->execute([$slotId]);
        $slot = $slotStmt->fetch();

        $doctor = repository_find_doctor($doctorId);

        if (!$slot || !$doctor || $slot['status'] !== 'available') {
            throw new RuntimeException('Horário não encontrado ou indisponível.');
        }

        $conflictStmt = db()->prepare(
            'SELECT COUNT(*) FROM appointments WHERE slot_id = ? AND status IN ("pending", "confirmed")'
        );
        $conflictStmt->execute([$slotId]);
        if ((int) $conflictStmt->fetchColumn() > 0) {
            throw new RuntimeException('Este horário já foi reservado.');
        }

        try {
            $appointmentId = repository_append('appointments', [
                'slot_id' => $slotId,
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'clinic_id' => (int) $doctor['clinic_id'],
                'specialty_id' => (int) $doctor['specialty_id'],
                'status' => $status,
                'modality' => $modality,
                'notes' => $notes ?: null,
                'cancel_reason' => null,
                'confirmed_at' => $status === 'confirmed' ? now_sql() : null,
                'cancelled_at' => null,
                'completed_at' => null,
                'created_at' => now_sql(),
                'updated_at' => now_sql(),
            ]);
        } catch (PDOException $e) {
            // Ex.: outra requisição reservou o mesmo horário entre a
            // checagem acima e este INSERT (corrida). Vira uma mensagem
            // amigável em vez de erro fatal.
            throw new RuntimeException('Este horário acabou de ser reservado por outra solicitação. Escolha outro horário.');
        }

        repository_replace('appointment_slots', $slotId, ['status' => 'booked']);

        repository_append('payments', [
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'clinic_id' => (int) $doctor['clinic_id'],
            'amount' => 250.00,
            'method' => null,
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now_sql(),
            'updated_at' => null,
        ]);

        return $appointmentId;
    });
}

function appointments_for_user(array $user, string $scope = 'future'): array
{
    $doctor = $user['role'] === 'doctor' ? doctor_by_user((int) $user['id']) : null;
    if ($user['role'] === 'doctor' && !$doctor) {
        return [];
    }

    $sql = 'SELECT a.id FROM appointments a JOIN appointment_slots s ON s.id = a.slot_id WHERE 1=1';
    $params = [];

    if ($user['role'] === 'doctor') {
        $sql .= ' AND a.doctor_id = ?';
        $params[] = (int) $doctor['id'];
    } elseif ($user['role'] === 'patient') {
        $sql .= ' AND a.patient_id = ?';
        $params[] = (int) $user['id'];
    }

    if ($scope === 'future') {
        $sql .= ' AND s.slot_start >= ? AND a.status IN ("pending", "confirmed")';
        $params[] = now_sql();
    } elseif ($scope === 'history') {
        $sql .= ' AND (s.slot_start < ? OR a.status NOT IN ("pending", "confirmed"))';
        $params[] = now_sql();
    }

    $sql .= ' ORDER BY s.slot_start ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map(
        static fn(array $row): array => repository_appointment_row(repository_find_appointment((int) $row['id'])),
        $stmt->fetchAll()
    );
}

function appointments_for_admin(array $filters = []): array
{
    $sql = 'SELECT a.id FROM appointments a JOIN appointment_slots s ON s.id = a.slot_id WHERE 1=1';
    $params = [];

    if (!empty($filters['date'])) {
        $sql .= ' AND DATE(s.slot_start) = ?';
        $params[] = $filters['date'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND a.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['doctor_id'])) {
        $sql .= ' AND a.doctor_id = ?';
        $params[] = (int) $filters['doctor_id'];
    }

    $sql .= ' ORDER BY s.slot_start DESC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map(
        static fn(array $row): array => repository_appointment_row(repository_find_appointment((int) $row['id'])),
        $stmt->fetchAll()
    );
}

function appointment_by_id(int $appointmentId): ?array
{
    $appointment = repository_find_appointment($appointmentId);
    return $appointment ? repository_appointment_row($appointment) : null;
}

function cancel_appointment(int $appointmentId, array $actor, string $reason = ''): void
{
    db_transaction(function () use ($appointmentId, $actor, $reason): void {
        $appointment = repository_find_appointment($appointmentId);
        if (!$appointment || !in_array($appointment['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Consulta não pode ser cancelada.');
        }

        $doctor = $actor['role'] === 'doctor' ? doctor_by_user((int) $actor['id']) : null;
        if ($actor['role'] === 'doctor' && (!$doctor || (int) $appointment['doctor_id'] !== (int) $doctor['id'])) {
            abort_forbidden();
        }

        repository_replace('appointments', $appointmentId, [
            'status' => 'cancelled',
            'cancel_reason' => $reason ?: null,
            'cancelled_at' => now_sql(),
            'updated_at' => now_sql(),
        ]);

        $slot = repository_slot((int) $appointment['slot_id']);
        if ($slot && new DateTime($slot['slot_start']) > new DateTime()) {
            repository_replace('appointment_slots', (int) $slot['id'], ['status' => 'available']);
        }

        create_notification((int) $appointment['doctor_id'], $appointmentId, 'in_app', 'Consulta cancelada', 'O cancelamento da consulta foi registrado.');
    });
}

function reschedule_appointment(int $appointmentId, int $newSlotId, array $actor): void
{
    throw new RuntimeException('A remarcação é realizada pelo administrador nesta versão.');
}

function confirm_appointment(int $appointmentId, int $patientId): void
{
    throw new RuntimeException('A confirmação pelo paciente foi desativada.');
}

function mark_appointment(int $appointmentId, array $actor, string $status): void
{
    if (!in_array($status, ['completed', 'no_show'], true)) {
        throw new RuntimeException('Status inválido.');
    }
    $appointment = repository_find_appointment($appointmentId);
    if (!$appointment) {
        throw new RuntimeException('Consulta não encontrada.');
    }
    if ($actor['role'] === 'doctor') {
        $doctor = doctor_by_user((int) $actor['id']);
        if (!$doctor || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            abort_forbidden();
        }
    } elseif ($actor['role'] !== 'admin') {
        abort_forbidden();
    }

    repository_replace('appointments', $appointmentId, [
        'status' => $status,
        'completed_at' => now_sql(),
        'updated_at' => now_sql(),
    ]);
}

/* ---------------------------------------------------------------------
 * Perfis e usuários
 * ------------------------------------------------------------------- */

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function email_in_use(string $email): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?)');
    $stmt->execute([$email]);
    return (int) $stmt->fetchColumn() > 0;
}

/* ---------------------------------------------------------------------
 * Recuperação de senha ("Esqueci minha senha")
 * ------------------------------------------------------------------- */

/**
 * Define a nova senha do usuário (já com a identidade confirmada via
 * pergunta de segurança — ver password_reset_can_set_new_password() em
 * app/auth.php). A senha é sempre persistida com password_hash() —
 * nunca em texto puro.
 */
function reset_user_password(int $userId, string $newPassword): void
{
    if (strlen($newPassword) < 6) {
        throw new RuntimeException('A senha deve ter pelo menos 6 caracteres.');
    }

    repository_replace('users', $userId, [
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'updated_at' => now_sql(),
    ]);
}

/* ---------------------------------------------------------------------
 * Pergunta de segurança (rota alternativa de verificação em
 * "Esqueci minha senha", além do código enviado por e-mail).
 * ------------------------------------------------------------------- */

/**
 * Normaliza a resposta antes de gerar/validar o hash: remove espaços nas
 * pontas, colapsa espaços internos repetidos e ignora maiúsculas/minúsculas
 * e acentuação. Isso evita que "São Paulo", "sao paulo " ou "SÃO  PAULO"
 * sejam tratadas como respostas diferentes.
 */
function normalize_security_answer(string $answer): string
{
    $answer = trim($answer);
    $answer = mb_strtolower($answer, 'UTF-8');
    // Remove acentos (assim "São Paulo" e "Sao Paulo" batem).
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $answer);
    if ($transliterated !== false) {
        $answer = $transliterated;
    }
    // Colapsa espaços múltiplos em um único espaço.
    $answer = preg_replace('/\s+/', ' ', $answer) ?? $answer;
    return trim($answer);
}

/**
 * Cadastra ou atualiza a pergunta de segurança do usuário. A resposta é
 * normalizada e, em seguida, armazenada apenas como hash — nunca em
 * texto puro — usando o mesmo password_hash() já usado para a senha.
 */
function set_user_security_question(int $userId, string $question, string $answer): void
{
    $question = trim($question);
    $normalizedAnswer = normalize_security_answer($answer);

    if ($question === '' || $normalizedAnswer === '') {
        throw new RuntimeException('Selecione uma pergunta de segurança e informe a resposta.');
    }

    repository_update_user($userId, [
        'security_question' => $question,
        'security_answer_hash' => password_hash($normalizedAnswer, PASSWORD_DEFAULT),
    ]);
}

/**
 * Retorna a pergunta de segurança cadastrada por um usuário (texto puro,
 * sem a resposta) — usada para exibi-la na etapa 2 alternativa do fluxo
 * de recuperação de senha. Retorna null se o usuário não cadastrou uma.
 */
function get_user_security_question(int $userId): ?string
{
    $user = repository_find_user($userId);
    $question = $user['security_question'] ?? null;
    return $question !== null && $question !== '' ? $question : null;
}

/**
 * Verifica a resposta informada contra o hash salvo, usando a mesma
 * normalização (case/acentos/espaços) aplicada no cadastro.
 */
function verify_user_security_answer(int $userId, string $answer): bool
{
    $user = repository_find_user($userId);
    if (!$user || empty($user['security_answer_hash'])) {
        return false;
    }

    return password_verify(normalize_security_answer($answer), $user['security_answer_hash']);
}

    function mark_tutorial_seen(int $userId): void
{
    repository_update_user($userId, ['tutorial_seen' => 1]);
}

/**
 * Registra o aceite dos Termos de Uso e Política de Privacidade
 * (chamado ao clicar "Prosseguir" no modal bloqueante — ver
 * render_terms_modal() em index.php).
 */
function accept_terms(int $userId): void
{
    repository_update_user($userId, ['terms_accepted' => 1]);
}

function update_profile(int $userId, array $data): void
{
    repository_update_user($userId, [
        'name' => $data['name'],
        'phone' => $data['phone'] ?: null,
        'document' => $data['document'] ?: null,
        'birth_date' => $data['birth_date'] ?: null,
        'address' => $data['address'] ?: null,
    ]);
}

function update_patient_admin(int $patientId, array $data): void
{
    $patient = repository_find_user($patientId);
    if (!$patient || $patient['role'] !== 'patient') {
        throw new RuntimeException('Paciente não encontrado.');
    }
    update_profile($patientId, $data);
    repository_update_user($patientId, ['status' => $data['status'] === 'inactive' ? 'inactive' : 'active']);
}

function update_staff_profile(int $userId, array $data): void
{
    $user = repository_find_user($userId);
    if (!$user || !in_array($user['role'], ['admin', 'doctor'], true)) {
        abort_forbidden();
    }
    update_profile($userId, $data);
}

function update_own_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
{
    if ($newPassword === '' && $confirmPassword === '') {
        return;
    }
    if (strlen($newPassword) < 6 || $newPassword !== $confirmPassword) {
        throw new RuntimeException('Confira a nova senha e use pelo menos 6 caracteres.');
    }
    $user = repository_find_user($userId);
    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        throw new RuntimeException('Senha atual inválida.');
    }
    repository_update_user($userId, ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]);
}

/* ---------------------------------------------------------------------
 * Gestão de médicos, agenda e bloqueios (admin)
 * ------------------------------------------------------------------- */

function create_doctor(array $data): int
{
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido para o médico.');
    }
    if (email_in_use($data['email'])) {
        throw new RuntimeException('Já existe usuário com este e-mail.');
    }

    return db_transaction(function () use ($data): int {
        $userId = repository_append('users', [
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password_hash' => password_hash($data['password'] ?: '123456', PASSWORD_DEFAULT),
            'role' => 'doctor',
            'phone' => $data['phone'] ?: null,
            'document' => null,
            'birth_date' => null,
            'address' => null,
            'clinic_id' => (int) $data['clinic_id'],
            'status' => 'active',
            'created_at' => now_sql(),
            'updated_at' => null,
            'last_login_at' => null,
        ]);

        return repository_append('doctors', [
            'user_id' => $userId,
            'clinic_id' => (int) $data['clinic_id'],
            'specialty_id' => (int) $data['specialty_id'],
            'crm' => $data['crm'],
            'bio' => $data['bio'] ?: null,
            'appointment_duration' => (int) ($data['appointment_duration'] ?: 30),
            'active' => 1,
            'created_at' => now_sql(),
        ]);
    });
}

function update_doctor(int $doctorId, array $data): void
{
    $doctor = repository_find_doctor($doctorId);
    if (!$doctor) {
        throw new RuntimeException('Médico não encontrado.');
    }
    repository_update_user((int) $doctor['user_id'], [
        'name' => $data['name'],
        'phone' => $data['phone'] ?: null,
        'clinic_id' => (int) $data['clinic_id'],
    ]);
    repository_replace('doctors', $doctorId, [
        'clinic_id' => (int) $data['clinic_id'],
        'specialty_id' => (int) $data['specialty_id'],
        'crm' => $data['crm'],
        'bio' => $data['bio'] ?: null,
        'appointment_duration' => (int) ($data['appointment_duration'] ?: 30),
    ]);
}

function deactivate_doctor(int $doctorId): void
{
    $doctor = repository_find_doctor($doctorId);
    if (!$doctor) {
        throw new RuntimeException('Médico não encontrado.');
    }
    repository_replace('doctors', $doctorId, ['active' => 0]);
    repository_update_user((int) $doctor['user_id'], ['status' => 'inactive']);
}

function add_schedule(array $data): void
{
    if ($data['start_time'] >= $data['end_time']) {
        throw new RuntimeException('Horário inicial deve ser antes do final.');
    }
    repository_append('doctor_schedules', [
        'doctor_id' => (int) $data['doctor_id'],
        'weekday' => (int) $data['weekday'],
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'active' => 1,
    ]);
}

function delete_schedule(int $scheduleId): void
{
    repository_replace('doctor_schedules', $scheduleId, ['active' => 0]);
}

function add_block(array $data): void
{
    if ($data['start_time'] >= $data['end_time']) {
        throw new RuntimeException('Horário inicial deve ser antes do final.');
    }
    $doctorId = (int) $data['doctor_id'];
    $start = $data['block_date'] . ' ' . $data['start_time'];
    $end = $data['block_date'] . ' ' . $data['end_time'];

    db_transaction(function () use ($data, $doctorId, $start, $end): void {
        $conflictStmt = db()->prepare(
            'SELECT COUNT(*) FROM appointment_slots
             WHERE doctor_id = ? AND status = "booked" AND slot_start < ? AND slot_end > ?'
        );
        $conflictStmt->execute([$doctorId, $end, $start]);
        if ((int) $conflictStmt->fetchColumn() > 0) {
            throw new RuntimeException('Não é possível bloquear horário com consulta agendada.');
        }

        repository_append('schedule_blocks', [
            'doctor_id' => $doctorId,
            'block_date' => $data['block_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'reason' => $data['reason'] ?: null,
            'created_at' => now_sql(),
        ]);

        $updateStmt = db()->prepare(
            'UPDATE appointment_slots SET status = "blocked", block_reason = ?
             WHERE doctor_id = ? AND status = "available" AND slot_start < ? AND slot_end > ?'
        );
        $updateStmt->execute([$data['reason'] ?: 'Bloqueado pela clínica', $doctorId, $end, $start]);
    });
}

function doctor_schedules(int $doctorId): array
{
    $stmt = db()->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND active = 1 ORDER BY weekday, start_time');
    $stmt->execute([$doctorId]);
    return $stmt->fetchAll();
}

function doctor_blocks(int $doctorId): array
{
    $stmt = db()->prepare('SELECT * FROM schedule_blocks WHERE doctor_id = ? AND block_date >= ? ORDER BY block_date, start_time');
    $stmt->execute([$doctorId, current_date_value()]);
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------------------
 * Pacientes
 * ------------------------------------------------------------------- */

function patient_list(string $search = ''): array
{
    $sql = 'SELECT u.*,
                   COUNT(a.id) AS total_appointments,
                   SUM(CASE WHEN a.status = "no_show" THEN 1 ELSE 0 END) AS no_shows
            FROM users u
            LEFT JOIN appointments a ON a.patient_id = u.id
            WHERE u.role = "patient"';
    $params = [];

    if ($search !== '') {
        // Busca por nome, e-mail, telefone ou CPF — cobre os jeitos
        // mais comuns de alguém procurar um paciente na lista.
        $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.document LIKE ?)';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= ' GROUP BY u.id ORDER BY u.name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return array_map(static function (array $row): array {
        $row['total_appointments'] = (int) $row['total_appointments'];
        $row['no_shows'] = (int) $row['no_shows'];
        return $row;
    }, $rows);
}

function doctor_patient_list(int $doctorId, string $search = ''): array
{
    $idsStmt = db()->prepare('SELECT DISTINCT patient_id FROM appointments WHERE doctor_id = ?');
    $idsStmt->execute([$doctorId]);
    $patientIds = array_map('intval', array_column($idsStmt->fetchAll(), 'patient_id'));

    $rows = [];
    foreach ($patientIds as $patientId) {
        $patient = repository_find_user($patientId);
        if (!$patient || ($search !== '' && stripos($patient['name'], $search) === false)) {
            continue;
        }

        $apptStmt = db()->prepare(
            'SELECT s.slot_start FROM appointments a
             JOIN appointment_slots s ON s.id = a.slot_id
             WHERE a.doctor_id = ? AND a.patient_id = ?
             ORDER BY s.slot_start'
        );
        $apptStmt->execute([$doctorId, $patientId]);
        $dates = array_column($apptStmt->fetchAll(), 'slot_start');

        $future = array_values(array_filter($dates, static fn(string $date): bool => $date >= now_sql()));
        $past = array_values(array_filter($dates, static fn(string $date): bool => $date < now_sql()));

        $patient['last_appointment'] = $past ? end($past) : null;
        $patient['next_appointment'] = $future[0] ?? null;
        $patient['total_appointments'] = count($dates);
        $rows[] = $patient;
    }

    usort($rows, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    return $rows;
}

function patient_detail_for_doctor(int $doctorId, int $patientId): ?array
{
    foreach (doctor_patient_list($doctorId) as $patient) {
        if ((int) $patient['id'] === $patientId) {
            return $patient;
        }
    }
    return null;
}

/* ---------------------------------------------------------------------
 * Prontuário eletrônico
 * ------------------------------------------------------------------- */

function medical_record_by_appointment(int $appointmentId): ?array
{
    $stmt = db()->prepare('SELECT * FROM medical_records WHERE appointment_id = ? LIMIT 1');
    $stmt->execute([$appointmentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function medical_records_for_patient(int $patientId, ?int $doctorId = null): array
{
    $sql = 'SELECT r.*, a.status AS appointment_status, s.slot_start AS slot_start, du.name AS doctor_name
            FROM medical_records r
            JOIN appointments a ON a.id = r.appointment_id
            JOIN appointment_slots s ON s.id = a.slot_id
            JOIN doctors d ON d.id = r.doctor_id
            JOIN users du ON du.id = d.user_id
            WHERE r.patient_id = ?';
    $params = [$patientId];

    if ($doctorId) {
        $sql .= ' AND r.doctor_id = ?';
        $params[] = $doctorId;
    }

    $sql .= ' ORDER BY s.slot_start DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function save_medical_record(int $appointmentId, array $actor, array $data): void
{
    db_transaction(function () use ($appointmentId, $actor, $data): void {
        $appointment = repository_find_appointment($appointmentId);
        if (!$appointment || !in_array($appointment['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Consulta não pode ser encerrada.');
        }
        $doctor = doctor_by_user((int) $actor['id']);
        if (!$doctor || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            abort_forbidden();
        }

        $fields = [];
        foreach (['weight', 'height', 'temperature', 'heart_rate', 'blood_pressure', 'symptoms', 'diagnosis', 'prescription', 'follow_up'] as $field) {
            $fields[$field] = $data[$field] ?: null;
        }

        $existing = medical_record_by_appointment($appointmentId);
        if ($existing) {
            $fields['updated_at'] = now_sql();
            repository_replace('medical_records', (int) $existing['id'], $fields);
        } else {
            repository_append('medical_records', array_merge($fields, [
                'appointment_id' => $appointmentId,
                'patient_id' => $appointment['patient_id'],
                'doctor_id' => $appointment['doctor_id'],
                'created_by' => $actor['id'],
                'created_at' => now_sql(),
                'updated_at' => now_sql(),
            ]));
        }

        repository_replace('appointments', $appointmentId, [
            'status' => 'completed',
            'completed_at' => now_sql(),
            'updated_at' => now_sql(),
        ]);

        $paymentStmt = db()->prepare('SELECT id FROM payments WHERE appointment_id = ? AND status = "pending" LIMIT 1');
        $paymentStmt->execute([$appointmentId]);
        $paymentId = $paymentStmt->fetchColumn();
        if ($paymentId) {
            repository_replace('payments', (int) $paymentId, [
                'status' => 'paid',
                'paid_at' => now_sql(),
                'updated_at' => now_sql(),
            ]);
        }
    });
}

function age_from_birth(?string $birthDate): string
{
    return $birthDate ? (new DateTime($birthDate))->diff(new DateTime())->y . ' anos' : '-';
}

/* ---------------------------------------------------------------------
 * Calendário, relatórios e dashboard
 * ------------------------------------------------------------------- */

function calendar_appointments(int $year, int $month, ?int $doctorId = null): array
{
    $prefix = sprintf('%04d-%02d-', $year, $month);
    $days = [];
    // Quando $doctorId é informado, a busca já sai filtrada no repositório
    // (mesmo filtro usado em appointments_for_admin), garantindo que um
    // médico nunca receba, nem carregado em memória, compromissos de
    // outro profissional.
    $filters = $doctorId !== null ? ['doctor_id' => $doctorId] : [];
    foreach (appointments_for_admin($filters) as $appointment) {
        if (str_starts_with((string) $appointment['slot_start'], $prefix)) {
            $day = (int) (new DateTime($appointment['slot_start']))->format('j');
            $days[$day][] = $appointment;
        }
    }
    return $days;
}

function report_data(string $fromDate, string $toDate): array
{
    ensure_slots_for_all($fromDate, $toDate);

    $appointments = array_values(array_filter(
        appointments_for_admin(),
        static fn(array $row): bool => substr((string) $row['slot_start'], 0, 10) >= $fromDate && substr((string) $row['slot_start'], 0, 10) <= $toDate
    ));

    $summary = ['total' => count($appointments), 'completed' => 0, 'no_shows' => 0, 'active' => 0];
    foreach ($appointments as $row) {
        if ($row['status'] === 'completed') $summary['completed']++;
        if ($row['status'] === 'no_show') $summary['no_shows']++;
        if (in_array($row['status'], ['pending', 'confirmed'], true)) $summary['active']++;
    }

    $slotStmt = db()->prepare(
        'SELECT COUNT(*) AS total_slots,
                SUM(CASE WHEN status = "booked" THEN 1 ELSE 0 END) AS booked_slots,
                SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) AS blocked_slots
         FROM appointment_slots WHERE DATE(slot_start) >= ? AND DATE(slot_start) <= ?'
    );
    $slotStmt->execute([$fromDate, $toDate]);
    $slotRow = $slotStmt->fetch();
    $slotSummary = [
        'total_slots' => (int) ($slotRow['total_slots'] ?? 0),
        'booked_slots' => (int) ($slotRow['booked_slots'] ?? 0),
        'blocked_slots' => (int) ($slotRow['blocked_slots'] ?? 0),
    ];

    $byDoctor = [];
    foreach (active_doctors() as $doctor) {
        $doctorAppointments = array_values(array_filter($appointments, static fn(array $row): bool => (int) $row['doctor_id'] === (int) $doctor['id']));
        $byDoctor[] = [
            'doctor_name' => $doctor['name'],
            'total' => count($doctorAppointments),
            'no_shows' => count(array_filter($doctorAppointments, static fn(array $row): bool => $row['status'] === 'no_show')),
            'completed' => count(array_filter($doctorAppointments, static fn(array $row): bool => $row['status'] === 'completed')),
        ];
    }

    return ['summary' => $summary, 'slots' => $slotSummary, 'by_doctor' => $byDoctor];
}

function dashboard_metrics(): array
{
    $today = current_date_value();

    $todayStmt = db()->prepare(
        'SELECT COUNT(*) FROM appointments a JOIN appointment_slots s ON s.id = a.slot_id
         WHERE DATE(s.slot_start) = ? AND a.status IN ("pending", "confirmed")'
    );
    $todayStmt->execute([$today]);

    return [
        'today' => (int) $todayStmt->fetchColumn(),
        'pending' => (int) db()->query('SELECT COUNT(*) FROM appointments WHERE status = "pending"')->fetchColumn(),
        'patients' => (int) db()->query('SELECT COUNT(*) FROM users WHERE role = "patient" AND status = "active"')->fetchColumn(),
        'doctors' => (int) db()->query('SELECT COUNT(*) FROM doctors WHERE active = 1')->fetchColumn(),
    ];
}

function create_due_reminders(int $hoursAhead = 24): int
{
    $from = (new DateTime())->modify('+' . max(0, $hoursAhead - 1) . ' hours');
    $to = (new DateTime())->modify('+' . ($hoursAhead + 1) . ' hours');
    $created = 0;

    foreach (appointments_for_admin() as $appointment) {
        $slot = new DateTime($appointment['slot_start']);
        if (!in_array($appointment['status'], ['pending', 'confirmed'], true) || $slot < $from || $slot > $to) {
            continue;
        }
        create_notification((int) $appointment['doctor_id'], (int) $appointment['id'], 'in_app', 'Lembrete de consulta', 'Você tem uma consulta marcada para ' . format_datetime($appointment['slot_start']) . '.');
        $created++;
    }

    return $created;
}