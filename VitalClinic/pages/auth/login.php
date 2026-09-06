<?php

function render_login(): void
{
    ?>
    <section class="auth-grid">
        <div class="auth-copy">
            <img class="auth-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="Vital Clinic">
            <h1>A sua saúde em primeiro lugar!</h1>
            <p> Uma forma rápida e eficaz de deixar sua clínica organizada.</p>
        </div>
        <form method="post" class="panel form-card" id="login-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="page_after" value="login">
            <input type="hidden" name="role_context" id="role_context" value="admin">
            <h2>Entrar</h2>

            <div class="role-picker" role="group" aria-label="Tipo de acesso">
                <button type="button" class="role-option active" data-role="admin">Clínica</button>
                <button type="button" class="role-option" data-role="doctor">Médico</button>
            </div>

            <label>E-mail <input type="email" name="email" required autocomplete="email"></label>
            <label>Senha <input type="password" name="password" required autocomplete="current-password"></label>
            <button class="button primary" type="submit">Entrar</button>
            <a class="muted-link" href="<?= h(app_url(['page' => 'forgot_password'])) ?>">Esqueci minha senha</a>
            <p class="muted">Acesso exclusivo para administradores e médicos.</p>
        </form>
    </section>
    <?php
}
