<?php

function render_notificacoes(array $paciente, array $notificacoes): void
{
    render_header($paciente, 'notificacoes');
    ?>
    <h1>Notificações</h1>
    <p class="text-muted" style="margin-bottom: 20px;">Avisos sobre suas consultas</p>

    <?php if (empty($notificacoes)): ?>
        <div class="card">
            <p>Você não tem nenhuma notificação.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notificacoes as $notificacao): ?>
            <div class="appointment-card">
                <?php if ($notificacao['status'] === 'sent'): ?>
                    <div class="appointment-card-top">
                        <span class="badge">Nova</span>
                    </div>
                <?php endif; ?>
                <!-- o selinho "Nova" só desenha (e só cria a fileira de cima)
                     quando a notificação ainda estava 'sent' no momento em que
                     buscamos a lista. Se já tiver sido lida antes, esse "if"
                     inteiro é pulado, e o cartão começa direto pelo título -->

                <div class="appointment-doctor"><?= h($notificacao['title']) ?></div>
                <div class="appointment-meta"><?= h($notificacao['message']) ?></div>
                <div class="appointment-meta"><?= h(format_datetime($notificacao['sent_at'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    render_footer();
}