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
            <p>Informe o e-mail cadastrado. Se ele existir em nossa base e tiver uma pergunta de segurança cadastrada, você poderá respondê-la para redefinir sua senha.</p>
        </div>
        <form method="post" class="panel form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request_password_reset">
            <input type="hidden" name="page_after" value="forgot_password">
            <h2>Recuperar senha</h2>
            <label>E-mail cadastrado <input type="email" name="email" required autocomplete="email" autofocus></label>
            <button class="button primary" type="submit">Continuar</button>
            <a class="muted-link" href="<?= h(app_url(['page' => 'login'])) ?>">Voltar para o login</a>
        </form>
    </section>
    <?php
}

/**
 * Passo 2 — o usuário responde a pergunta de segurança cadastrada no
 * perfil dele. Se a conta não existir, não for elegível, ou não tiver
 * pergunta de segurança cadastrada, exibimos uma mensagem genérica em
 * vez do formulário (sem revelar qual desses três é o caso, para não
 * permitir enumeração de contas).
 * POST -> action=verify_security_answer.
 */
function render_reset_security_question(): void
{
    $pending = password_reset_pending();
    $question = $pending['security_question'] ?? '';
    $available = password_reset_has_security_question();
    ?>
    <section class="auth-grid">
        <div class="auth-copy">
            <img class="auth-logo" src="<?= asset_url('assets/brand/vital-clinic-logo.svg') ?>" alt="Vital Clinic">
            <h1>Pergunta de segurança</h1>
            <?php if ($available): ?>
                <p>Responda à pergunta de segurança cadastrada no seu perfil para continuar a redefinição de senha.</p>
            <?php else: ?>
                <p>Não foi possível continuar com este e-mail. Verifique se ele está correto ou se há uma pergunta de segurança cadastrada no perfil da conta.</p>
            <?php endif; ?>
        </div>
        <?php if ($available): ?>
            <form method="post" class="panel form-card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify_security_answer">
                <input type="hidden" name="page_after" value="reset_security_question">
                <h2>Confirme sua identidade</h2>
                <label>
                    <?= h($question) ?>
                    <input type="text" name="answer" required autofocus autocomplete="off">
                </label>
                <button class="button primary" type="submit">Confirmar resposta</button>
                <a class="muted-link" href="<?= h(app_url(['page' => 'forgot_password'])) ?>">Usar outro e-mail</a>
            </form>
        <?php else: ?>
            <div class="panel form-card">
                <h2>Não foi possível continuar</h2>
                <p class="muted">Se você é administrador ou médico e ainda não cadastrou uma pergunta de segurança, peça a outro administrador para redefinir sua senha, ou cadastre a pergunta em "Meu perfil" assim que conseguir acessar o sistema novamente.</p>
                <a class="button primary" href="<?= h(app_url(['page' => 'forgot_password'])) ?>">Tentar outro e-mail</a>
                <a class="muted-link" href="<?= h(app_url(['page' => 'login'])) ?>">Voltar para o login</a>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Passo 3 — pergunta de segurança respondida corretamente; usuário
 * cadastra a nova senha.
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
