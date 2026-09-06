<?php

/**
 * Componente reaproveitável de calendário: desenha o cabeçalho do mês,
 * a legenda de status e a grade de dias com os compromissos.
 * Usado tanto pelo painel do Administrador quanto pelo painel do Médico
 * — cada um só monta os dados (já filtrados) e os links de navegação
 * antes de chamar esta função, sem duplicar HTML/CSS.
 */
function render_calendar_component(string $navPage, DateTime $first, DateTime $prev, DateTime $next, array $calendar, array $extraLinkParams = []): void
{
    $daysInMonth = (int) $first->format('t');
    $offset = (int) $first->format('w');
    // O destino do clique em cada consulta muda conforme o painel: o
    // Admin tem acesso à tela geral de consultas (com filtro por
    // médico); o Médico é levado para a SUA própria tela de consultas,
    // que já se autofiltra pela sessão — nunca pelo doctor_id da URL.
    $eventPage = $navPage === 'doctor_calendar' ? 'doctor_appointments' : 'admin_appointments';
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow"><?= h($navPage === 'doctor_calendar' ? 'Médico' : 'Administração') ?></p>
            <h1>Calendário de consultas</h1>
        </div>
        <div class="actions">
            <a class="button small" href="<?= h(app_url(array_merge(['page' => $navPage, 'month' => $prev->format('n'), 'year' => $prev->format('Y')], $extraLinkParams))) ?>">Mês anterior</a>
            <a class="button small" href="<?= h(app_url(array_merge(['page' => $navPage, 'month' => $next->format('n'), 'year' => $next->format('Y')], $extraLinkParams))) ?>">Próximo mês</a>
        </div>
    </section>

    <section class="panel">
        <h2><?= h($first->format('m/Y')) ?></h2>
        <div class="calendar-legend">
            <span><i class="dot confirmed-dot"></i> Confirmadas</span>
            <span><i class="dot pending-dot"></i> Pendentes</span>
            <span><i class="dot completed-dot"></i> Concluídas</span>
            <span><i class="dot cancelled-dot"></i> Canceladas</span>
            <span><i class="dot no_show-dot"></i> Não compareceu</span>
        </div>
        <div class="calendar-scroll">
            <div class="calendar-grid">
                <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $dayName): ?>
                    <strong class="calendar-weekday"><?= h($dayName) ?></strong>
                <?php endforeach; ?>
                <?php for ($i = 0; $i < $offset; $i++): ?>
                    <div class="calendar-day muted-day"></div>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <div class="calendar-day">
                    <strong><?= $day ?></strong>
                    <?php foreach (array_slice($calendar[$day] ?? [], 0, 4) as $appointment): ?>
                        <?php
                        $eventLinkParams = ['page' => $eventPage, 'date' => (new DateTime($appointment['slot_start']))->format('Y-m-d')];
                        if ($eventPage === 'admin_appointments') {
                            $eventLinkParams['doctor_id'] = $appointment['doctor_id'];
                        }
                        ?>
                        <a class="calendar-event <?= h($appointment['status']) ?>" href="<?= h(app_url($eventLinkParams)) ?>">
                            <?= h(format_time($appointment['slot_start'])) ?> <?= h($appointment['patient_name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($calendar[$day] ?? []) > 4): ?>
                        <span class="muted">+ <?= count($calendar[$day]) - 4 ?> consultas</span>
                    <?php endif; ?>
                    <?php if (empty($calendar[$day])): ?>
                        <span class="muted">Sem consultas</span>
                    <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    <?php
}

function render_admin_calendar(): void
{
    $month = (int) ($_GET['month'] ?? (new DateTime())->format('n'));
    $year = (int) ($_GET['year'] ?? (new DateTime())->format('Y'));
    if ($month < 1 || $month > 12) {
        $month = (int) (new DateTime())->format('n');
    }
    if ($year < 2000 || $year > 2100) {
        $year = (int) (new DateTime())->format('Y');
    }

    $first = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $prev = (clone $first)->modify('-1 month');
    $next = (clone $first)->modify('+1 month');
    $calendar = calendar_appointments($year, $month);

    render_calendar_component('admin_calendar', $first, $prev, $next, $calendar);
}
