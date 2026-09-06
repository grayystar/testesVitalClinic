<?php

function render_consultas(array $paciente, array $consultas): void
{
    render_header($paciente, 'consultas');
    ?>
    <h1>Minhas consultas</h1>
    <p class="text-muted" style="margin-bottom: 20px;">Suas próximas consultas agendadas</p>

    <?php if (empty($consultas)): ?>
        <div class="card">
            <p style="margin-bottom: 12px;">Você não tem nenhuma consulta futura marcada.</p>
            <a href="<?= h(app_url(['page' => 'agendar'])) ?>" class="btn btn-primary">
                Agendar consulta
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($consultas as $consulta): ?>
            <!-- repara: não precisamos mais do "as $indice => $consulta" nem do
                 "if ($indice < count(...) - 1)" pra controlar a linha divisória,
                 cada consulta agora é seu próprio cartão fechado, então esse
                 controle manual não faz mais falta. Código mais simples! -->

            <div class="appointment-card">
                <div class="appointment-card-top">
                    <span class="badge"><?= h(status_label($consulta['status'])) ?></span>

                    <form method="post" action="<?= h(app_url()) ?>" onsubmit="return confirm('Tem certeza que deseja cancelar essa consulta?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancelar_consulta">
                        <input type="hidden" name="consulta_id" value="<?= (int) $consulta['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                    </form>
                </div>

                <div class="appointment-doctor"><?= h($consulta['doctor_name']) ?></div>
                <div class="appointment-meta">
                    <?= h($consulta['specialty_name']) ?> · <?= h($consulta['clinic_name']) ?> · <?= h(format_datetime($consulta['slot_start'])) ?>
                </div>
                <!-- juntei especialidade, clínica E data/hora numa linha só (com
                     "·" separando), em vez de duas linhas separadas, menos
                     informação espalhada verticalmente, mais fácil de escanear
                     com o olho -->
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    render_footer();
}