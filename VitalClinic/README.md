# VCTCC — Vital Clinic

Sistema web de gestão de consultas para administradores e médicos.

> **Atualização:** o site voltou a ler e gravar diretamente em um banco de dados **MySQL** (banco `vitalclinic`, ver `vitalclinic_schema.sql`), através de PDO (`app/db.php`). O antigo modo demonstração baseado em `data/demo-state.json` foi desativado — o arquivo continua no projeto apenas como referência histórica dos dados de exemplo, mas não é mais lido pela aplicação.

## Como conectar ao MySQL

1. Crie o banco executando o script `vitalclinic_schema.sql` (inclui `CREATE DATABASE`, todas as tabelas e dados de exemplo):
   ```bash
   mysql -u root -p < vitalclinic_schema.sql
   ```
2. Informe as credenciais de acesso por variáveis de ambiente (recomendado) ou diretamente em `app/config.php`:
   ```text
   VCTCC_DB_HOST=127.0.0.1
   VCTCC_DB_PORT=3306
   VCTCC_DB_NAME=vitalclinic
   VCTCC_DB_USER=root
   VCTCC_DB_PASS=sua_senha
   ```
3. No XAMPP, inicie **Apache** e **MySQL** e acesse `http://localhost/VitalClinic-SITE/`.

Se a conexão falhar, o site mostra uma tela de "Erro de conexão com o banco de dados" com o detalhe técnico, em vez de travar.

## Arquitetura da camada de dados

| Arquivo | Responsabilidade |
| --- | --- |
| `app/db.php` | Conexão PDO única (singleton) com o MySQL, mais um helper `db_transaction()`. |
| `app/repository.php` | Todas as consultas e gravações de negócio (SQL), com as mesmas funções que as páginas já usavam — nenhuma página em `pages/` precisou ser alterada. |
| `app/config.php` | Credenciais do banco (chave `db`) e demais configurações. |
| `vitalclinic_schema.sql` | Script completo de criação do banco: `CREATE DATABASE`, `CREATE TABLE` com chaves e índices, e `INSERT` de dados de exemplo. |

O modo de API central (`app/api_client.php`) e o modo demo em JSON continuam no repositório apenas por referência, mas não são mais usados por padrão.

## Modos de funcionamento

| Modo | Configuração | Comportamento |
| --- | --- | --- |
| Demonstração | `VCTCC_DATA_MODE=demo` ou configuração padrão | Usa `data/demo-state.json`. Não depende de MySQL nem XAMPP para o banco. |
| API central | `VCTCC_DATA_MODE=api` e `VCTCC_API_URL=https://...` | Busca e salva o estado pela API central no endpoint `v1/state`. |

O modo padrão é `demo`, para que a aplicação continue funcionando imediatamente após ser copiada para o servidor local. O arquivo JSON serve apenas para protótipo e desenvolvimento; não deve ser usado como banco de produção ou acessado diretamente por usuários.

## Execução local

O projeto continua sendo uma aplicação PHP. Para executar localmente, copie a pasta `VCTCC-main` para o diretório público do Apache, por exemplo:

```text
C:\xampp\htdocs\VCTCC-main
```

Inicie somente o **Apache** no XAMPP e acesse:

```text
http://localhost/VCTCC-main/
```

O MySQL não é mais necessário para o modo demonstração. Caso o PHP seja executado por outro servidor web, o diretório `data` precisa ter permissão de escrita para que o estado JSON seja atualizado.

## Acesso de demonstração

> Depois de criar o banco (`vitalclinic_schema.sql`), rode também `correcao_dados_e_padronizacao.sql` — ele corrige inconsistências (ex.: clínicas sem administrador vinculado) e padroniza a senha de **todas** as contas ativas para `Senha123!`.

### Administradores

| Nome | E-mail | Senha | Nível de acesso |
| --- | --- | --- | --- |
| Administrador — Clínica Central | `admin@clinica.local` | `Senha123!` | Administrador geral (clínica) |
| Carlos Martins Rocha | `carlos.martins.rocha.adm.383@seed2.local` | `Senha123!` | Administrador (clínica) |
| Cristiano Pereira Ramos | `cristiano.pereira.ramos.adm.309@seed2.local` | `Senha123!` | Administrador (clínica) |
| Eliane Rodrigues Pinto | `eliane.rodrigues.pinto.adm.465@seed2.local` | `Senha123!` | Administrador (clínica) |
| Marcelo Ferreira Ribeiro | `marcelo.ferreira.ribeiro.adm.161@seed2.local` | `Senha123!` | Administrador (clínica) |
| Administrador — Clínica Norte * | `admin.clinicanorte@vitalclinic.local` | `Senha123!` | Administrador (clínica) |
| Administrador — Clínica Leste * | `admin.clinicaleste@vitalclinic.local` | `Senha123!` | Administrador (clínica) |
| Administrador — Clínica Vitalità * | `admin.clinicavitalita@vitalclinic.local` | `Senha123!` | Administrador (clínica) |

`*` Contas criadas pelo script de correção — essas 3 clínicas não tinham nenhum administrador cadastrado.

### Clínicas

| Nome da Clínica | E-mail | Senha (do responsável) | Responsável |
| --- | --- | --- | --- |
| Clínica Central | `admin@clinica.local` | `Senha123!` | Administrador — Clínica Central |
| Clínica Norte | `admin.clinicanorte@vitalclinic.local` | `Senha123!` | Administrador — Clínica Norte |
| Clínica Sul | `cristiano.pereira.ramos.adm.309@seed2.local` | `Senha123!` | Cristiano Pereira Ramos |
| Clínica Leste | `admin.clinicaleste@vitalclinic.local` | `Senha123!` | Administrador — Clínica Leste |
| Clínica Vitalità | `admin.clinicavitalita@vitalclinic.local` | `Senha123!` | Administrador — Clínica Vitalità |
| Clínica Renascer | `eliane.rodrigues.pinto.adm.465@seed2.local` | `Senha123!` | Eliane Rodrigues Pinto |
| Espaço Saúde Mais | `marcelo.ferreira.ribeiro.adm.161@seed2.local` | `Senha123!` | Marcelo Ferreira Ribeiro |

> A clínica em si não possui login próprio no sistema — o acesso é feito pela conta do administrador vinculado a ela (coluna `clinic_id` em `users`).

### Médicos

| Nome | E-mail | Senha | Especialidade | Clínica vinculada |
| --- | --- | --- | --- | --- |
| Dra. Ana Souza | `medico@clinica.local` | `Senha123!` | Clínico geral | Clínica Central |
| Dr. Carlos Lima | `carlos.lima@clinicanorte.local` | `Senha123!` | Cardiologia | Clínica Norte |

Além desses dois médicos "de referência" (usados nos prints e exemplos deste README), o arquivo `seed_data_extra.sql` cadastra mais **12 médicos** de teste, distribuídos entre as clínicas Vitalità, Renascer e Espaço Saúde Mais, todos com e-mail terminado em `@seed2.local` e a mesma senha padronizada (`Senha123!`). Para listar todos eles, com especialidade e clínica, rode:

```sql
SELECT u.name, u.email, sp.name AS especialidade, c.name AS clinica
FROM doctors d
JOIN users u ON u.id = d.user_id
JOIN specialties sp ON sp.id = d.specialty_id
JOIN clinics c ON c.id = d.clinic_id
ORDER BY c.name, u.name;
```

### Pacientes

| Nome | E-mail | Senha |
| --- | --- | --- |
| Paciente de Demonstração | `paciente.demo@clinica.local` | `Senha123!` |
| João Pereira | `joao.pereira@email.local` | `Senha123!` |

O arquivo `seed_data_extra.sql` adiciona mais **90 pacientes** de teste (e-mail `@seed2.local`, mesma senha padronizada), usados para simular volume de agendamentos/histórico. Pacientes não possuem tela própria de login no site — as contas acima servem para que administrador e médico visualizem consultas e prontuários já preenchidos durante os testes.

## Configurar a API central

Quando a API compartilhada pelo aplicativo e pelo site estiver disponível, configure as variáveis de ambiente do PHP:

```text
VCTCC_DATA_MODE=api
VCTCC_API_URL=https://seu-dominio.example/api
VCTCC_API_TOKEN=seu-token-de-servico
VCTCC_API_TIMEOUT=8
```

Também é possível definir os valores diretamente em `app/config.php`, embora as variáveis de ambiente sejam preferíveis para não colocar tokens no código.

O cliente atual utiliza o contrato inicial abaixo:

| Método | Caminho | Finalidade |
| --- | --- | --- |
| `GET` | `/v1/state` | Retorna o estado compatível com o protótipo. |
| `PUT` | `/v1/state` | Persiste o estado enviado pelo site. |

A resposta pode ser o objeto diretamente ou um objeto com a propriedade `data`:

```json
{
  "data": {
    "clinics": [],
    "specialties": [],
    "users": [],
    "doctors": [],
    "doctor_schedules": [],
    "schedule_blocks": [],
    "appointment_slots": [],
    "appointments": [],
    "notifications": [],
    "medical_records": [],
    "payments": []
  }
}
```

Esse endpoint de estado é uma ponte de desenvolvimento. Para produção, recomenda-se evoluir a API para endpoints específicos, como `/v1/auth/login`, `/v1/doctors`, `/v1/appointments`, `/v1/schedules`, `/v1/medical-records` e `/v1/payments`, com autenticação e permissões no servidor. O aplicativo móvel e o site devem acessar a API, e nunca o banco central diretamente.

## Estrutura da integração

```text
Aplicativo móvel ─┐
                  ├── API central ─── Banco de dados único
Site PHP/PWA ─────┘
```

O banco central deve ficar protegido no servidor da API. O site e o aplicativo devem compartilhar regras de autenticação, validação, permissões e formato de respostas no backend.

## PWA

O projeto inclui:

| Arquivo | Função |
| --- | --- |
| `manifest.webmanifest` | Nome, ícone, cores, escopo e modo instalável da aplicação. |
| `service-worker.js` | Cache da casca visual e fallback básico quando a rede falha. |
| `assets/js/app.js` | Registro automático do service worker em `localhost` ou HTTPS. |
| `assets/brand/` | Pasta única para as imagens da marca. |

Para o navegador oferecer a instalação como aplicativo, sirva o projeto por `localhost` ou HTTPS. O cache não armazena respostas da API nem dados sensíveis; as operações que dependem do backend continuam exigindo conexão.

## Logos centralizadas

As imagens da marca estão concentradas em:

```text
assets/brand/vital-clinic-logo.svg
assets/brand/vital-clinic-mark.svg
```

Os templates usam esses caminhos diretamente. Para trocar a identidade visual, substitua os arquivos mantendo os mesmos nomes e formatos ou atualize os caminhos em `app/config.php`, `index.php` e `pages/auth/login.php`.

## Organização principal

| Caminho | Responsabilidade |
| --- | --- |
| `app/api_client.php` | Cliente HTTP para o backend central. |
| `app/repository.php` | Repositório de demonstração, estado compartilhado e ponte de persistência. |
| `app/auth.php` | Sessão e autenticação de administrador/médico. |
| `data/demo-state.json` | Dados locais do modo demo. |
| `manifest.webmanifest` | Configuração PWA. |
| `service-worker.js` | Cache offline da interface. |

## Observações para o TCC

A arquitetura deixa clara a separação entre **frontend**, **API** e **banco de dados**. O site funciona de forma independente em modo demonstração, mas a fonte oficial dos dados deverá ser a API central quando o aplicativo e o site forem integrados. Essa separação facilita explicar no TCC que o banco não é acessado diretamente pelo cliente.

Antes de publicar a API, implemente autenticação por token ou sessão, autorização por perfil, validação de entrada, controle de concorrência, tratamento de erros e CORS restrito aos domínios do projeto. Não coloque senha de banco ou credenciais administrativas no JavaScript ou no aplicativo móvel.

## Referências

[1]: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps "MDN — Progressive web apps"

[2]: https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API "MDN — Service Worker API"
