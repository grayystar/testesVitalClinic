# `migrations/` — Histórico de mudanças no banco

Cada arquivo aqui é um script SQL **incremental**: aplica, num banco
que já existe, a mesma mudança que já está incluída no
`vitalclinic_schema.sql` (que só serve para instalações novas — ele
apaga e recria o banco do zero). Rode as migrations **em ordem**, pela
aba SQL do phpMyAdmin, pulando as que você já aplicou.

> Não existe um "001" — a numeração começa em 002 porque a criação
> inicial das tabelas já é o próprio `vitalclinic_schema.sql`.

| Nº | O que faz |
|---|---|
| `002` | Adiciona a coluna calculada `is_admin` em `users` (deriva de `role`). |
| `003` | Cria a tabela `password_resets` (código de recuperação de senha por e-mail — **etapa removida depois**, ver migration 006). |
| `004` | Adiciona a coluna `modality` (presencial/teleconsulta) em `appointments`. |
| `005` | Adiciona `security_question` e `security_answer_hash` em `users` — a Pergunta de Segurança. |
| `006` | Remove a tabela `password_resets` (a recuperação de senha virou 100% por Pergunta de Segurança) e já deixa uma pergunta de demonstração cadastrada nas 3 contas fixas. |
| `007` | Cadastra o primeiro administrador de uma clínica nova — não é bem uma "migration de estrutura", é um exemplo de `INSERT` pronto para editar e rodar a cada nova clínica que contratar o sistema. |
| `008` | Adiciona `tutorial_seen` em `users` — controla se o tour guiado de primeiro acesso já foi visto. |
| `009` | Adiciona `terms_accepted` em `users` — controla se os Termos de Uso já foram aceitos. |

## Como saber quais eu já rodei

```sql
SHOW COLUMNS FROM users;
```

Se a coluna da migration já aparecer na lista, ela já foi aplicada —
pule para a próxima.
