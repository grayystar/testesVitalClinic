# `assets/` — CSS, JavaScript e identidade visual

## `css/styles.css`

Todo o estilo visual do site, num arquivo só (sem pré-processador, sem
Tailwind — CSS puro com variáveis nativas). Principais blocos, na
ordem em que aparecem no arquivo:

- **`:root` e `[data-theme="dark"]`** — as cores do sistema, como
  variáveis (`--bg`, `--surface`, `--text`, `--primary` etc.). Quase
  todo o resto do CSS usa essas variáveis em vez de cores fixas — é
  por isso que o modo escuro (`[data-theme="dark"]` no `<html>`)
  consegue escurecer o site inteiro só trocando os valores delas.
- **Layout base** (`.topbar`, `.shell`, `.nav`) — cabeçalho, menu em
  pílula centralizada (desktop) / gaveta com hambúrguer (mobile), e o
  botão "Sair" fixo.
- **Componentes reutilizáveis** — `.panel`, `.button`, `.form-card`,
  `.grid`, `.filters`, `.modal`, `.search-field` (busca com ícone de
  lupa), `.slot-picker` (chips de horário).
- **Calendários** — `.calendar-grid` (visão de mês, em
  `pages/*/calendario.php`) e `.appointment-calendar` (o calendário
  compacto dentro do modal "Nova consulta").
- **Tutorial guiado** (`.tour-*`) e **Termos de Uso** (`.terms-*`) —
  os dois modais especiais de primeiro acesso.
- **Blocos `@media`, ao final do arquivo** — os 3 níveis de tela
  (mobile-first): base = celular, `min-width: 640px` = tablet,
  `min-width: 1024px` = desktop. É aqui que o menu vira pílula
  horizontal, os grids ganham mais colunas, etc.

## `js/app.js`

Todo o comportamento interativo, em JavaScript "vanilla" (sem
framework, sem dependências externas). O arquivo é uma única IIFE
(função que roda sozinha), com uma função `setupX()` por
funcionalidade, todas registradas no fim do arquivo dentro de
`document.addEventListener('DOMContentLoaded', ...)`:

| Função | O que faz |
|---|---|
| `setupPwa()` | Registra o service worker (instalação como app) |
| `setupConfirmations()` | Confirma antes de ações destrutivas (ex.: cancelar consulta) |
| `setupNetworkBanner()` | Aviso quando a internet cai |
| `setupRolePicker()` | O seletor "Clínica / Médico" na tela de login |
| `setupRoleSwitches()` | Campos do formulário que mudam conforme o perfil escolhido |
| `setupThemeToggle()` | Alterna modo claro/escuro e salva a escolha (`localStorage`) |
| `setupMobileNav()` | Abre/fecha a gaveta do menu no celular/tablet |
| `setupResponsiveTables()` | Ativa o modo "cartão empilhado" das tabelas no celular |
| `setupTermsGate()` | O modal bloqueante de aceite dos Termos de Uso |
| `setupTutorial()` | O tour guiado de primeiro acesso (destaque + balão sobre o menu) |
| `setupModals()` | Abrir/fechar modais genéricos (`<dialog>`) |
| `setupAppointmentCalendar()` | O calendário visual do modal "Nova consulta" (busca os dias de atendimento do médico e deixa escolher a data) |
| `setupSlots()` | Busca os horários livres de um médico numa data (chips clicáveis) |
| `setupAutocomplete()` | Campo de busca com sugestões (usado para escolher paciente/médico) |
| `setupNewAppointmentForm()` | Envio do formulário de nova consulta via `fetch()`, com validação, estado de carregamento e tratamento de erro |

No topo do arquivo também estão os helpers `qs()`/`qsa()`
(atalhos para `querySelector`/`querySelectorAll`) usados em todas as
funções acima.

## `brand/`

`vital-clinic-logo.svg` (logo completa, usada no topo) e
`vital-clinic-mark.svg` (só o símbolo, usado como ícone/favicon).
