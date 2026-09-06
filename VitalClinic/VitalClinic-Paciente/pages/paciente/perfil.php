<?php

function render_perfil(array $paciente): void
{
    render_header($paciente, 'perfil');
    ?>
    <h1>Meu perfil</h1>
    <p class="text-muted" style="margin-bottom: 20px;">Veja e edite suas informações</p>

    <div class="card">
        <form method="post" action="<?= h(app_url()) ?>" onsubmit="return confirm('Tem certeza que deseja fazer essas alterações?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="atualizar_perfil">

            <label class="field-label" for="name">Nome completo</label>
            <input class="field-input" type="text" id="name" name="name" value="<?= h($paciente['name']) ?>" required>

            <label class="field-label" for="email">E-mail</label>
            <input class="field-input" type="email" id="email" name="email" value="<?= h($paciente['email']) ?>" required>

            <label class="field-label" for="phone">Telefone</label>
            <input class="field-input" type="text" id="phone" name="phone" value="<?= h($paciente['phone'] ?? '') ?>">
            <!-- Repara que usei $paciente['phone'] ?? '' (e o mesmo pros outros campos opcionais): se esses dados 
             estiverem null no banco (pessoa nunca preencheu), o ?? troca por uma string vazia, evitando passar null 
             pro h(), o PHP moderno reclama (um aviso, não quebra o site) quando você tenta usar funções de texto 
             direto num valor null. -->

            <label class="field-label" for="document">CPF</label>
            <input class="field-input" type="text" id="document" name="document" value="<?= h($paciente['document'] ?? '') ?>">

            <label class="field-label" for="birth_date">Data de nascimento</label>
            <input class="field-input" type="date" id="birth_date" name="birth_date" value="<?= h($paciente['birth_date'] ?? '') ?>">
            <!-- type="date" faz o navegador mostrar um seletor de calendário
                 sozinho. O value precisa vir no formato AAAA-MM-DD pra ele
                 entender, que é exatamente o formato que o MySQL já devolve
                 pra colunas DATE, então não precisamos converter nada aqui -->

            <label class="field-label" for="address">Endereço</label>
            <input class="field-input" type="text" id="address" name="address" value="<?= h($paciente['address'] ?? '') ?>">

            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Salvar alterações</button>
        </form>
    </div>
    <?php
    render_footer();
}