<?php

function render_historico(array $paciente, array $consultas): void
{
    render_header($paciente, 'historico');
    ?>
    <h1>Histórico de consultas</h1>
    <p class="text-muted" style="margin-bottom: 20px;">Suas consultas já finalizadas ou canceladas</p>

    <?php if (empty($consultas)): ?>
        <div class="card">
            <p>Você ainda não tem nenhuma consulta no histórico.</p>
        </div>
    <?php else: ?>
        <?php foreach ($consultas as $consulta): ?>
            <div class="appointment-card">
                <div class="appointment-card-top">
                    <span class="badge"><?= h(status_label($consulta['status'])) ?></span>
                </div>
                <!-- aqui não tem um segundo item na fileira de cima (não tem
                     botão nenhum, essa tela é só de leitura), então o badge
                     simplesmente fica sozinho na esquerda, o
                     "justify-content: space-between" não reclama de ter só
                     um item, ele só não tem ninguém pra empurrar pra direita -->

                <div class="appointment-doctor"><?= h($consulta['doctor_name']) ?></div>
                <div class="appointment-meta">
                    <?= h($consulta['specialty_name']) ?> · <?= h($consulta['clinic_name']) ?> · <?= h(format_datetime($consulta['slot_start'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    render_footer();
}