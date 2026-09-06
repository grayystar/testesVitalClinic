<?php

function render_agendar_medicos(array $paciente, array $medicos): void
{
    // TELA 1: lista de médicos pra escolher. Repara que passamos 'agendar'
    // como página atual mesmo aqui, pra o link "Agendar" ficar destacado no
    // menu (a gente reaproveita esse mesmo nome pras duas telas dessa seção)
    render_header($paciente, 'agendar');
    ?>
    <h1>Agendar consulta</h1>
    <p class="text-muted" style="margin-bottom: 20px;">Escolha um médico para ver os horários disponíveis</p>

    <?php if (empty($medicos)): ?>
        <div class="card">
            <p>Nenhum médico disponível no momento.</p>
        </div>
    <?php else: ?>
        <?php foreach ($medicos as $medico): ?>
            <div class="appointment-card">
                <div class="appointment-card-top">
                    <div class="appointment-doctor"><?= h($medico['doctor_name']) ?></div>
                    <a href="<?= h(app_url(['page' => 'agendar', 'medico' => $medico['doctor_id']])) ?>" class="btn btn-outline btn-sm">
                        Ver horários
                    </a>
                </div>
                <div class="appointment-meta">
                    <?= h($medico['specialty_name']) ?> · <?= h($medico['clinic_name']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    render_footer();
}

function render_agendar_horarios(array $paciente, array $medico, array $horarios, ?string $dataSelecionada, ?string $mesSelecionado): void
{
    // TELA 2: horários livres de UM médico específico já escolhido
    render_header($paciente, 'agendar');
    ?>
    <a href="<?= h(app_url(['page' => 'agendar'])) ?>" class="text-muted" style="display:inline-block;margin-bottom:12px;">
        ‹ Voltar para lista de médicos
    </a>

    <h1><?= h($medico['doctor_name']) ?></h1>
    <p class="text-muted" style="margin-bottom: 20px;">
        <?= h($medico['specialty_name']) ?> · <?= h($medico['clinic_name']) ?>
    </p>

    <?php if (empty($horarios)): ?>
        <div class="card">
            <p>Esse médico não tem horários livres no momento.</p>
        </div>
    <?php else: ?>
        <?php
            // agrupa os horários por data, igual antes, só que agora a CHAVE
            // do array é no formato "2026-09-20" (ano-mês-dia), em vez de
            // "20/09/2026". Esse formato (ano primeiro) é o "formato universal"
            // de data, mais fácil de comparar e de colocar numa URL sem
            // confusão. A gente só transforma isso no formato bonito (dd/mm)
            // na hora de mostrar pro usuário, mais abaixo
            $horariosPorData = [];
            foreach ($horarios as $horario) {
                $chaveData = (new DateTime($horario['slot_start']))->format('Y-m-d');
                $horariosPorData[$chaveData][] = $horario;
            }

            // array_keys() pega só as CHAVES de um array (nesse caso, só as
            // datas), descartando os valores, isso vira nossa lista de "quais
            // dias têm horário livre", pra desenhar uma pilula pra cada um
            $datasDisponiveis = array_keys($horariosPorData);

            // se ninguém escolheu uma data ainda (a pessoa acabou de entrar
            // nessa tela, sem clicar em nenhuma pilula), ou escolheu uma data
            // que não existe mais (por exemplo, alguém marcou o último horário
            // daquele dia enquanto ela estava pensando), usa a primeira data
            // disponível como padrão, pra tela nunca abrir vazia sem motivo
            if ($dataSelecionada === null || !isset($horariosPorData[$dataSelecionada])) {
                $dataSelecionada = $datasDisponiveis[0];
            }
            // confere se o "mês" que veio na URL tem um formato válido
            // (AAAA-MM, tipo "2026-09"). preg_match confere se o texto BATE
            // com esse padrão, devolve 1 se bateu, 0 se não bateu
            if ($mesSelecionado === null || !preg_match('/^\d{4}-\d{2}$/', $mesSelecionado)) {
                // sem mês válido na URL: mostra o mês da data selecionada.
                // substr($texto, 0, 7) pega só os 7 primeiros caracteres de
                // uma string, ou seja, de "2026-09-20" sobra só "2026-09"
                $mesSelecionado = substr($dataSelecionada, 0, 7);
            }

            // monta as informações do calendário desse mês
            $primeiroDiaDoMes = new DateTime($mesSelecionado . '-01');

            $totalDiasNoMes = (int) $primeiroDiaDoMes->format('t');
            // format('t') devolve quantos dias tem esse mês (28, 29, 30 ou 31)

            $diaDaSemanaInicio = (int) $primeiroDiaDoMes->format('w');
            // format('w') devolve em que dia da semana cai o dia 1 desse mês:
            // 0 = domingo, 1 = segunda... 6 = sábado. É esse número que diz
            // quantas "casinhas vazias" o calendário precisa antes do dia 1

            // clone cria uma CÓPIA do objeto DateTime, pra gente poder usar
            // modify() sem alterar o $primeiroDiaDoMes original (sem o clone,
            // modify() mudaria a mesma variável que ainda vamos usar embaixo)
            $mesAnterior = (clone $primeiroDiaDoMes)->modify('-1 month')->format('Y-m');
            $mesSeguinte = (clone $primeiroDiaDoMes)->modify('+1 month')->format('Y-m');

            // nomes dos meses em português (o PHP só sabe os nomes em inglês
            // por padrão, format('F') devolveria "August", não "Agosto")
            $nomesDosMeses = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
            ];
            $nomeDoMesAtual = $nomesDosMeses[(int) $primeiroDiaDoMes->format('n')] . ' ' . $primeiroDiaDoMes->format('Y');
        ?>

        <div class="agendar-grid" id="calendario">
            <div class="card calendar-card">
                <div class="calendar-header">
                    <a href="<?= h(app_url(['page' => 'agendar', 'medico' => $medico['doctor_id'], 'data' => $dataSelecionada, 'mes' => $mesAnterior])) ?>#calendario" class="calendar-nav-arrow">‹</a>
                    <span><?= h($nomeDoMesAtual) ?></span>
                    <a href="<?= h(app_url(['page' => 'agendar', 'medico' => $medico['doctor_id'], 'data' => $dataSelecionada, 'mes' => $mesSeguinte])) ?>#calendario" class="calendar-nav-arrow">›</a>
                </div>

                <div class="calendar-weekdays">
                    <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
                </div>

                <div class="calendar-days">
                    <?php for ($i = 0; $i < $diaDaSemanaInicio; $i++): ?>
                        <span class="calendar-day empty"></span>
                        <!-- casinhas vazias antes do dia 1, só pra empurrar
                             ele pra coluna certa da semana -->
                    <?php endfor; ?>

                    <?php for ($dia = 1; $dia <= $totalDiasNoMes; $dia++): ?>
                        <?php
                            // str_pad garante que o dia 5 vire "05" (com zero
                            // na frente), pra bater com o formato "2026-09-05"
                            // que usamos como chave no $horariosPorData
                            $dataDaCelula = $mesSelecionado . '-' . str_pad((string) $dia, 2, '0', STR_PAD_LEFT);
                        ?>

                        <?php if (isset($horariosPorData[$dataDaCelula])): ?>
                            <a href="<?= h(app_url(['page' => 'agendar', 'medico' => $medico['doctor_id'], 'data' => $dataDaCelula, 'mes' => $mesSelecionado])) ?>#calendario"
                               class="calendar-day available <?= $dataDaCelula === $dataSelecionada ? 'selected' : '' ?>">
                                <?= $dia ?>
                            </a>
                        <?php else: ?>
                            <span class="calendar-day disabled"><?= $dia ?></span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="card horarios-card">
                <div class="section-title">Horários disponíveis</div>
                <p class="text-muted" style="margin-bottom: 16px;">
                    <?= h((new DateTime($dataSelecionada))->format('d/m/Y')) ?>
                </p>

                <div class="time-slot-grid">
                    <?php foreach ($horariosPorData[$dataSelecionada] as $horario): ?>
                        <form method="post" action="<?= h(app_url()) ?>" onsubmit="return confirm('Confirma marcar essa consulta?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="marcar_consulta">
                            <input type="hidden" name="slot_id" value="<?= (int) $horario['id'] ?>">
                            <input type="hidden" name="medico_id" value="<?= (int) $medico['doctor_id'] ?>">
                            <input type="hidden" name="data" value="<?= h($dataSelecionada) ?>">
                            <input type="hidden" name="mes" value="<?= h($mesSelecionado) ?>">
                            <button type="submit" class="time-slot-btn">
                                <?= h((new DateTime($horario['slot_start']))->format('H:i')) ?>
                            </button>
                        </form>
                        <!-- Cada horário vira um <form> pequenininho, sozinho, com o botão dentro, assim, ao
                             clicar num horário, ele já manda um POST direto pra marcar aquela consulta
                             específica (o slot_id escondido diz qual). -->
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
    render_footer();
}
    // Cada horário vira um <form> pequenininho, sozinho, com o botão dentro, assim, ao clicar num horário,
    // ele já manda um POST direto pra marcar aquela consulta específica (o slot_id escondido diz qual).
