# Projeto de Exemplo: Calculadora em Python

Este é um projeto simples de calculadora em Python usado para demonstrar como configurar CI/CD com GitHub Actions.
 
---

## O que é o GitHub Actions?

O **GitHub Actions** é uma ferramenta de automação de workflows integrada ao GitHub. Ele permite que desenvolvedores configurem processos automatizados diretamente em seus repositórios, como testes, builds e implantações, usando um arquivo de configuração YAML. Lançado oficialmente em 2018, o GitHub Actions tornou-se uma solução poderosa para gerenciar **Integração Contínua (CI)** e **Entrega Contínua (CD)**.

---

## História do GitHub Actions

O GitHub Actions foi lançado em outubro de 2018 pela equipe do GitHub, como parte de sua estratégia para transformar o GitHub em uma plataforma completa de desenvolvimento. Inicialmente, sua funcionalidade era focada na automação genérica, mas logo evoluiu para incluir suporte completo a CI/CD. Desde então, ele tem sido adotado por grandes empresas e projetos de código aberto devido à sua integração nativa e facilidade de uso.

---

## Vantagens e Desvantagens do GitHub Actions

### **Vantagens**
1. **Integração Nativa com o GitHub:**
   - Totalmente integrado ao repositório, eliminando a necessidade de ferramentas externas de CI/CD.
2. **Facilidade de Configuração:**
   - Arquivos YAML simples tornam a configuração intuitiva, mesmo para iniciantes.
3. **Flexibilidade:**
   - Suporte para várias linguagens e plataformas, além de workflows personalizados.
4. **Gratuito para Projetos Open Source:**
   - Oferece minutos gratuitos para execução de workflows em repositórios públicos.
5. **Marketplace de Actions:**
   - Biblioteca com milhares de actions prontas para uso.

### **Desvantagens**
1. **Limitações do Plano Gratuito:**
   - Em repositórios privados, o uso gratuito é limitado.
2. **Curva de Aprendizado Inicial:**
   - Configurar workflows mais complexos pode ser desafiador.
3. **Dependência do GitHub:**
   - Funciona apenas com repositórios hospedados no GitHub.

---

## Suporte a Linguagens e Limitações

### **Linguagens Suportadas**
O GitHub Actions suporta nativamente as principais linguagens de programação, incluindo:
- Python
- JavaScript/Node.js
- Java
- Ruby
- PHP
- Go
- C++
- Rust
- Swift

Também permite executar comandos personalizados, contêineres Docker e workflows para tecnologias específicas.

### **Limitações**
1. Linguagens ou ferramentas menos conhecidas podem exigir configurações adicionais.
2. Performance pode ser menor em comparação a soluções auto-hospedadas para workflows complexos.

---

## Empresas e Projetos que Utilizam GitHub Actions

O GitHub Actions é amplamente utilizado por organizações de renome e projetos de código aberto. Alguns exemplos incluem:

- **Nubank:** Automação de CI/CD para integração contínua.
- **VTEX:** Testes e implantações automatizadas.
- **QuintoAndar:** Gerenciamento de pipelines de desenvolvimento.
- **Open Source:** Projetos como React, Vue.js e TensorFlow utilizam o GitHub Actions para automação.

---

## Pré-requisitos
- Python 3.8 ou superior
- Git

---

## Passo a Passo

### 1. Clonar o Repositório
```bash
git clone <https://github.com/pedroAmalfi/AulaCICD.git>
cd <AulaCICD>
```

### 2. Configurar o Ambiente
Crie um ambiente virtual e instale as dependências:
```bash
python -m venv venv
source venv/bin/activate # Para Linux/Mac
venv\Scripts\activate  # Para Windows
pip install -r requirements.txt
```

### 3. Executar os Testes Localmente
Verifique se os testes estão funcionando:
```bash
python -m unittest test_calculadora.py
```
Para testar manualmente, abra o terminal Python:
```bash
python
```
Importe e teste as funções:
```python
from calculadora import soma, subtrai, multiplica, divide

print(soma(2, 3))          # Saída: 5
print(multiplica(4, 2))    # Saída: 8
print(divide(10, 2))       # Saída: 5.0
```

Se tudo der certo até aqui, seu projeto está pronto para testar o CI/CD!
---

## Configurando CI/CD

### GitHub Actions
1. No GitHub, vá até a aba **Actions** do seu repositório.
2. Crie um arquivo no caminho `.github/workflows/python-app.yml` com o seguinte conteúdo:
```yaml
name: Python application

on:
  push:
    branches:
      - main

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3
    - name: Set up Python
      uses: actions/setup-python@v4
      with:
        python-version: '3.8'
    - name: Install dependencies
      run: |
        python -m pip install --upgrade pip
        pip install -r requirements.txt
    - name: Run tests
      run: |
        python -m unittest test_calculadora.py
```
3. Faça um commit e um push para iniciar o workflow.

---

## Resultados
- Testes automatizados executados a cada push ou pull request no repositório.
- Feedback imediato sobre erros ou falhas no código.

---

## Explicando o Código do python-app.yml

### Que Passos Ele Executa?

#### **1. Início do Workflow:**
- É disparado pelo evento configurado em `on:`, como um **push** na branch `main`.

#### **2. Configuração do Ambiente:**
- O GitHub Actions prepara uma máquina virtual em `runs-on: ubuntu-latest`.
- Faz o **checkout do código-fonte** (baixa o código do repositório para o ambiente de execução).

#### **3. Configuração do Python:**
- Configura a versão do Python com `setup-python`.

#### **4. Instalação das Dependências:**
- Executa o comando:
  ```bash
  pip install -r requirements.txt
  ```
  Isso garante que as dependências necessárias ao projeto sejam instaladas.

#### **5. Execução dos Testes:**
- Com o comando:
  ```bash
  python -m unittest test_calculadora.py
  ```
  Ele roda os testes definidos no arquivo `test_calculadora.py`.
- O `unittest` verifica se as funções da calculadora estão funcionando como esperado.

#### **6. Resultado do Workflow:**
- **Sucesso:** Todos os testes passam.
- **Falha:** Algum teste retorna um erro ou exceção, interrompendo o workflow.

---

## Testando o CI com GitHub Actions

Para testar se o CI do GitHub Actions está funcionando corretamente, siga os passos abaixo:

1. **Introduza um erro proposital:**
   - No arquivo `calculadora.py`, altere a função `soma` para realizar uma subtração:
     ```python
     def soma(a, b):
         return a - b  # Erro proposital
     ```
   - Faça um commit e um push com a alteração:
     ```bash
     git add calculadora.py
     git commit -m "Simulação de erro para testar CI"
     git push
     ```

2. **Verifique o erro no GitHub Actions:**
   - Você receberá um e-mail notificando que o workflow falhou.
   - Vá até a aba **Actions** no GitHub e veja que o workflow está marcado em vermelho, indicando um erro. Consulte os logs para verificar a falha.
   - ![Erro no Workflow](https://github.com/pedroAmalfi/AulaCICD/blob/main/assets/erro.PNG?raw=true)

3. **Corrija o erro:**
   - Restaure a função `soma` para seu estado correto:
     ```python
     def soma(a, b):
         return a + b  # Correção
     ```
   - Faça um novo commit e push:
     ```bash
     git add calculadora.py
     git commit -m "Correção do erro proposital"
     git push
     ```

4. **Verifique o sucesso no GitHub Actions:**
   - Após corrigir o erro, o workflow será executado novamente.
   - Agora, ele será marcado em verde, indicando que todos os testes foram aprovados.
   - ![Sucesso  no Workflow](https://github.com/pedroAmalfi/AulaCICD/blob/main/assets/sucesso.PNG?raw=true)

Esses passos demonstram como o GitHub Actions reage a erros e confirma quando os problemas são resolvidos, garantindo a confiabilidade do código no processo de integração contínua.

---

## Testando o Continuous Delivery com GitHub Actions

Nesta etapa, vamos simular um processo de **Continuous Delivery (CD)** utilizando o GitHub Actions para empacotar o projeto da calculadora como um artefato.

1. **Atualize o arquivo do workflow:**
   - Adicione a etapa de empacotamento e upload de artefato ao arquivo `.github/workflows/python-app.yml`:
     ```yaml
     name: Python application with CD

     on:
       push:
         branches:
           - main

     jobs:
       build:
         runs-on: ubuntu-latest

         steps:
         - uses: actions/checkout@v3
         - name: Set up Python
           uses: actions/setup-python@v4
           with:
             python-version: '3.8'
         - name: Install dependencies
           run: |
             python -m pip install --upgrade pip
             pip install -r requirements.txt
         - name: Run tests
           run: |
             python -m unittest test_calculadora.py
         - name: Package project
           run: zip -r calculadora.zip .
         - name: Upload artifact
           uses: actions/upload-artifact@v3
           with:
             name: calculadora-artifact
             path: calculadora.zip
     ```

2. **Faça um commit e push:**
   - Após editar o workflow, salve o arquivo, faça o commit e o push:
     ```bash
     git add .github/workflows/python-app.yml
     git commit -m "Adicionado suporte ao CD no workflow"
     git push
     ```

3. **Verifique o workflow no GitHub Actions:**
   - Vá para a aba **Actions** e observe o novo workflow.
   - Certifique-se de que os testes são executados e o artefato é empacotado corretamente.

4. **Baixe o artefato gerado:**
   - Após a conclusão bem-sucedida do workflow, clique no job do workflow em **Actions**.
   - Na seção **Artifacts**, baixe o arquivo `calculadora-artifact`.

5. **Entendendo as novas etapas do workflow:**

   - **Empacotamento do Projeto:**
     - A etapa:
       ```yaml
       - name: Package project
         run: zip -r calculadora.zip .
       ```
       Compacta todos os arquivos do repositório em um único arquivo ZIP chamado `calculadora.zip`. Isso é útil para organizar e distribuir os arquivos do projeto de maneira prática.

   - **Upload do Artefato:**
     - A etapa:
       ```yaml
       - name: Upload artifact
         uses: actions/upload-artifact@v3
         with:
           name: calculadora-artifact
           path: calculadora.zip
       ```
       Envia o arquivo `calculadora.zip` para o GitHub como um artefato associado à execução do workflow. Ele pode ser baixado manualmente na aba **Actions** do repositório.

6. **Validação do Processo:**
   - Certifique-se de que as etapas do workflow foram executadas sem erros.
   - Confirme que o artefato gerado está acessível na aba **Actions** e que o arquivo compactado contém todos os arquivos esperados do projeto.

7. **Encontrando os Artefatos:**
 - Vá na Aba Action em seguida clique sobre o ultimo Action executado
 - Após isso, desça até o final da página e encontrará a seção Artifacts conforme imagem abaixo:
 - ![Artefato no Workflow](https://github.com/pedroAmalfi/AulaCICD/blob/main/assets/artefato.PNG?raw=true)
 - Com isso poderá fazer do download da ultima versão do repositório atualizada.
---


---
### **Atividade**
- utilizando este projeto como base, crie um repositório no GitHub com um novo projeto (Sem ser a calculadora)
- Em seguida crie um teste de validação (CI)
- Gere artefatos (CD)
- Entregue o link do GitHub criado via EMAIL contendo imagens demonstrando os Teste do Action em ação (CI) e print do repositório criado com os artefatos compactados (CD) como apresentado no projeto em aula.
