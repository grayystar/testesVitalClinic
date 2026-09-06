<?php

function render_admin_doctors(): void
{
    $clinics = clinics();
    $specialties = specialties();
    $search = trim((string) ($_GET['q'] ?? ''));
    $doctors = active_doctors($search !== '' ? ['search' => $search] : []);
    $currentUser = current_user();
    $staff = staff_users();
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Administração</p>
            <h1>Médicos e agendas</h1>
        </div>
    </section>

    <section class="grid two">
        <div class="panel">
            <h2>Novo médico</h2>
            <form method="post" class="subform" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_create_doctor">
                <input type="hidden" name="page_after" value="admin_doctors">
                <?php render_doctor_fields($clinics, $specialties); ?>
                <button class="button primary" type="submit">Cadastrar médico</button>
            </form>
        </div>

        <div class="panel">
            <h2>Médicos ativos</h2>
            <form method="get" class="filters">
                <input type="hidden" name="page" value="admin_doctors">
                <label>Buscar médico
                    <span class="search-field">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input name="q" value="<?= h($search) ?>" placeholder="Nome, CRM ou especialidade">
                    </span>
                </label>
                <button class="button" type="submit">Buscar</button>
            </form>
            <div class="accordion-list">
                <?php if (!$doctors): ?>
                    <p class="muted">Nenhum médico encontrado<?= $search !== '' ? ' para "' . h($search) . '"' : '' ?>.</p>
                <?php endif; ?>
                <?php foreach ($doctors as $doctor): ?>
                    <details class="doctor-card">
                        <summary>
                            <span>
                                <strong><?= h($doctor['name']) ?></strong>
                                <small><?= h($doctor['specialty_name']) ?> - <?= h($doctor['crm']) ?></small>
                            </span>
                        </summary>
                        <form method="post" class="subform" autocomplete="off">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="admin_update_doctor">
                            <input type="hidden" name="doctor_id" value="<?= (int) $doctor['id'] ?>">
                            <input type="hidden" name="page_after" value="admin_doctors">
                            <?php render_doctor_fields($clinics, $specialties, $doctor, false); ?>
                            <button class="button small" type="submit">Salvar</button>
                        </form>

                        <div class="split-line"></div>
                        <h3>Atendimento semanal</h3>
                        <div class="chips">
                            <?php foreach (doctor_schedules((int) $doctor['id']) as $schedule): ?>
                                <form method="post" class="chip-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_delete_schedule">
                                    <input type="hidden" name="schedule_id" value="<?= (int) $schedule['id'] ?>">
                                    <input type="hidden" name="page_after" value="admin_doctors">
                                    <button class="chip" type="submit">
                                        <?= h(weekday_name((int) $schedule['weekday'])) ?> <?= h(substr($schedule['start_time'], 0, 5)) ?>-<?= h(substr($schedule['end_time'], 0, 5)) ?> x
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" class="filters compact">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="admin_add_schedule">
                            <input type="hidden" name="doctor_id" value="<?= (int) $doctor['id'] ?>">
                            <input type="hidden" name="page_after" value="admin_doctors">
                            <label>Dia
                                <select name="weekday">
                                    <?php for ($i = 0; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>"><?= h(weekday_name($i)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label>Início <input type="time" name="start_time" required></label>
                            <label>Fim <input type="time" name="end_time" required></label>
                            <button class="button small" type="submit">Adicionar</button>
                        </form>

                        <div class="split-line"></div>
                        <h3>Bloqueios</h3>
                        <div class="chips">
                            <?php foreach (doctor_blocks((int) $doctor['id']) as $block): ?>
                                <span class="chip muted-chip">
                                    <?= h(format_date($block['block_date'])) ?> <?= h(substr($block['start_time'], 0, 5)) ?>-<?= h(substr($block['end_time'], 0, 5)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" class="filters compact">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="admin_add_block">
                            <input type="hidden" name="doctor_id" value="<?= (int) $doctor['id'] ?>">
                            <input type="hidden" name="page_after" value="admin_doctors">
                            <label>Data <input type="date" name="block_date" min="<?= h(current_date_value()) ?>" required></label>
                            <label>Início <input type="time" name="start_time" required></label>
                            <label>Fim <input type="time" name="end_time" required></label>
                            <label>Motivo <input name="reason"></label>
                            <button class="button small" type="submit">Bloquear</button>
                        </form>

                        <form method="post" class="danger-zone">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="admin_delete_doctor">
                            <input type="hidden" name="doctor_id" value="<?= (int) $doctor['id'] ?>">
                            <input type="hidden" name="page_after" value="admin_doctors">
                            <button class="button danger" type="submit" data-confirm="Remover médico da agenda?">Remover médico</button>
                        </form>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Gestão de acessos</h2>
        <p class="muted">Conceda ou revogue o perfil de Administrador para os usuários do sistema. Apenas administradores podem realizar esta ação.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>CRM / Especialidade</th>
                        <th>Perfil</th>
                        <th>Acesso ADM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $person): ?>
                        <?php $isSelf = (int) $person['id'] === (int) $currentUser['id']; ?>
                        <tr>
                            <td>
                                <?= h($person['name']) ?>
                                <?php if ($isSelf): ?><small>(você)</small><?php endif; ?>
                            </td>
                            <td><?= h($person['email']) ?></td>
                            <td><?= h($person['crm'] ? $person['crm'] . ' - ' . $person['specialty_name'] : '-') ?></td>
                            <td>
                                <span class="status <?= $person['is_admin'] ? 'confirmed' : 'pending' ?>">
                                    <?= $person['is_admin'] ? 'Administrador' : 'Médico' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isSelf): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <form method="post" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="admin_update_user_role">
                                        <input type="hidden" name="user_id" value="<?= (int) $person['id'] ?>">
                                        <input type="hidden" name="role" value="<?= $person['is_admin'] ? 'doctor' : 'admin' ?>">
                                        <input type="hidden" name="page_after" value="admin_doctors">
                                        <label class="switch" data-confirm="<?= $person['is_admin']
                                            ? h('Remover privilégio de administrador de ' . $person['name'] . '?')
                                            : h('Conceder privilégio de administrador para ' . $person['name'] . '?') ?>">
                                            <input type="checkbox" data-role-switch <?= $person['is_admin'] ? 'checked' : '' ?>>
                                            <span class="switch-track"></span>
                                        </label>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$staff): ?>
                        <tr><td colspan="5" class="muted">Nenhum usuário encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}
function render_doctor_fields(array $clinics, array $specialties, array $doctor = [], bool $includeEmail = true): void
{
    ?>
    <div class="grid two">
        <label>Nome <input name="name" value="<?= h($doctor['name'] ?? '') ?>" autocomplete="off" required></label>
        <?php if ($includeEmail): ?>
            <label>E-mail <input type="email" name="email" value="<?= h($doctor['email'] ?? '') ?>" autocomplete="off" required></label>
            <label>Senha inicial <input type="password" name="password" minlength="6" placeholder="Padrão: 123456" autocomplete="new-password"></label>
        <?php endif; ?>
        <label>Telefone <input name="phone" value="<?= h($doctor['phone'] ?? '') ?>"></label>
        <label>CRM <input name="crm" value="<?= h($doctor['crm'] ?? '') ?>" required></label>
        <label>Duração
            <select name="appointment_duration">
                <?php foreach ([20, 30, 40, 45, 60] as $duration): ?>
                    <option value="<?= $duration ?>" <?= (int) ($doctor['appointment_duration'] ?? 30) === $duration ? 'selected' : '' ?>>
                        <?= $duration ?> min
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Clínica
            <select name="clinic_id" required>
                <?php foreach ($clinics as $clinic): ?>
                    <option value="<?= (int) $clinic['id'] ?>" <?= (int) ($doctor['clinic_id'] ?? 0) === (int) $clinic['id'] ? 'selected' : '' ?>>
                        <?= h($clinic['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Especialidade
            <select name="specialty_id" required>
                <?php foreach ($specialties as $specialty): ?>
                    <option value="<?= (int) $specialty['id'] ?>" <?= (int) ($doctor['specialty_id'] ?? 0) === (int) $specialty['id'] ? 'selected' : '' ?>>
                        <?= h($specialty['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label>Bio <textarea name="bio" rows="3"><?= h($doctor['bio'] ?? '') ?></textarea></label>
    <?php
}