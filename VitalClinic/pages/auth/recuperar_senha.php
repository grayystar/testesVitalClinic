<?php

/**
 * Passo 1 — o usuário informa o e-mail cadastrado.
 * POST -> action=request_password_reset (ver index.php::handle_post()).
 */
function render_forgot_password(): void
{
    ?>
    <section class="auth-grid">
        <div class="auth-copy">
            <img class="auth-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="Vital Clinic">
            <h1>Esqueceu sua senha?</h1>
            <p>Informe o e-mail cadastrado. Se ele existir em nossa base, enviaremos um código de verificação de 5 dígitos para você redefinir a senha.</p>
        </div>
        <form method="post" class="panel form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request_password_reset">
            <input type="hidden" name="page_after" value="forgot_password">
            <h2>Recuperar senha</h2>
            <label>E-mail cadastrado <input type="email" name="email" required autocomplete="email" autofocus></label>
            <button class="button primary" type="submit">Enviar código</button>
            <a class="muted-link" href="<?= h(app_url(['page' => 'login'])) ?>">Voltar para o login</a>
            <?php if (config('mail.transport') === 'log'): ?>
                <p class="muted">Modo de desenvolvimento: nenhum e-mail real é enviado. O código gerado fica registrado em <code>storage/mail_outbox.log</code>. Configure <code>VCTCC_MAIL_TRANSPORT=smtp</code> para envio real.</p>
            <?php endif; ?>
        </form>
    </section>
    <?php
}

/**
 * Passo 2 — o usuário informa o código de 5 dígitos recebido por e-mail.
 * POST -> action=verify_reset_code.
 */
function render_reset_verify(): void
{
    $pending = password_reset_pending();
    $email = $pending['email'] ?? '';
    $codeLength = (int) (config('rules.password_reset_code_length') ?: 5);
    $expiresMinutes = (int) (config('rules.password_reset_expires_minutes') ?: 15);
    ?>
    <section class="auth-grid">
        <div class="auth-copy">
            <img class="auth-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="Vital Clinic">
            <h1>Confirme o código</h1>
            <p>
                Enviamos um código de <?= $codeLength ?> dígitos para
                <strong><?= h($email) ?></strong>. O código expira em <?= $expiresMinutes ?> minutos.
            </p>
        </div>
        <form method="post" class="panel form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_reset_code">
            <input type="hidden" name="page_after" value="reset_verify">
            <h2>Código de verificação</h2>
            <label>
                Código de <?= $codeLength ?> dígitos
                <input
                    type="text"
                    name="code"
                    class="code-input"
                    inputmode="numeric"
                    pattern="\d{<?= $codeLength ?>}"
                    minlength="<?= $codeLength ?>"
                    maxlength="<?= $codeLength ?>"
                    autocomplete="one-time-code"
                    required
                    autofocus
                >
            </label>
            <button class="button primary" type="submit">Validar código</button>
            <a class="muted-link" href="<?= h(app_url(['page' => 'forgot_password'])) ?>">Não recebi o código / usar outro e-mail</a>
        </form>
    </section>
    <?php
}

/**
 * Passo 3 — código já validado; usuário cadastra a nova senha.
 * POST -> action=reset_password.
 */
function render_reset_password(): void
{
    ?>
    <section class="auth-grid">
        <div class="auth-copy">
            <img class="auth-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="Vital Clinic">
            <h1>Defina sua nova senha</h1>
            <p>Escolha uma nova senha para acessar o painel.</p>
        </div>
        <form method="post" class="panel form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="page_after" value="reset_password">
            <h2>Nova senha</h2>
            <label>Nova senha <input type="password" name="password" minlength="6" required autocomplete="new-password" autofocus></label>
            <label>Confirmar nova senha <input type="password" name="confirm_password" minlength="6" required autocomplete="new-password"></label>
            <button class="button primary" type="submit">Salvar nova senha</button>
        </form>
    </section>
    <?php
}
