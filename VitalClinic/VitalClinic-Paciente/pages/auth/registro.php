<?php

// render_registro() desenha a tela de "Criar conta", é a nossa segunda View de verdade
function render_registro(): void
{
    render_header(null, 'registro');
    //   mesma lógica do login: passamos null como paciente, porque quem está vendo essa
    //   tela ainda não tem conta nenhuma, então a barra de navegação/Sair não aparece
    ?>
    <div class="card" style="max-width: 480px; margin: 40px auto;">
        <!--  um pouquinho mais larga que o card do login (480px em vez de 400px), porque
             esse formulário tem mais campos e ficaria apertado demais na largura menor -->

        <div class="form-title">Criar conta</div>
        <div class="form-subtitle">Cadastre-se para agendar suas consultas</div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="registro">
            <!-- esse "action" precisa bater EXATAMENTE com o texto que a gente vai
                 conferir no index.php daqui a pouco (if ($action === 'registro')) -->

            <label class="field-label">Nome completo</label>
            <input type="text" name="name" class="field-input" required>
            <!-- o "name" desse input precisa ser exatamente "name", porque é essa a
                 chave que o register_patient() vai procurar dentro de $data['name'] -->

            <label class="field-label">E-mail</label>
            <input type="email" name="email" class="field-input" required>

            <label class="field-label">Senha</label>
            <input type="password" name="password" class="field-input" minlength="6" required>
            <!-- minlength="6" é uma validação do próprio navegador: ele já avisa a pessoa
                 antes de nem tentar enviar, se a senha tiver menos de 6 caracteres. Mas
                 repara que isso é só conforto, o register_patient() confere de novo
                 (strlen(...) < 6) do lado do servidor, porque validação só no navegador
                 pode ser burlada por alguém mandando a requisição de outro jeito -->

            <label class="field-label">Telefone</label>
            <input type="text" name="phone" class="field-input" placeholder="(11) 90000-0000">
            <!-- sem "required": olhando o register_patient(), phone é opcional
                 ($data['phone'] ?: null), se vier vazio, vira null no banco -->

            <label class="field-label">CPF</label>
            <input type="text" name="document" class="field-input" placeholder="000.000.000-00">

            <label class="field-label">Data de nascimento</label>
            <input type="date" name="birth_date" class="field-input">
            <!-- type="date" faz o navegador mostrar um calendariozinho pra escolher a data,
                 em vez da pessoa ter que digitar no formato certo na mão -->

            <label class="field-label">Endereço</label>
            <input type="text" name="address" class="field-input">

            <button type="submit" class="btn btn-primary" style="width: 100%;">Criar conta</button>
        </form>

        <p class="text-center" style="margin-top: 12px; font-size: 13px;">
            <a href="<?= h(app_url(['page' => 'login'])) ?>"
               style="color: var(--color-primary); font-weight: 700; text-decoration: none;">
                Já tem conta? Entrar
            </a>
        </p>
        <!-- link de volta pro login, pra quem clicou em "Criar conta" sem querer,
             ou já tem cadastro. var(--color-primary) funciona aqui mesmo sendo um
             "style" solto no HTML, porque variável de CSS vale em qualquer lugar da
             página, não só dentro do arquivo paciente.css -->
    </div>
    <?php
    render_footer();
}