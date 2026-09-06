<?php
declare(strict_types=1);
//   já vimos isso no index.php: liga o modo "estrito" de tipos do PHP, pra pegar erros
//   de tipo (ex.: passar um número onde se espera um texto) o quanto antes
 
function render_header(?array $paciente, string $paginaAtual = ''): void
{
    //   os parâmetros dessa função: $paciente é o array com os dados do paciente logado
    //   (ou null, se ninguém estiver logado, o "?" antes de "array" permite isso).
    //   $paginaAtual é um texto simples, tipo 'dashboard' ou 'consultas', usado só pra
    //   saber qual link da barra de navegação deve ficar destacado. Ele tem um valor
    //   padrão (''), então é opcional chamar a função sem informar isso

    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- essa segunda tag é o que faz o site se ajustar direito em celular/tablet,
             sem ela, o navegador do celular tentaria mostrar a página "encolhida",
             como se fosse desktop, ficando ilegível -->

        <title>Vital Clinic — Paciente</title>

        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= h(asset_url('assets/css/paciente.css')) ?>">
        <!-- lembra do asset_url() que comentamos no helpers.php. Ele gruda um "?v=123"
             no final do link, baseado na data de modificação do arquivo, assim, se você
             editar o CSS depois, o navegador busca a versão nova, em vez de usar uma
             cópia antiga guardada em cache. E passamos por h() por costume de segurança,
             mesmo sendo um valor que nós mesmos geramos -->
    </head>
    <body>

    <div class="topbar">
        <div class="topbar-logo">Vital Clinic</div>
        <?php if ($paciente !== null): ?>
            <!-- esse "if" só desenha o menu de navegação e o botão Sair quando existe
                 alguém logado. Na tela de login, por exemplo, vamos chamar essa função
                 passando null aqui, e essa parte inteira simplesmente não aparece -->

            <!-- um array associativo "dicionário": de um lado o slug da página (o
            valor que vai em ?page=...), do outro o texto que aparece pro usuário -->
            <nav class="topbar-nav">
                <?php
                $links = [
                    'dashboard'  => 'Início',
                    'consultas'  => 'Consultas',
                    'agendar'    => 'Agendar',
                    'historico'  => 'Histórico',
                    'notificacoes'  => 'Notificações',
                    'perfil'     => 'Perfil',
                ];
                // 'notificacoes' => 'Notificações', (coloquei antes do "Perfil", mas a ordem é só visual,
                //  pode colocar em qualquer posição da lista, o menu vai desenhar nessa mesma ordem). Como esse
                //  array já é percorrido num foreach que gera o link e confere se é a página atual pra destacar 
                // (o $classe = ... ? 'active' : ''), não precisa mexer em mais nada, só de acrescentar essa linha,
                //  o link novo já aparece no menu, já aponta pro lugar certo (?page=notificacoes), e já fica
                //  destacado quando a pessoa estiver nessa tela.
               

                foreach ($links as $slug => $rotulo):
                    $classe = ($paginaAtual === $slug) ? 'active' : '';
                    //   operador ternário de novo: se o slug desse link for igual à
                    //   página atual, ele ganha a classe "active" (que no CSS deixa o
                    //   fundo teal clarinho); senão, fica sem classe nenhuma
                    ?>
                    <a href="<?= h(app_url(['page' => $slug])) ?>" class="<?= h($classe) ?>">
                        <?= h($rotulo) ?>
                    </a>
                    <!-- repara nos três lugares usando h(): o link, a classe e o texto.
                         O slug e o rótulo aqui são valores que nós escrevemos no array
                         acima (não vêm do usuário), mas manter o hábito de sempre passar
                         por h() evita esquecer de escapar em algum lugar que realmente
                         importa depois -->
                <?php endforeach; ?>
            </nav>

            <form method="post" action="<?= h(app_url()) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn-sair">Sair</button>
            </form>
            <!-- o botão Sair precisa ser um formulário (não um link <a> comum), porque
                 sair da conta é uma ação que muda o estado do sistema (destrói a sessão),
                 e a gente já viu que ações assim devem ser POST, com token CSRF, o
                 mesmo cuidado que tomamos com o login -->
        <?php endif; ?>
    </div>

    <div class="container" style="padding-top: 24px; padding-bottom: 40px;">
        <?php foreach (take_flash() as $mensagem): ?>
            <div class="flash-<?= h($mensagem['type']) ?>">
                <?= h($mensagem['message']) ?>
            </div>
            <!-- isso conecta direto com o flash()/take_flash() do helpers.php: cada
                 mensagem guardada tem um 'type' ('success' ou 'error') e uma 'message'.
                 Usando o type dentro de class="flash-< ?= ... ? >", a gente aproveita as
                 classes .flash-success e .flash-error que já existem no CSS, se o type
                 for 'error', vira class="flash-error" automaticamente -->
        <?php endforeach; ?>
    <?php
    //   a função termina aqui, de propósito, sem fechar a </div> do .container.
    //   Isso é intencional: o conteúdo específico de cada tela (o formulário de login,
    //   a lista de consultas, etc.) vai ser desenhado depois dessa chamada, ainda
    //   "dentro" dessa div. Quem fecha ela é a render_footer(), lá embaixo
}

function render_footer(): void
{
    ?>
    </div>
    <!-- fecha o .container que a render_header() deixou aberto -->
    </body>
    </html>
    <?php
}