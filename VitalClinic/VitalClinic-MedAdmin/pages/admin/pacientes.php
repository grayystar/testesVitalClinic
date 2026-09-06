<?php

function render_admin_patients(): void
{
    $patientId = (int) ($_GET['patient_id'] ?? 0);
    $search = trim((string) ($_GET['q'] ?? ''));
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">Administração</p>
            <h1>Pacientes</h1>
        </div>
    </section>
    <section class="grid two">
        <div class="panel">
            <h2>Lista de pacientes</h2>
            <form method="get" class="filters">
                <input type="hidden" name="page" value="admin_patients">
                <label>Buscar paciente
                    <span class="search-field">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input name="q" value="<?= h($search) ?>" placeholder="Nome, e-mail, telefone ou CPF">
                    </span>
                </label>
                <button class="button" type="submit">Buscar</button>
            </form>
            <div class="list">
                <?php $filteredPatients = patient_list($search); ?>
                <?php if (!$filteredPatients): ?>
                    <p class="muted">Nenhum paciente encontrado<?= $search !== '' ? ' para "' . h($search) . '"' : '' ?>.</p>
                <?php endif; ?>
                <?php foreach ($filteredPatients as $patient): ?>
                    <article class="list-row">
                        <div>
                            <strong><?= h($patient['name']) ?></strong>
                            <span><?= h($patient['email']) ?> - <?= (int) $patient['total_appointments'] ?> consultas - <?= (int) $patient['no_shows'] ?> faltas</span>
                        </div>
                        <a class="button small" href="<?= h(app_url(['page' => 'admin_patients', 'patient_id' => $patient['id']])) ?>">Histórico</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="panel">
            <h2>Paciente selecionado</h2>
            <?php
            if ($patientId) {
                $patient = repository_find_user($patientId);
                if ($patient && $patient['role'] !== 'patient') {
                    $patient = null;
                }
                if ($patient) {
                    ?>
                    <form method="post" class="subform">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="admin_update_patient">
                        <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                        <input type="hidden" name="page_after" value="admin_patients">
                        <div class="grid two">
                            <label>Nome <input name="name" value="<?= h($patient['name']) ?>" required></label>
                            <label>Telefone <input name="phone" value="<?= h($patient['phone']) ?>"></label>
                            <label>Documento <input name="document" value="<?= h($patient['document']) ?>"></label>
                            <label>Nascimento <input type="date" name="birth_date" value="<?= h($patient['birth_date']) ?>"></label>
                            <label>Endereço <input name="address" value="<?= h($patient['address'] ?? '') ?>"></label>
                            <label>Status
                                <select name="status">
                                    <option value="active" <?= $patient['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inactive" <?= $patient['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </label>
                        </div>
                        <button class="button small" type="submit">Salvar paciente</button>
                    </form>
                    <div class="split-line"></div>
                    <h2>Histórico</h2>
                    <?php
                    render_appointment_table(appointments_for_user($patient, 'history'), ['role' => 'admin', 'id' => 0], 'admin_patients');
                }
            } else {
                ?>
                <form method="post" class="subform">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_create_patient">
                    <input type="hidden" name="page_after" value="admin_patients">
                    <h2>Cadastrar paciente</h2>
                    <div class="grid two">
                        <label>Nome <input name="name" required></label>
                        <label>E-mail <input type="email" name="email" required></label>
                        <label>Senha inicial <input type="password" name="password" placeholder="Padrão: 123456"></label>
                        <label>Telefone <input name="phone"></label>
                        <label>Documento <input name="document"></label>
                        <label>Nascimento <input type="date" name="birth_date"></label>
                        <label>Endereço <input name="address"></label>
                        <label>Clínica
                            <select name="clinic_id">
                                <option value="">Sem preferência</option>
                                <?php foreach (clinics() as $clinic): ?>
                                    <option value="<?= (int) $clinic['id'] ?>"><?= h($clinic['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="button primary" type="submit">Cadastrar paciente</button>
                </form>
                <?php
            }
            ?>
        </div>
    </section>
    <?php
}