# Situação Acadêmica

Aluna: Luiza Vittória Cardoso
Turma: 2 INFO

## 1. O que precisa

- PHP 7.4 ou superior

## 2. Iniciar a API

Abra um terminal na pasta do projeto e execute:

```sh
php -S localhost:8080 -t public public/index.php
```

A API estará disponível em `http://localhost:8080`. Deixe o terminal aberto enquanto usa a API. Para parar o servidor, pressione `Ctrl+C`.

Os registros são salvos no arquivo `src/situacoes.json`. O arquivo é criado quando o primeiro registro é cadastrado.

## 3. Executar os testes

Na pasta do projeto, execute:

```sh
php testes/testesAcademia.php
```

Não é necessário iniciar a API. O teste usa um arquivo temporário e não altera os dados do servidor. Quando tudo funciona, mostra:

```text
Todos os testes passaram.
```

## Endpoints disponíveis

Requisições com dados devem estar em JSON e usar o cabeçalho `Content-Type: application/json`. As respostas, inclusive as de erro, também são JSON.

| Método | Endereço | O que faz |
| --- | --- | --- |
| GET | `/status` | Verifica se a API está funcionando. |
| POST | `/situacao` | Cadastra uma situação com `nome`, `unidadeCurricular` e `aprovado`. |
| GET | `/situacao` | Lista as situações cadastradas. |
| GET | `/situacao/{id}` | Consulta uma situação pelo ID. |
| PUT | `/situacao/{id}` | Altera somente o campo `aprovado`. |
| DELETE | `/situacao/{id}` | Remove a situação. |

O campo `aprovado` deve ser `true` ou `false`. O `PUT` deve receber somente esse campo. Exemplo:

```json
{"aprovado":false}
```

Ao cadastrar, a API retorna `201`. Consultas, atualizações e exclusões feitas com sucesso retornam `200`. Dados inválidos retornam `400`; um ID que não existe retorna `404` com a mensagem `Estudante não encontrado.`.

## Evidências de testes

Os quatro endpoints foram testados via HTTP, com o servidor iniciado e sem registros anteriores. A sequência abaixo mostra as respostas e os códigos recebidos:

### Cadastro — `POST /situacao`

```json
{"id":1,"nome":"Maria da Silva","unidadeCurricular":"Desenvolvimento Web","aprovado":true}
```

Código HTTP: `201`

### Consulta — `GET /situacao/1`

```json
{"nome":"Maria da Silva","unidadeCurricular":"Desenvolvimento Web","aprovado":true}
```

Código HTTP: `200`

### Atualização somente de `aprovado` — `PUT /situacao/1`

Requisição:

```json
{"aprovado":false}
```

Resposta:

```json
{"nome":"Maria da Silva","unidadeCurricular":"Desenvolvimento Web","aprovado":false}
```

Código HTTP: `200`

### Exclusão — `DELETE /situacao/1`

```json
{"mensagem":"Registro removido com sucesso."}
```

Código HTTP: `200`

Após excluir, uma nova consulta ao mesmo ID retornou:

```json
{"erro":"Estudante não encontrado."}
```

Código HTTP: `404`