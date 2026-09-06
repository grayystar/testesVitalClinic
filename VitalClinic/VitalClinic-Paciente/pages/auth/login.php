<?php

function render_login(): void
{
    render_header(null, 'login');
    //   chama a função que constrói o "topo" da página (doctype, head, CSS, barra de
    //   navegação). Passamos null como paciente, porque ninguém está logado ainda,
    //   lembra que dentro de render_header(), o "if ($paciente !== null)" faz a barra
    //   de navegação e o botão Sair nem aparecerem nesse caso, só o logo sozinho
    ?>
    <div class="card" style="max-width: 400px; margin: 40px auto;">
        <!-- o mesmo .card que testamos no teste_css.html, só limitando a largura
             (max-width) e centralizando (margin: 40px auto), porque um formulário de
             login não precisa ocupar a tela inteira igual o dashboard vai ocupar -->

        <div class="form-title">Entrar</div>
        <div class="form-subtitle">Acesse sua conta de paciente</div>
        <!--  essas duas classes já existiam prontas no CSS, só precisávamos usar -->

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">

            <label class="field-label">E-mail</label>
            <input type="email" name="email" class="field-input" required>

            <label class="field-label">Senha</label>
            <input type="password" name="password" class="field-input" required>
            <!-- trocamos os <label>Texto <input></label> de antes por label e input
                 separados, um embaixo do outro, porque o CSS .field-label usa
                 "display: block" pra forçar essa quebra de linha. Continua sendo o
                 mesmo <label>, só reorganizado -->

            <button type="submit" class="btn btn-primary" style="width: 100%;">Entrar</button>
            <!-- repara que usamos duas classes juntas: "btn" (o formato base, que
                 todo botão tem) e "btn-primary" (a cor teal cheia por cima) -->
        </form>

            <p class="text-center" style="margin-top: 12px; font-size: 13px;">
            <a href="<?= h(app_url(['page' => 'registro'])) ?>"
               style="color: var(--color-primary); font-weight: 700; text-decoration: none;">
                Não tem conta? Criar conta
            </a>
        </p>
        <!-- é exatamente o mesmo padrão do link que colocamos no registro.php, só
             que invertido: aqui aponta para registro, lá apontava para login -->

    </div>
    <?php
    render_footer();
    //   fecha a página (fecha o .container, </body>, </html>), sem essa chamada, o
    //   HTML ficaria com tags abertas e o navegador ia "adivinhar" onde fechar, o que
    //   quase sempre bagunça o layout
}