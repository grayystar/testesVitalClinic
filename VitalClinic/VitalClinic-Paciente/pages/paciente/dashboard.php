<?php

// render_dashboard() desenha a tela de início do paciente logado
function render_dashboard(array $paciente, ?array $proximaConsulta): void
{
    //   repara nos dois parâmetros: $paciente é obrigatório (sempre vai ter alguém
    //   logado pra ver essa tela). $proximaConsulta é "?array", pode ser um array
    //   (achou uma consulta) ou null (não achou nenhuma), porque é exatamente isso
    //   que next_appointment_for_patient() pode devolver

    render_header($paciente, 'dashboard');
    ?>
    <h1>Olá, <?= h(explode(' ', $paciente['name'])[0]) ?>!</h1>
    <!-- explode(' ', $paciente['name']) quebra o nome completo em pedaços, separando
         por espaço, igual vimos no config() com o ponto. Se o nome for "Paciente de
         Demonstração", vira o array ['Paciente', 'de', 'Demonstração']. O [0] no final
         pega só o PRIMEIRO pedaço desse array, ou seja, só o primeiro nome, pra
         saudação ficar mais "de conversa" (Olá, Paciente!) em vez do nome inteiro -->

    <p class="text-muted" style="margin-bottom: 20px;">O que você precisa hoje?</p>

    <div class="card">
        <?php if ($proximaConsulta === null): ?>
            <!-- caminho 1: o paciente não tem nenhuma consulta futura -->
            <p style="margin-bottom: 12px;">Você não tem nenhuma consulta agendada.</p>
            <a href="<?= h(app_url(['page' => 'agendar'])) ?>" class="btn btn-primary">
                Agendar consulta
            </a>
        <?php else: ?>
            <!-- caminho 2: existe uma próxima consulta pra mostrar -->
            <div class="appointment-highlight">
                <div class="appointment-highlight-info">
                    <span class="badge"><?= h(status_label($proximaConsulta['status'])) ?></span>
                    <!-- status_label() já existia lá no helpers.php: traduz
                         'pending'/'confirmed' pro texto em português que o paciente entende -->

                    <div class="appointment-doctor"><?= h($proximaConsulta['doctor_name']) ?></div>

                    <div class="appointment-meta">
                        <?= h($proximaConsulta['specialty_name']) ?> · <?= h($proximaConsulta['clinic_name']) ?>
                    </div>

                    <div class="appointment-meta">
                        <?= h(format_datetime($proximaConsulta['slot_start'])) ?>
                    </div>
                    <!-- format_datetime() também já existia: transforma a data "crua"
                         do banco (tipo 2026-09-05 14:00:00) no formato brasileiro
                         (05/09/2026 14:00) -->
                </div>

                <a href="<?= h(app_url(['page' => 'consultas'])) ?>" class="btn btn-outline">
                    Ver detalhes
                </a>
            </div>
        <?php endif; ?>
    </div>


       <div class="shortcut-grid">
    <!-- essa div é o "flex container" (display: flex lá no CSS) que organiza os
         quatro cartões de atalho lado a lado, dividindo o espaço igualmente entre
         eles (por causa do "flex: 1" que cada .shortcut-card tem) e com um espaço
         de 16px entre um e outro (o "gap: 16px") -->

    <a href="<?= h(app_url(['page' => 'agendar'])) ?>" class="shortcut-card">
        Agendar consulta
    </a>
    <!-- o cartão inteiro é um único link <a>, a pessoa pode clicar em qualquer
         parte dele (não só no texto), porque o <a> "embrulha" o conteúdo inteiro.

         href="< ?= h(app_url(['page' => 'agendar'])) ?>"
             app_url(['page' => 'agendar']) monta o texto "index.php?page=agendar"
             h() escapa esse valor antes de imprimir (hábito de segurança, mesmo
             sendo um valor que a gente mesmo gerou)

         class="shortcut-card"
             aplica todo aquele estilo que já existe no CSS: fundo branco,
             cantos arredondados, sombra sutil, texto centralizado, e o efeitinho
             de "levantar" (:hover) quando o mouse passa em cima

         "Agendar consulta"
             o texto que fica visível dentro do cartão, entre a tag de abertura
             <a ...> e a de fechamento </a> -->

    <a href="<?= h(app_url(['page' => 'consultas'])) ?>" class="shortcut-card">
        Minhas consultas
    </a>
    <!--  mesma estrutura de cima, só mudando o destino (page=consultas) e o
         texto mostrado -->

    <a href="<?= h(app_url(['page' => 'historico'])) ?>" class="shortcut-card">
        Histórico
    </a>
    <!--  idem, apontando pra page=historico -->

    <a href="<?= h(app_url(['page' => 'perfil'])) ?>" class="shortcut-card">
        Meu perfil
    </a>
    <!--  idem, apontando pra page=perfil -->
</div>
<!--  fecha a div do grid. Repara que os quatro <a> são "irmãos" (mesmo nível),
     um do lado do outro, é o CSS "display: flex" do .shortcut-grid que decide
     como eles se organizam na tela, o HTML aqui só declara que elementos existem -->
     
    <!-- os quatro atalhos apontam pra páginas que ainda NÃO existem de verdade
         (consultas, agendar, historico, perfil), vamos construí-las nas próximas
         etapas. Clicar neles agora vai cair no tratamento novo que vamos colocar
         no index.php, que redireciona de volta pro dashboard -->
    <?php
    render_footer();
}