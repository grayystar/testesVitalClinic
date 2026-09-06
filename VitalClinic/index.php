<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/app/helpers.php';

date_default_timezone_set(config('timezone') ?: 'America/Sao_Paulo');

require __DIR__ . '/app/db.php';
require __DIR__ . '/app/api_client.php';
require __DIR__ . '/app/repository.php';
require __DIR__ . '/app/mailer.php';
require __DIR__ . '/app/auth.php';

foreach ([
    'pages/auth/login.php',
    'pages/auth/recuperar_senha.php',
    'pages/admin/dashboard.php',
    'pages/admin/calendario.php',
    'pages/admin/consultas.php',
    'pages/admin/pacientes.php',
    'pages/admin/medicos.php',
    'pages/admin/relatorios.php',
    'pages/medico/dashboard.php',
    'pages/medico/calendario.php',
    'pages/medico/consultas.php',
    'pages/medico/pacientes.php',
    'pages/medico/prontuario.php',
    'pages/medico/historico.php',
] as $pageFile) {
    require __DIR__ . '/' . $pageFile;
}

try {
    run_app();
} catch (RuntimeException $e) {
    render_install_error($e);
}

function run_app(): void
{
    ensure_runtime_schema();

    if (($_GET['action'] ?? '') === 'slots') {
        slots_endpoint();
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            handle_post();
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(['page' => $_POST['page_after'] ?? ($_GET['page'] ?? 'dashboard')]);
        }
    }

    $page = $_GET['page'] ?? 'dashboard';
    $publicPages = ['login', 'forgot_password', 'reset_verify', 'reset_password'];
    $user = current_user();

    if (!$user && !in_array($page, $publicPages, true)) {
        $page = 'login';
    }

    if ($user && in_array($page, $publicPages, true)) {
        $page = 'dashboard';
    }

    // Guarda de fluxo do "Esqueci minha senha": impede acesso direto por
    // URL a uma etapa sem ter concluído a etapa anterior. Precisa ser
    // resolvido aqui (antes de qualquer HTML ser enviado), pois
    // redirect() usa header('Location: ...').
    if (!$user) {
        if ($page === 'reset_verify' && !password_reset_pending()) {
            flash('error', 'Informe seu e-mail para receber o código de verificação.');
            redirect(['page' => 'forgot_password']);
        }
        if ($page === 'reset_password' && !password_reset_can_set_new_password()) {
            flash('error', 'Confirme o código de verificação antes de definir a nova senha.');
            redirect(['page' => password_reset_pending() ? 'reset_verify' : 'forgot_password']);
        }
    }

    render_layout($page, $user);
}

function slots_endpoint(): void
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Nao autenticado.']);
        return;
    }

    $doctorId = (int) ($_GET['doctor_id'] ?? 0);
    $date = $_GET['date'] ?? current_date_value();
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);

    if (!$doctorId || !$dateObj || $dateObj->format('Y-m-d') !== $date) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Dados invalidos.']);
        return;
    }

    $maxDate = (new DateTime())->modify('+' . (int) config('rules.booking_max_days') . ' days');
    if ($dateObj > $maxDate) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Data fora do limite permitido.']);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'slots' => array_map(static function (array $slot): array {
            return [
                'id' => (int) $slot['id'],
                'time' => format_time($slot['slot_start']),
                'label' => format_time($slot['slot_start']) . ' - ' . format_time($slot['slot_end']),
            ];
        }, available_slots($doctorId, $date)),
    ]);
}

function handle_post(): void
{
    verify_csrf();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            if (!login_user(post_value('email'), (string) ($_POST['password'] ?? ''), post_value('role_context'))) {
                throw new RuntimeException('E-mail ou senha invalidos.');
            }
            flash('success', 'Login realizado.');
            redirect(['page' => 'dashboard']);

        case 'request_password_reset':
            request_password_reset(post_value('email'));
            flash('success', 'Se o e-mail informado estiver cadastrado, enviamos um código de verificação para ele.');
            redirect(['page' => 'reset_verify']);

        case 'verify_reset_code':
            if (!confirm_password_reset_code(post_value('code'))) {
                throw new RuntimeException('Código inválido, expirado ou com muitas tentativas. Solicite um novo código.');
            }
            flash('success', 'Código confirmado. Cadastre sua nova senha.');
            redirect(['page' => 'reset_password']);

        case 'reset_password':
            complete_password_reset(
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            flash('success', 'Senha redefinida com sucesso. Faça login com a nova senha.');
            redirect(['page' => 'login']);

        case 'cancel_password_reset':
            clear_password_reset();
            redirect(['page' => 'login']);

        case 'logout':
            logout_user();
            flash('success', 'Sessao encerrada.');
            redirect(['page' => 'login']);

        case 'update_staff_profile':
            $user = require_role(['doctor', 'admin']);
            update_staff_profile((int) $user['id'], [
                'name' => post_value('name'),
                'phone' => post_value('phone'),
                'document' => post_value('document'),
                'address' => post_value('address'),
            ]);
            update_own_password(
                (int) $user['id'],
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            flash('success', 'Perfil atualizado.');
            redirect(['page' => 'profile']);

        case 'cancel':
            $user = require_login();
            cancel_appointment((int) ($_POST['appointment_id'] ?? 0), $user, post_value('reason'));
            flash('success', 'Consulta cancelada.');
            redirect(['page' => $_POST['page_after'] ?? 'appointments']);

        case 'admin_create_appointment':
            require_role('admin');
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            $doctorId = (int) ($_POST['doctor_id'] ?? 0);
            $slotId = (int) ($_POST['slot_id'] ?? 0);
            $modality = post_value('modality') === 'teleconsulta' ? 'teleconsulta' : 'presencial';

            $patient = repository_find_user($patientId);
            if (!$patient || $patient['role'] !== 'patient') {
                throw new RuntimeException('Selecione um paciente válido.');
            }
            if (!$slotId) {
                throw new RuntimeException('Selecione um horário disponível.');
            }

            create_appointment($patientId, $doctorId, $slotId, post_value('notes'), $modality, 'confirmed');
            flash('success', 'Consulta cadastrada com sucesso.');
            redirect(['page' => 'admin_appointments']);

        case 'admin_create_appointment':
            require_role('admin');
            $slotId = (int) ($_POST['slot_id'] ?? 0);
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            $doctorId = (int) ($_POST['doctor_id'] ?? 0);
            if (!$patientId) {
                throw new RuntimeException('Selecione o paciente.');
            }
            if (!$doctorId) {
                throw new RuntimeException('Selecione o médico.');
            }
            if (!$slotId) {
                throw new RuntimeException('Selecione um horário disponível para a consulta.');
            }
            create_appointment(
                $patientId,
                $doctorId,
                $slotId,
                post_value('notes'),
                post_value('modality') ?: 'presencial',
                'confirmed'
            );
            flash('success', 'Consulta agendada com sucesso.');
            redirect(['page' => 'admin_appointments']);

        case 'admin_create_doctor':
            require_role('admin');
            create_doctor(doctor_form_data());
            flash('success', 'Medico cadastrado.');
            redirect(['page' => 'admin_doctors']);

        case 'admin_update_doctor':
            require_role('admin');
            update_doctor((int) ($_POST['doctor_id'] ?? 0), doctor_form_data(false));
            flash('success', 'Medico atualizado.');
            redirect(['page' => 'admin_doctors']);

        case 'admin_delete_doctor':
            require_role('admin');
            deactivate_doctor((int) ($_POST['doctor_id'] ?? 0));
            flash('success', 'Medico removido da agenda.');
            redirect(['page' => 'admin_doctors']);

        case 'admin_add_schedule':
            require_role('admin');
            add_schedule([
                'doctor_id' => (int) ($_POST['doctor_id'] ?? 0),
                'weekday' => (int) ($_POST['weekday'] ?? 0),
                'start_time' => post_value('start_time'),
                'end_time' => post_value('end_time'),
            ]);
            flash('success', 'Agenda configurada.');
            redirect(['page' => 'admin_doctors']);

        case 'admin_delete_schedule':
            require_role('admin');
            delete_schedule((int) ($_POST['schedule_id'] ?? 0));
            flash('success', 'Horario removido.');
            redirect(['page' => 'admin_doctors']);

        case 'admin_add_block':
            require_role('admin');
            add_block([
                'doctor_id' => (int) ($_POST['doctor_id'] ?? 0),
                'block_date' => post_value('block_date'),
                'start_time' => post_value('start_time'),
                'end_time' => post_value('end_time'),
                'reason' => post_value('reason'),
            ]);
            flash('success', 'Bloqueio registrado.');
            redirect(['page' => 'admin_doctors']);

        case 'admin_update_patient':
            require_role('admin');
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            update_patient_admin($patientId, [
                'name' => post_value('name'),
                'phone' => post_value('phone'),
                'document' => post_value('document'),
                'birth_date' => post_value('birth_date'),
                'address' => post_value('address'),
                'status' => post_value('status'),
            ]);
            flash('success', 'Paciente atualizado.');
            redirect(['page' => 'admin_patients', 'patient_id' => $patientId]);

        case 'admin_create_patient':
            require_role('admin');
            $initialPassword = (string) ($_POST['password'] ?? '');
            if ($initialPassword === '') {
                $initialPassword = '123456';
            }
            $patientId = register_patient([
                'name' => post_value('name'),
                'email' => post_value('email'),
                'password' => $initialPassword,
                'phone' => post_value('phone'),
                'document' => post_value('document'),
                'birth_date' => post_value('birth_date'),
                'address' => post_value('address'),
                'clinic_id' => (int) ($_POST['clinic_id'] ?? 0),
            ]);
            flash('success', 'Paciente cadastrado. Senha inicial: ' . $initialPassword);
            redirect(['page' => 'admin_patients', 'patient_id' => $patientId]);

        case 'save_medical_record':
            $user = require_role('doctor');
            save_medical_record((int) ($_POST['appointment_id'] ?? 0), $user, [
                'weight' => post_value('weight'),
                'height' => post_value('height'),
                'temperature' => post_value('temperature'),
                'heart_rate' => post_value('heart_rate'),
                'blood_pressure' => post_value('blood_pressure'),
                'symptoms' => post_value('symptoms'),
                'diagnosis' => post_value('diagnosis'),
                'prescription' => post_value('prescription'),
                'follow_up' => post_value('follow_up'),
            ]);
            flash('success', 'Consulta encerrada e salva no historico.');
            redirect(['page' => 'doctor_detail', 'appointment_id' => (int) ($_POST['appointment_id'] ?? 0)]);

        case 'admin_update_user_role':
            $actor = require_role('admin');
            update_user_role(
                (int) ($_POST['user_id'] ?? 0),
                post_value('role'),
                (int) $actor['id']
            );
            flash('success', 'Nível de acesso atualizado.');
            redirect(['page' => 'admin_doctors']);

        case 'mark_appointment':
            $user = require_role(['doctor', 'admin']);
            mark_appointment((int) ($_POST['appointment_id'] ?? 0), $user, post_value('status'));
            flash('success', 'Consulta atualizada.');
            redirect(['page' => $_POST['page_after'] ?? 'dashboard']);

        case 'mark_notifications_read':
            $user = require_login();
            mark_notifications_read((int) $user['id']);
            flash('success', 'Notificacoes marcadas como lidas.');
            redirect(['page' => 'notifications']);
    }

    throw new RuntimeException('Acao invalida.');
}

function doctor_form_data(bool $withPassword = true): array
{
    return [
        'name' => post_value('name'),
        'email' => post_value('email'),
        'password' => $withPassword ? (string) ($_POST['password'] ?? '') : '',
        'phone' => post_value('phone'),
        'clinic_id' => (int) ($_POST['clinic_id'] ?? 0),
        'specialty_id' => (int) ($_POST['specialty_id'] ?? 0),
        'crm' => post_value('crm'),
        'bio' => post_value('bio'),
        'appointment_duration' => (int) ($_POST['appointment_duration'] ?? 30),
    ];
}

function render_layout(string $page, ?array $user): void
{
    $messages = take_flash();
    $title = config('app_name');
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="manifest.webmanifest">
    <title><?= h($title) ?></title>
    <link rel="icon" href="<?= asset_url('assets/brand/vital-clinic-mark.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset_url('assets/css/styles.css') ?>">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="<?= h(app_url(['page' => 'dashboard'])) ?>">
            <img class="brand-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="<?= h($title) ?>">
        </a>
        <?php render_nav($page, $user); ?>
    </header>

    <main class="shell">
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= h($message['type']) ?>"><?= h($message['message']) ?></div>
        <?php endforeach; ?>

        <?php render_page($page, $user); ?>
    </main>

    <footer class="app-footer">
        <span>VitalClinic <?= h(app_version()) ?></span>
    </footer>

    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
    <?php
}

function render_nav(string $page, ?array $user): void
{
    if (!$user) {
        return;
    }

    $items = [];
    if ($user['role'] === 'patient') {
        $items = [
            'profile' => 'Perfil',
            'notifications' => 'Notificações',
        ];
    } elseif ($user['role'] === 'admin') {
        $items = [
            'dashboard' => 'Geral',
            'admin_calendar' => 'Calendário',
            'admin_appointments' => 'Consultas',
            'admin_patients' => 'Pacientes',
            'admin_doctors' => 'Médicos',
            'admin_reports' => 'Relatórios',
            'profile' => 'Perfil',
            'notifications' => 'Notificações',
        ];
    } else {
        $items = [
            'dashboard' => 'Geral',
            'doctor_calendar' => 'Calendário',
            'doctor_appointments' => 'Consultas',
            'doctor_patients' => 'Pacientes',
            'profile' => 'Perfil',
            'notifications' => 'Notificações',
        ];
    }

    $unread = unread_notifications_count((int) $user['id']);
    ?>
    <nav class="nav">
        <?php foreach ($items as $key => $label): ?>
            <a class="<?= $page === $key ? 'active' : '' ?>" href="<?= h(app_url(['page' => $key])) ?>">
                <?= h($label) ?><?= $key === 'notifications' && $unread ? ' (' . (int) $unread . ')' : '' ?>
            </a>
        <?php endforeach; ?>
        <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button class="button ghost" type="submit">Sair</button>
        </form>
    </nav>
    <?php
}

function render_page(string $page, ?array $user): void
{
    if (!$user) {
        if ($page === 'forgot_password') {
            render_forgot_password();
        } elseif ($page === 'reset_verify') {
            render_reset_verify();
        } elseif ($page === 'reset_password') {
            render_reset_password();
        } else {
            render_login();
        }
        return;
    }

    if ($page === 'notifications') {
        render_notifications($user);
        return;
    }

    if ($user['role'] === 'patient') {
        render_profile($user);
        return;
    }

    if ($user['role'] === 'admin') {
        if ($page === 'admin_calendar') {
            render_admin_calendar();
        } elseif ($page === 'admin_doctors') {
            render_admin_doctors();
        } elseif ($page === 'admin_appointments') {
            render_admin_appointments();
        } elseif ($page === 'admin_patients') {
            render_admin_patients();
        } elseif ($page === 'admin_reports') {
            render_admin_reports();
        } elseif ($page === 'profile') {
            render_staff_profile($user);
        } else {
            render_admin_dashboard();
        }
        return;
    }

    if ($page === 'doctor_calendar') {
        render_doctor_calendar($user);
    } elseif ($page === 'doctor_appointments') {
        render_doctor_appointments($user);
    } elseif ($page === 'doctor_patients') {
        render_doctor_patients($user);
    } elseif ($page === 'doctor_patient_history') {
        render_doctor_patient_history($user);
    } elseif ($page === 'doctor_detail') {
        render_doctor_detail($user);
    } elseif ($page === 'doctor_consultation') {
        render_doctor_consultation($user);
    } elseif ($page === 'profile') {
        render_staff_profile($user);
    } else {
        render_doctor_dashboard($user);
    }
}

function render_profile(array $user): void
{
    ?>
    <section class="narrow">
        <form method="post" class="panel form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="page_after" value="profile">
            <h1>Perfil do paciente</h1>
            <label>Nome <input name="name" value="<?= h($user['name']) ?>" required></label>
            <label>E-mail <input value="<?= h($user['email']) ?>" disabled></label>
            <label>Telefone/WhatsApp <input name="phone" value="<?= h($user['phone']) ?>"></label>
            <label>CPF <input name="document" value="<?= h($user['document']) ?>"></label>
            <label>Data de nascimento <input type="date" name="birth_date" value="<?= h($user['birth_date']) ?>"></label>
            <label>Endereço <input name="address" value="<?= h($user['address'] ?? '') ?>"></label>
            <button class="button primary" type="submit">Salvar perfil</button>
        </form>
    </section>
    <?php
}

function render_staff_profile(array $user): void
{
    $doctor = $user['role'] === 'doctor' ? doctor_by_user((int) $user['id']) : null;
    $title = $doctor ? 'Dr. ' . preg_replace('/^Dr(a)?\.?\s+/i', '', $user['name']) : $user['name'];
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow"><?= $user['role'] === 'doctor' ? 'Médico' : 'Administrador' ?></p>
            <h1>Meu perfil</h1>
        </div>
    </section>

    <form method="post" class="grid two profile-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_staff_profile">
        <input type="hidden" name="page_after" value="profile">

        <div class="grid">
            <div class="panel profile-summary">
                <div class="avatar"><?= h(strtoupper(substr($user['name'], 0, 1))) ?></div>
                <div>
                    <h2><?= h($title) ?></h2>
                    <p><?= h($doctor['specialty_name'] ?? 'Administrador da clínica') ?></p>
                    <p class="muted"><?= h($doctor['crm'] ?? 'Gestão da clínica') ?></p>
                </div>
            </div>

            <div class="panel form-card">
                <h2>Alterar senha</h2>
                <label>Senha atual <input type="password" name="current_password" autocomplete="current-password"></label>
                <label>Nova senha <input type="password" name="new_password" autocomplete="new-password"></label>
                <label>Confirmar senha <input type="password" name="confirm_password" autocomplete="new-password"></label>
            </div>
        </div>

        <div class="panel form-card">
            <h2>Informações pessoais</h2>
            <div class="grid two">
                <label>Nome <input name="name" value="<?= h($user['name']) ?>" required></label>
                <label>Endereço <input name="address" value="<?= h($user['address'] ?? '') ?>"></label>
                <label>E-mail <input value="<?= h($user['email']) ?>" disabled></label>
                <label>Telefone <input name="phone" value="<?= h($user['phone']) ?>"></label>
                <label>CPF <input name="document" value="<?= h($user['document']) ?>"></label>
                <label><?= $doctor ? 'CRM' : 'Perfil' ?> <input value="<?= h($doctor['crm'] ?? status_label($user['role'])) ?>" disabled></label>
                <?php if ($doctor): ?>
                    <label>Especialidade <input value="<?= h($doctor['specialty_name']) ?>" disabled></label>
                    <label>Clínica <input value="<?= h($doctor['clinic_name']) ?>" disabled></label>
                <?php endif; ?>
            </div>
            <button class="button primary" type="submit">Salvar perfil</button>
        </div>
    </form>
    <?php
}

function render_notifications(array $user): void
{
    $notifications = notifications_for_user((int) $user['id']);
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Alertas</p>
            <h1>Notificações</h1>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_notifications_read">
            <input type="hidden" name="page_after" value="notifications">
            <button class="button" type="submit">Marcar lidas</button>
        </form>
    </section>
    <section class="panel">
        <div class="list">
            <?php foreach ($notifications as $notification): ?>
                <article class="list-row <?= $notification['read_at'] ? '' : 'unread' ?>">
                    <div>
                        <strong><?= h($notification['title']) ?></strong>
                        <span><?= h($notification['message']) ?></span>
                        <small><?= h(strtoupper($notification['type'])) ?> - <?= h(format_datetime($notification['created_at'])) ?></small>
                    </div>
                    <?php if ($notification['type'] === 'whatsapp'): ?>
                        <a class="button small" target="_blank" rel="noopener" href="https://wa.me/?text=<?= urlencode($notification['message']) ?>">WhatsApp</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$notifications): ?>
                <p class="muted">Sem notificações.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

function render_appointment_table(array $appointments, array $actor, string $pageAfter): void
{
    if (!$appointments) {
        echo '<p class="muted">Nenhum registro encontrado.</p>';
        return;
    }
    ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidade</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= h(format_datetime($appointment['slot_start'])) ?></td>
                        <td>
                            <?= h($appointment['patient_name']) ?>
                            <small><?= h($appointment['patient_phone'] ?: $appointment['patient_email']) ?></small>
                        </td>
                        <td><?= h($appointment['doctor_name']) ?></td>
                        <td><?= h($appointment['specialty_name']) ?></td>
                        <td><?= h($appointment['modality'] === 'teleconsulta' ? 'Teleconsulta' : 'Presencial') ?></td>
                        <td><span class="status <?= h($appointment['status']) ?>"><?= h(status_label($appointment['status'])) ?></span></td>
                        <td>
                            <div class="actions">
                                <?php if ($actor['role'] === 'doctor'): ?>
                                    <a class="button small" href="<?= h(app_url(['page' => 'doctor_detail', 'appointment_id' => $appointment['id']])) ?>">Detalhes</a>
                                <?php endif; ?>

                                <?php if ($actor['role'] === 'doctor' && in_array($appointment['status'], ['pending', 'confirmed'], true)): ?>
                                    <a class="button small primary" href="<?= h(app_url(['page' => 'doctor_consultation', 'appointment_id' => $appointment['id']])) ?>">Iniciar</a>
                                <?php endif; ?>

                                <?php if ($actor['role'] === 'patient' && $appointment['status'] === 'pending'): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="confirm">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                        <button class="button small" type="submit">Confirmar</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($actor['role'] === 'patient' && in_array($appointment['status'], ['pending', 'confirmed'], true) && can_change_appointment($appointment['slot_start'], 24)): ?>
                                    <a class="button small" href="<?= h(app_url(['page' => 'book', 'reschedule_id' => $appointment['id'], 'doctor_id' => $appointment['doctor_id']])) ?>">Remarcar</a>
                                <?php endif; ?>

                                <?php if (in_array($actor['role'], ['patient', 'admin'], true) && in_array($appointment['status'], ['pending', 'confirmed'], true)): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                        <input type="hidden" name="page_after" value="<?= h($pageAfter) ?>">
                                        <button class="button small danger" type="submit" data-confirm="Cancelar consulta?">Cancelar</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($actor['role'], ['doctor', 'admin'], true) && in_array($appointment['status'], ['pending', 'confirmed'], true)): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="mark_appointment">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <input type="hidden" name="page_after" value="<?= h($pageAfter) ?>">
                                        <button class="button small" type="submit">Realizada</button>
                                    </form>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="mark_appointment">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                                        <input type="hidden" name="status" value="no_show">
                                        <input type="hidden" name="page_after" value="<?= h($pageAfter) ?>">
                                        <button class="button small warning" type="submit">Ausência</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function render_install_error(Throwable $e): void
{
    http_response_code(500);
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="manifest.webmanifest">
    <title>Erro de conexão</title>
    <link rel="icon" href="<?= asset_url('assets/brand/vital-clinic-mark.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset_url('assets/css/styles.css') ?>">
</head>
<body>
    <main class="shell">
        <section class="panel narrow">
            <img class="error-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="Vital Clinic">
            <h1>Erro de conexão com o banco de dados</h1>
            <p>O site não conseguiu ler os dados do banco MySQL "vitalclinic". Confira as credenciais em <code>app/config.php</code> (ou nas variáveis <code>VCTCC_DB_HOST</code>, <code>VCTCC_DB_NAME</code>, <code>VCTCC_DB_USER</code>, <code>VCTCC_DB_PASS</code>) e se o script <code>vitalclinic_schema.sql</code> já foi executado.</p>
            <p class="muted"><?= h($e->getMessage()) ?></p>
        </section>
    </main>
</body>
</html>
    <?php
}
