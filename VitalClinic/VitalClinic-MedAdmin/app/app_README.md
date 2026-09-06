# `app/` — Lógica de negócio

Esta pasta reúne tudo que **não é tela**: conexão com o banco,
autenticação, regras de negócio e funções utilitárias. Nenhum arquivo
aqui produz HTML diretamente — quem desenha a tela é a pasta `pages/`
(veja `pages/README.md`). Todo arquivo é `require`ido a partir de
`index.php`.

---

## `config.php`

Configurações do sistema, num único array PHP retornado pela função
`config()` (definida em `helpers.php`). Contém:

- `app_name`, `timezone` — identidade e fuso horário do sistema.
- `db` — host, nome do banco, usuário e senha do MySQL. Lê de
  variáveis de ambiente (`getenv()`) quando existem, com valores
  padrão para desenvolvimento local (XAMPP).
- `data.mode` — qual "modo de dados" o site usa: `mysql` (o atual,
  ativo), `api` (modo alternativo via `api_client.php`, não usado
  hoje) ou `demo` (modo legado).
- `rules` — regras de negócio: quantas horas de antecedência para
  cancelar/reagendar, até quantos dias no futuro dá para agendar,
  quantas tentativas erradas a pergunta de segurança permite.
- `security_questions` — a lista fixa de perguntas de segurança que
  aparece no formulário de perfil.
- `mail` — configuração de e-mail (não usada no fluxo atual de
  recuperação de senha, que é só por pergunta de segurança).

## `db.php`

Abre a conexão com o MySQL via PDO (`db()`), reaproveitando a mesma
conexão durante toda a requisição (variável `static`). Também expõe
`db_transaction(callable $fn)` — roda o `$fn` dentro de uma transação
(`BEGIN`/`COMMIT`/`ROLLBACK` automáticos): se qualquer coisa lançar uma
exceção lá dentro, nada é gravado. É usada em operações que mexem em
várias tabelas de uma vez (ex.: criar uma consulta grava em
`appointment_slots`, `appointments` e `payments` juntos).

## `helpers.php`

Funções pequenas, usadas em quase todo arquivo do projeto:

| Função | Para que serve |
|---|---|
| `config($key)` | Lê uma configuração (aceita `'rules.booking_max_days'`, por exemplo) |
| `h($value)` | Escapa texto para exibir em HTML com segurança (`htmlspecialchars`) |
| `app_url($params)` / `asset_url($path)` | Monta a URL de uma página / de um arquivo estático (com versão anti-cache) |
| `is_ajax_request()` | Detecta se a requisição veio de `fetch()` (cabeçalho `X-Requested-With`) |
| `send_json($payload, $status)` | Responde em JSON e encerra a execução — usada por toda ação chamada via `fetch()` |
| `redirect($params)` | Redireciona (ou responde em JSON, se for AJAX) — é o "fim de linha" comum de toda ação de formulário |
| `csrf_token()` / `csrf_field()` / `verify_csrf()` | Geração e checagem do token anti-CSRF |
| `flash($type, $msg)` / `take_flash()` | Mensagens de sucesso/erro que sobrevivem a um redirecionamento |
| `post_value($key)` | Lê um campo de `$_POST` já tratado (trim) |
| `format_datetime` / `format_date` / `format_time` / `format_money` / `weekday_name` / `status_label` | Formatação de datas, valores e status para exibição |
| `current_date_value()` / `now_sql()` | Data/hora atual nos formatos usados pelo sistema/banco |
| `abort_forbidden()` | Interrompe a requisição com HTTP 403 |

## `auth.php`

Login, logout e todo o fluxo de "Esqueci minha senha".

- `current_user()` / `require_login()` / `require_role($roles)` —
  quem está logado, e trava de acesso por perfil.
- `attempt_login()` — faz a autenticação de verdade e devolve o
  **motivo exato** da falha (`not_found`, `inactive`, `wrong_role`,
  `wrong_password`) ou `null` se deu certo — é isso que permite a tela
  de login mostrar uma mensagem específica em vez de um erro genérico.
  `login_user()` é só um wrapper que devolve `true`/`false`.
- `register_patient()` — cadastro de paciente feito pelo admin (gera
  uma senha inicial aleatória).
- `request_password_reset()` → `confirm_password_reset_security_answer()`
  → `complete_password_reset()` — as 3 etapas da recuperação de senha
  por pergunta de segurança, com o estado temporário guardado em
  `$_SESSION['pwd_reset']` entre uma etapa e outra.

## `repository.php`

O arquivo mais longo do projeto (~70 funções) — é aqui que toda
consulta SQL do sistema realmente acontece. Organizado por assunto:

- **Usuários e perfis** — buscar por e-mail/id, atualizar perfil,
  trocar senha, pergunta de segurança (`set_user_security_question()`,
  `verify_user_security_answer()`), tutorial (`mark_tutorial_seen()`),
  termos de uso (`accept_terms()`).
- **Clínicas e especialidades** — listagens usadas em formulários.
- **Médicos** (`doctors`) — CRUD, agenda semanal
  (`doctor_working_weekdays()` inclusive, usada pelo calendário visual
  do agendamento), bloqueios pontuais.
- **Pacientes** — CRUD, busca, histórico de consultas.
- **Agenda e consultas** — geração de horários (`ensure_slots()`),
  horários livres (`available_slots()`), criação de consulta
  (`create_appointment()`, dentro de uma transação), mudança de status,
  cancelamento.
- **Prontuários** (`medical_records`) — salvar e listar.
- **Pagamentos** — criados automaticamente junto com a consulta.
- **Notificações** — criar, listar, marcar como lidas.
- **Relatórios** — números agregados para a tela de Relatórios.

## `mailer.php`

Envio de e-mail via `mail()`/SMTP. **Não é chamado por nenhuma tela
hoje** — era usado pelo antigo fluxo de recuperação de senha por
código enviado por e-mail, que foi substituído pela Pergunta de
Segurança. Mantido no projeto caso o envio de e-mail volte a ser
necessário para outra finalidade (ex.: lembretes de consulta).

## `api_client.php`

Cliente HTTP (via cURL) para um modo alternativo de operação em que o
site conversaria com uma API central externa em vez de ler/gravar
direto no MySQL. **Não está em uso** — o modo ativo é `mysql` (ver
`config.php`, `data.mode`). Nenhuma das funções deste arquivo é
chamada no funcionamento normal do sistema hoje.
