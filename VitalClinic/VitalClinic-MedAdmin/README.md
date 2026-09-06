# Vital Clinic — Painel Web (Admin + Médico)

Sistema de agendamento e gestão de consultas para clínicas médicas. Este
repositório contém o **painel web** (PHP + MySQL), usado por
**administradores da clínica** e **médicos** para gerenciar consultas,
pacientes, prontuários e a própria agenda. Pacientes **não fazem login
neste site** — eles são cadastrados e atendidos pela equipe da clínica
(o acesso do próprio paciente é feito por outro componente do projeto,
como um app mobile).

Este README dá a visão geral do site inteiro. Cada pasta principal tem
o seu próprio `README.md` com detalhes de arquivo por arquivo:

- [`app/README.md`](app/README.md) — a "lógica" do sistema (PHP puro)
- [`pages/README.md`](pages/README.md) — as telas (uma função por página)
- [`assets/README.md`](assets/README.md) — CSS, JavaScript e a logo
- [`migrations/README.md`](migrations/README.md) — histórico de mudanças no banco
- [`scripts/README.md`](scripts/README.md) — scripts de linha de comando

---

## Índice

1. [O que é o sistema](#1-o-que-é-o-sistema)
2. [Instalação](#2-instalação)
3. [Credenciais de acesso](#3-credenciais-de-acesso)
4. [Como o sistema funciona por dentro](#4-como-o-sistema-funciona-por-dentro)
5. [Funcionalidades — tela por tela](#5-funcionalidades--tela-por-tela)
6. [Recursos de experiência do usuário](#6-recursos-de-experiência-do-usuário)
7. [Recuperação de senha](#7-recuperação-de-senha)
8. [Banco de dados](#8-banco-de-dados)
9. [Scripts de população (dados fictícios)](#9-scripts-de-população-dados-fictícios)
10. [Segurança](#10-segurança)
11. [Estrutura de pastas](#11-estrutura-de-pastas)
12. [Referência das ações do sistema](#12-referência-das-ações-do-sistema)
13. [Limitações conhecidas](#13-limitações-conhecidas)

---

## 1. O que é o sistema

O Vital Clinic é o painel administrativo de uma rede de clínicas. Nele,
a equipe consegue:

- Cadastrar e gerenciar **médicos** (com especialidade, CRM e horários
  de atendimento semanais);
- Cadastrar **pacientes**;
- **Agendar consultas**, com um calendário visual que já mostra os dias
  em que cada médico atende;
- Acompanhar a **agenda do dia/mês** de toda a clínica ou de um médico
  específico;
- Registrar o **prontuário** de cada consulta concluída (peso, altura,
  sinais vitais, diagnóstico, prescrição);
- Acompanhar **relatórios** (consultas por status, faltas, faturamento);
- Gerenciar o próprio **perfil**, senha e pergunta de segurança.

Não existe fluxo de cadastro público — todo mundo que usa o painel
(administrador ou médico) é cadastrado por um administrador já
existente. O primeiro administrador de uma clínica nova precisa ser
inserido diretamente no banco (não há tela de "primeiro acesso" —
veja a seção de scripts para isso).

---

## 2. Instalação

**Requisitos:** XAMPP (Apache + MySQL/MariaDB + PHP 8+), navegador atual.

1. Copie a pasta do projeto para `C:\xampp\htdocs\VitalClinic-SITE` (ou
   o nome que preferir).
2. Ligue **Apache** e **MySQL** no painel do XAMPP.
3. Abra o **phpMyAdmin** e execute o conteúdo de `vitalclinic_schema.sql`
   na aba SQL. Isso cria o banco `vitalclinic` do zero (⚠️ apaga
   qualquer banco de mesmo nome que já exista) e já deixa alguns
   registros de demonstração cadastrados.
4. Acesse `http://localhost/VitalClinic-SITE/.../VitalMED/` no navegador.

> **Já tem um banco criado e não quer perder dados?** Não rode
> `vitalclinic_schema.sql` de novo. Em vez disso, aplique só as
> migrations que ainda faltam, na ordem — veja
> [`migrations/README.md`](migrations/README.md).

---

## 3. Credenciais de acesso

Todos os usuários abaixo já vêm cadastrados quando você roda
`vitalclinic_schema.sql`. A senha de todos é **`password`**.

### Contas fixas de demonstração

| Perfil | E-mail | Senha | Observação |
|---|---|---|---|
| Administrador | `admin@clinica.local` | `password` | Clínica Central |
| Médico | `medico@clinica.local` | `password` | Dra. Ana Souza — Clínico geral |
| Médico | `carlos.lima@clinicanorte.local` | `password` | Dr. Carlos Lima — Cardiologia, Clínica Norte |

Na tela de login, escolha o botão **"Clínica"** para entrar como
administrador, ou **"Médico"** para entrar como médico.

Esses 3 usuários também já têm uma **pergunta de segurança** cadastrada
(para testar a recuperação de senha sem precisar configurar nada
antes — veja a [seção 7](#7-recuperação-de-senha)):

| Conta | Pergunta | Resposta |
|---|---|---|
| `admin@clinica.local` | Qual o nome do seu primeiro animal de estimação? | `Rex` |
| `medico@clinica.local` | Qual foi o nome da sua primeira escola? | `Colégio Santa Rita` |
| `carlos.lima@clinicanorte.local` | Qual é a sua cidade natal? | `Recife` |

### Contas geradas por `seed_populacao.sql`

Só existem se você rodou esse script (veja a [seção 9](#9-scripts-de-população-dados-fictícios)). Senha de todas: **`password`**.

**Administradores (9):**

| Nome | E-mail |
|---|---|
| Carlos Almeida Cardoso | `carlos.almeida.cardoso.5607@seed4.local` |
| Carlos Barbosa Cavalcanti | `carlos.barbosa.cavalcanti.9449@seed4.local` |
| Daniel Cavalcanti Rocha | `daniel.cavalcanti.rocha.5903@seed4.local` |
| Fábio Ramos Ribeiro | `fabio.ramos.ribeiro.5722@seed4.local` |
| Henrique Pinto Gomes | `henrique.pinto.gomes.6619@seed4.local` |
| Paulo Cardoso Fernandes | `paulo.cardoso.fernandes.4648@seed4.local` |
| Priscila Lima Gomes | `priscila.lima.gomes.8220@seed4.local` |
| Sandra Almeida Vieira | `sandra.almeida.vieira.7638@seed4.local` |
| Sérgio Lima Andrade | `sergio.lima.andrade.4718@seed4.local` |

**Médicos (12):**

| Nome | E-mail |
|---|---|
| Dr. Cristiano Castro Alves | `cristiano.castro.alves.3413@seed4.local` |
| Dr. Roberto Alves Monteiro | `roberto.alves.monteiro.8729@seed4.local` |
| Dr. Thiago Ramos Fernandes | `thiago.ramos.fernandes.6758@seed4.local` |
| Dr. Vinícius Ribeiro Vieira | `vinicius.ribeiro.vieira.9365@seed4.local` |
| Dra. Adriana Lopes Nascimento | `adriana.lopes.nascimento.6231@seed4.local` |
| Dra. Amanda Ribeiro Gomes | `amanda.ribeiro.gomes.9408@seed4.local` |
| Dra. Debora Reis Fernandes | `debora.reis.fernandes.9883@seed4.local` |
| Dra. Gabriela Soares Teixeira | `gabriela.soares.teixeira.9631@seed4.local` |
| Dra. Leticia Santos Martins | `leticia.santos.martins.6725@seed4.local` |
| Dra. Maria Cavalcanti Fernandes | `maria.cavalcanti.fernandes.9536@seed4.local` |
| Dra. Talita Reis Ferreira | `talita.reis.ferreira.2357@seed4.local` |
| Dra. Talita Ribeiro Nunes | `talita.ribeiro.nunes.8398@seed4.local` |

### Contas geradas por `seed_data_extra.sql`

Só existem se você rodou esse script. Senha de todas: **`password`**.

**Administradores (4):**

| Nome | E-mail |
|---|---|
| Carlos Martins Rocha | `carlos.martins.rocha.adm.383@seed2.local` |
| Cristiano Pereira Ramos | `cristiano.pereira.ramos.adm.309@seed2.local` |
| Eliane Rodrigues Pinto | `eliane.rodrigues.pinto.adm.465@seed2.local` |
| Marcelo Ferreira Ribeiro | `marcelo.ferreira.ribeiro.adm.161@seed2.local` |

**Médicos (12):**

| Nome | E-mail |
|---|---|
| Dr(a). Adriana Silva Pinto | `adriana.silva.pinto.915@seed2.local` |
| Dr(a). Daniel Marques Soares | `daniel.marques.soares.365@seed2.local` |
| Dr(a). Gustavo Oliveira Vieira | `gustavo.oliveira.vieira.973@seed2.local` |
| Dr(a). Igor Cavalcanti Andrade | `igor.cavalcanti.andrade.427@seed2.local` |
| Dr(a). Leonardo Freitas Marques | `leonardo.freitas.marques.290@seed2.local` |
| Dr(a). Otávio Nascimento Marques | `otavio.nascimento.marques.780@seed2.local` |
| Dr(a). Paulo Almeida Santos | `paulo.almeida.santos.745@seed2.local` |
| Dr(a). Roberto Fernandes Carvalho | `roberto.fernandes.carvalho.156@seed2.local` |
| Dr(a). Sérgio Lopes Martins | `sergio.lopes.martins.611@seed2.local` |
| Dr(a). Sérgio Ramos Andrade | `sergio.ramos.andrade.716@seed2.local` |
| Dr(a). Talita Lima Machado | `talita.lima.machado.471@seed2.local` |
| Dr(a). Viviane Dias Cavalcanti | `viviane.dias.cavalcanti.307@seed2.local` |

### Contas geradas por `scripts/seed_producao.php`

Esse script gera nomes e e-mails **aleatórios a cada execução**
(diferente dos dois arquivos SQL acima, que têm uma lista fixa), então
não dá pra listar aqui de antemão. Todas usam e-mail terminado em
`@seed3.local` e senha **`password`** — depois de rodar, veja os e-mails
gerados direto no painel (Médicos / em Perfil de cada administrador) ou com:

```sql
SELECT name, email, role FROM users WHERE email LIKE '%@seed3.local';
```

**Cadastrando o primeiro admin de uma clínica nova:** não existe tela
de "criar conta" — o primeiro administrador é inserido direto no banco.
Veja o exemplo em `migrations/007_criar_primeiro_admin.sql` (gere o
hash da senha com `password_hash()` do PHP antes de rodar o `INSERT`).

---

## 4. Como o sistema funciona por dentro

O projeto **não usa nenhum framework** — é PHP puro (sem Composer),
com JavaScript "vanilla" (sem React/Vue) e CSS puro (sem Tailwind).
Essa escolha é proposital: qualquer parte do código pode ser lida e
entendida sem precisar conhecer uma ferramenta externa.

- **`index.php`** — único ponto de entrada do site inteiro. Toda URL
  passa por ele (`?page=...` decide a tela, `?action=...` processa um
  formulário). Ele decide se a pessoa pode ver aquela página (login
  exigido ou não) e desenha o layout comum (topo + menu + conteúdo).
- **`app/`** — a lógica de negócio (ver `app/README.md`).
- **`pages/`** — uma função por tela, agrupadas por área
  (ver `pages/README.md`).
- **`assets/`** — CSS, JavaScript e a logo (ver `assets/README.md`).

**Como uma ação típica funciona, de ponta a ponta** (exemplo: agendar
uma consulta):

1. O admin clica em **"+ Adicionar Nova Consulta"** — abre um modal
   (JavaScript, sem recarregar a página).
2. Digita o nome do paciente e do médico — um autocompletar busca nas
   listas já carregadas na página (sem ida ao servidor a cada letra).
3. Ao escolher o médico, o calendário visual do modal busca
   (`?action=doctor_weekdays`) os dias da semana em que ele atende, e
   já destaca esses dias no calendário.
4. Ao clicar num dia disponível, o JavaScript busca
   (`?action=slots`) os horários livres daquele médico naquele dia.
5. Ao clicar em **"Agendar consulta"**, o formulário é enviado via
   `fetch()` para `index.php?action=admin_create_appointment`.
6. `index.php` identifica a ação, chama `create_appointment()` em
   `app/repository.php`, que grava o registro nas tabelas
   `appointment_slots`, `appointments` e `payments` (numa única
   transação — se algo falhar no meio, nada fica gravado pela metade).
7. O servidor responde em **JSON** (não uma página nova); o modal
   fecha sozinho, um aviso de sucesso aparece, e a lista de consultas
   na tela se atualiza sem precisar dar F5.

---

## 5. Funcionalidades — tela por tela

### Administrador

| Menu | O que faz |
|---|---|
| **Geral** | Painel com números do dia (consultas de hoje, pendentes, total de pacientes, total de médicos) e a agenda do dia. |
| **Calendário** | Visão do mês inteiro, com as consultas de cada dia. Em telas estreitas, a grade dos 7 dias ganha rolagem horizontal em vez de quebrar o layout. |
| **Consultas** | Lista completa de consultas, com filtros (status, médico, data). Botão **"Adicionar Nova Consulta"** abre um modal com autocompletar de paciente/médico, calendário visual de dias disponíveis, seleção de horário, tipo (presencial/teleconsulta) e observações. |
| **Pacientes** | Lista de pacientes, com busca (nome/CPF/telefone). Permite cadastrar um paciente novo (o sistema gera uma senha inicial, mostrada na tela), editar dados e ver o histórico de consultas de cada um. |
| **Médicos** | Lista de médicos da clínica, com especialidade e CRM. Permite cadastrar, editar, desativar, e configurar a agenda semanal (dias/horários) e bloqueios pontuais (férias, congressos). |
| **Relatórios** | Números agregados: consultas por status, taxa de faltas, faturamento — com filtro de período. |
| **Perfil** | Dados da própria conta: nome, telefone, trocar senha, e cadastrar/alterar a pergunta de segurança. |
| **Notificações** | Avisos do sistema relacionados às consultas (confirmações, cancelamentos, lembretes). |

### Médico

| Menu | O que faz |
|---|---|
| **Geral** | Painel com a agenda do médico logado. |
| **Calendário** | Igual ao do admin, mas só com a agenda deste médico. |
| **Consultas** | Lista das consultas deste médico, com filtros. |
| **Pacientes** | Lista dos pacientes já atendidos por este médico, com busca. |
| **Prontuário / Histórico** | Ao abrir uma consulta concluída, o médico registra o prontuário: peso, altura, temperatura, frequência cardíaca, pressão arterial, sintomas, diagnóstico, prescrição e retorno recomendado. O histórico mostra todos os prontuários anteriores daquele paciente. |
| **Perfil** | Dados da própria conta, trocar senha, pergunta de segurança. |
| **Notificações** | Avisos relacionados às consultas do médico. |

---

## 6. Recursos de experiência do usuário

- **Menu centralizado em pílula** — no desktop, o menu principal fica
  centralizado no topo, num formato de pílula flutuante. No
  celular/tablet vira uma gaveta acionada pelo botão ☰.
- **Botão "Sair"** fixo no canto superior direito, em vermelho, sempre
  visível.
- **Tutorial guiado de primeiro acesso** — na primeira vez que um
  admin ou médico loga, um tour destaca (com uma "luz" ao redor) os
  itens do menu, explicando o que cada um faz. Diferente por perfil.
  Não aparece de novo depois de concluído ou pulado.
- **Aceite de Termos de Uso** — no primeiro login, um modal bloqueante
  (sem X, sem Esc, sem clique fora) mostra os Termos de Uso e a
  Política de Privacidade; só libera o uso do site depois de marcar
  "Li e aceito" e clicar em "Prosseguir".
- **Calendário visual no agendamento** — em vez de um campo de data
  comum, o modal de nova consulta mostra um calendário de verdade, que
  destaca os dias em que o médico escolhido atende.
- **Busca padronizada** — os campos de busca de Pacientes e Médicos
  (admin e médico) têm o mesmo visual: mesma altura, ícone de lupa,
  cantos arredondados.
- **Totalmente responsivo** — mobile-first, com 3 níveis de tela:
  celular (< 640px), tablet (640–1024px) e desktop (> 1024px).
  Tabelas viram cartões empilhados no celular; o calendário mensal
  ganha rolagem horizontal em vez de espremer os 7 dias da semana.

---

## 7. Recuperação de senha

O sistema **não envia código por e-mail** — a recuperação de senha é
feita inteiramente pela **Pergunta de Segurança** cadastrada no
perfil de cada administrador/médico.

1. Na tela de login, clique em **"Esqueci minha senha"**.
2. Digite o e-mail cadastrado.
3. O sistema mostra a pergunta de segurança daquela conta (ou uma
   mensagem genérica, se a conta não existir ou não tiver pergunta
   cadastrada — isso evita que alguém descubra quais e-mails existem
   no sistema só tentando um por um).
4. Digite a resposta — maiúsculas/minúsculas, espaços extras e
   acentos não importam.
5. Se a resposta bater, o sistema libera a tela de nova senha.

A resposta nunca é salva em texto puro — só o hash (mesma técnica
usada para a senha de login). Errar várias vezes bloqueia
temporariamente essa etapa.

---

## 8. Banco de dados

| Tabela | O que guarda |
|---|---|
| `clinics` | As unidades/clínicas cadastradas (sistema multi-clínica). |
| `specialties` | Especialidades médicas. |
| `users` | Tabela única para administradores, médicos e pacientes — `role` diferencia quem é quem, `is_admin` é calculado automaticamente. Inclui pergunta de segurança, aceite de termos e status do tutorial. |
| `doctors` | Dados profissionais do médico (CRM, especialidade, duração padrão da consulta) — ligado 1:1 a um registro em `users`. |
| `doctor_schedules` | Grade semanal fixa de atendimento (ex.: segundas e quartas, 8h–12h). |
| `schedule_blocks` | Bloqueios pontuais (férias, congresso, feriado). |
| `appointment_slots` | Cada horário específico gerado (disponível, reservado ou bloqueado). |
| `appointments` | As consultas — liga paciente, médico, clínica e horário. |
| `medical_records` | O prontuário de cada consulta concluída. |
| `payments` | Cobrança de cada consulta. |
| `notifications` | Avisos enviados a um usuário sobre uma consulta. |

Veja a lista completa de colunas em `vitalclinic_schema.sql`.

---

## 9. Scripts de população (dados fictícios)

Para testar com um volume realista de dados, existem 3 opções — todas
**aditivas** (nunca apagam nada):

| Arquivo | Como rodar | O que gera |
|---|---|---|
| `seed_populacao.sql` | Cole no phpMyAdmin (aba SQL) | SQL puro — a opção mais simples |
| `scripts/seed_producao.php` | `php scripts/seed_producao.php` no terminal | Mesma ideia, mas com quantidades ajustáveis (`--clinics=8 --patients=200`) |
| `seed_data_extra.sql` | Cole no phpMyAdmin — só uma vez, usa CNPJs fixos | Lote adicional incluído desde o início do projeto |

Todos os usuários fictícios usam senha **`password`**, com e-mail em
domínio próprio (`@seed2.local`, `@seed3.local`, `@seed4.local`) e CNPJ
com prefixo próprio — fáceis de identificar/remover depois. Detalhes
em [`scripts/README.md`](scripts/README.md).

---

## 10. Segurança

- Senhas: hash com `password_hash()` (bcrypt), nunca texto puro.
- Resposta da pergunta de segurança: mesmo hash, com normalização
  (ignora maiúsculas/acentos/espaços) antes de gerar o hash.
- Todo formulário usa **token CSRF**.
- Mensagens de erro do "Esqueci minha senha" são propositalmente
  genéricas na 1ª etapa.
- Mensagens de erro de **login** já são específicas (conta não
  encontrada / inativa / perfil errado / senha incorreta) — como quem
  loga é sempre uma conta provisionada pela própria clínica, não há o
  mesmo risco de enumeração que existe no "esqueci minha senha".

---

## 11. Estrutura de pastas

```
VitalMED/
├── index.php                  # ponto de entrada único (rotas + layout)
├── vitalclinic_schema.sql     # cria o banco do zero (com dados demo)
├── seed_populacao.sql         # popula com dados fictícios (SQL puro)
├── seed_data_extra.sql        # lote adicional de dados fictícios
├── correcao_dados_e_padronizacao.sql  # script pontual de correção de dados
├── manifest.webmanifest       # metadados do PWA (ícone, nome, cores)
├── service-worker.js          # cache offline do PWA
├── README.md                  # este arquivo
├── app/            # lógica de negócio           → app/README.md
├── pages/          # telas (uma função por página) → pages/README.md
├── assets/         # CSS, JS e logo                → assets/README.md
├── migrations/     # histórico de mudanças no banco → migrations/README.md
└── scripts/        # scripts de linha de comando    → scripts/README.md
```

---

## 12. Referência das ações do sistema

Toda ação que grava algo no banco passa por `index.php?action=...`
(via `POST`). Referência rápida de todas as ações existentes:

| Ação | O que faz |
|---|---|
| `login` / `logout` | Entrar / sair da conta |
| `request_password_reset` / `verify_security_answer` / `reset_password` / `cancel_password_reset` | As etapas do "Esqueci minha senha" |
| `mark_tutorial_seen` | Marca que o tutorial guiado já foi visto/pulado |
| `accept_terms` | Registra o aceite dos Termos de Uso |
| `update_staff_profile` | Salva o perfil (dados, senha, pergunta de segurança) |
| `admin_create_appointment` | Agenda uma nova consulta |
| `cancel` | Cancela uma consulta |
| `mark_appointment` | Muda o status de uma consulta (confirmar, concluir, marcar falta) |
| `admin_create_doctor` / `admin_update_doctor` / `admin_delete_doctor` | Gerenciar médicos |
| `admin_add_schedule` / `admin_delete_schedule` | Grade semanal de atendimento do médico |
| `admin_add_block` | Bloqueio pontual na agenda de um médico |
| `admin_create_patient` / `admin_update_patient` | Gerenciar pacientes |
| `save_medical_record` | Salvar o prontuário de uma consulta |
| `admin_update_user_role` | Alterar o perfil/status de um usuário |
| `mark_notifications_read` | Marcar notificações como lidas |

Duas ações do tipo `GET` (não gravam nada, só consultam):
`?action=slots` (horários livres de um médico num dia) e
`?action=doctor_weekdays` (dias da semana em que um médico atende).

---

## 13. Limitações conhecidas

- **Pacientes não têm login neste site.** O campo `role = 'patient'`
  existe na tabela `users`, mas o login só aceita `admin` e `doctor` —
  o acesso do paciente é feito por outro componente do projeto.
- `app/mailer.php` e `app/api_client.php` continuam no projeto, mas
  não são chamados no funcionamento atual (o primeiro era usado pelo
  antigo fluxo de recuperação de senha por e-mail; o segundo é a base
  para um modo alternativo de operação via API central, ainda não
  ativado).