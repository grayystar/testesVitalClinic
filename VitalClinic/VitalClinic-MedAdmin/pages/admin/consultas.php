<?php

/** Lista de consultas com filtros (data/status/médico) e o modal
 * "Adicionar Nova Consulta" (autocompletar de paciente/médico,
 * calendário visual de dias disponíveis e seleção de horário). */
function render_admin_appointments(): void
{
    $filters = [
        'date' => $_GET['date'] ?? '',
        'status' => $_GET['status'] ?? '',
        'doctor_id' => (int) ($_GET['doctor_id'] ?? 0),
    ];
    $appointments = appointments_for_admin($filters);
    $doctors = active_doctors();
    $patients = patient_list();
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Administração</p>
            <h1>Consultas</h1>
        </div>
        <button type="button" class="button primary" data-open-modal="new-appointment-modal">+ Adicionar Nova Consulta</button>
    </section>
    <section class="panel" data-appointments-panel>
        <form method="get" class="filters">
            <input type="hidden" name="page" value="admin_appointments">
            <label>Data <input type="date" name="date" value="<?= h($filters['date']) ?>"></label>
            <label>Status
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $status): ?>
                        <option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Médico
                <select name="doctor_id">
                    <option value="">Todos</option>
                    <?php foreach (active_doctors() as $doctor): ?>
                        <option value="<?= (int) $doctor['id'] ?>" <?= (int) $filters['doctor_id'] === (int) $doctor['id'] ? 'selected' : '' ?>>
                            <?= h($doctor['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button" type="submit">Filtrar</button>
        </form>
        <?php render_appointment_table($appointments, ['role' => 'admin', 'id' => 0], 'admin_appointments'); ?>
        <?php if (!empty($filters['date']) && !empty($filters['doctor_id'])): ?>
            <div class="split-line"></div>
            <h2>Agenda completa do médico</h2>
            <div class="timeline">
                <?php foreach (doctor_day_slots((int) $filters['doctor_id'], $filters['date']) as $slot): ?>
                    <div class="timeline-row <?= h($slot['status']) ?>">
                        <time><?= h(format_time($slot['slot_start'])) ?></time>
                        <span>
                            <?= h(status_label($slot['appointment_status'] ?: $slot['status'])) ?>
                            <?= $slot['patient_name'] ? ' - ' . h($slot['patient_name']) : '' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <dialog id="new-appointment-modal" class="modal modal-wide">
        <form method="post" class="panel form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_create_appointment">
            <input type="hidden" name="page_after" value="admin_appointments">
            <input type="hidden" name="slot_id" data-selected-slot value="">

            <div class="modal-head">
                <h2>Adicionar nova consulta</h2>
                <button type="button" class="modal-close" data-close-modal aria-label="Fechar">&times;</button>
            </div>

            <label>Paciente
                <div class="autocomplete" data-autocomplete>
                    <input
                        type="text"
                        class="autocomplete-input"
                        placeholder="Digite o nome, CPF ou telefone do paciente"
                        autocomplete="off"
                        data-autocomplete-search
                    >
                    <input type="hidden" name="patient_id" data-autocomplete-value>
                    <ul class="autocomplete-list" data-autocomplete-list hidden></ul>
                    <script type="application/json" data-autocomplete-options><?= json_encode(array_map(
                        static function (array $patient): array {
                            $sub = trim(($patient['document'] ? 'CPF ' . $patient['document'] : '') . ($patient['phone'] ? ' - ' . $patient['phone'] : ''));
                            return [
                                'id' => (int) $patient['id'],
                                'label' => $patient['name'],
                                'sub' => $sub,
                                'terms' => mb_strtolower($patient['name'] . ' ' . ($patient['document'] ?? '') . ' ' . ($patient['phone'] ?? '')),
                            ];
                        },
                        $patients
                    ), JSON_UNESCAPED_UNICODE) ?></script>
                </div>
            </label>

            <label>Médico
                <div class="autocomplete" data-autocomplete>
                    <input
                        type="text"
                        class="autocomplete-input"
                        placeholder="Digite o nome, CRM ou especialidade do médico"
                        autocomplete="off"
                        data-autocomplete-search
                    >
                    <input type="hidden" name="doctor_id" data-autocomplete-value data-appointment-doctor>
                    <ul class="autocomplete-list" data-autocomplete-list hidden></ul>
                    <script type="application/json" data-autocomplete-options><?= json_encode(array_map(
                        static function (array $doctor): array {
                            $sub = trim(($doctor['crm'] ? 'CRM ' . $doctor['crm'] : '') . ($doctor['specialty_name'] ? ' - ' . $doctor['specialty_name'] : ''));
                            return [
                                'id' => (int) $doctor['id'],
                                'label' => $doctor['name'],
                                'sub' => $sub,
                                'terms' => mb_strtolower($doctor['name'] . ' ' . ($doctor['crm'] ?? '') . ' ' . ($doctor['specialty_name'] ?? '')),
                            ];
                        },
                        $doctors
                    ), JSON_UNESCAPED_UNICODE) ?></script>
                </div>
            </label>

            <div class="appointment-date-grid">
                <div class="appointment-calendar" data-appt-calendar data-min-date="<?= h(current_date_value()) ?>" data-max-days="<?= (int) config('rules.booking_max_days') ?>">
                    <div class="appointment-calendar-head">
                        <button type="button" class="button ghost small" data-cal-prev aria-label="Mês anterior">‹</button>
                        <strong data-cal-label>&nbsp;</strong>
                        <button type="button" class="button ghost small" data-cal-next aria-label="Próximo mês">›</button>
                    </div>
                    <div class="appointment-calendar-weekdays">
                        <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
                    </div>
                    <div class="appointment-calendar-days" data-cal-days></div>
                    <p class="muted appointment-calendar-hint" data-cal-hint>Selecione o médico para ver os dias de atendimento.</p>
                    <input type="hidden" id="new-appointment-date">
                </div>

                <div class="appointment-date-side">
                    <label>Horário disponível
                        <div class="slot-picker" data-slot-loader data-date-input="new-appointment-date">
                            <span class="muted">Selecione o médico e a data para ver os horários.</span>
                        </div>
                    </label>

                    <label>Tipo de consulta
                        <select name="modality" required>
                            <option value="presencial">Presencial</option>
                            <option value="teleconsulta">Teleconsulta</option>
                        </select>
                    </label>

                    <label>Observações (opcional)
                        <textarea name="notes" rows="3" placeholder="Motivo da consulta, orientações, etc."></textarea>
                    </label>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="button ghost" data-close-modal>Cancelar</button>
                <button type="submit" class="button primary">Agendar consulta</button>
            </div>
        </form>
    </dialog>
    <?php
}