<?php

/**
 * Calendário do painel do Médico — reaproveita a mesma estrutura visual
 * e a mesma função de componente (render_calendar_component) usada no
 * painel do Administrador, garantindo consistência visual entre os dois.
 *
 * A diferença central está na FILTRAGEM: aqui, o médico logado é
 * resolvido a partir da sessão ($user), e o ID dele é repassado para
 * calendar_appointments(), que por sua vez filtra a consulta no
 * repositório (WHERE a.doctor_id = ?) — o médico nunca chega a receber,
 * nem em memória, compromissos de outros profissionais.
 */
function render_doctor_calendar(array $user): void
{
    $doctor = doctor_by_user((int) $user['id']);
    if (!$doctor) {
        echo '<section class="panel"><p>Médico não encontrado.</p></section>';
        return;
    }

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

    // Aqui está o filtro de segurança: passamos o ID do médico logado.
    $calendar = calendar_appointments($year, $month, (int) $doctor['id']);

    render_calendar_component('doctor_calendar', $first, $prev, $next, $calendar);
}
