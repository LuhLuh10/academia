<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

$situacao = [
    ['id' => 1,
    'nome' => 'João da Silva',
    'unidadeCurricular' => 'Programação Web',
    'aprovado' => true,],

    ['id' => 2,
    'nome' => 'Maria Souza',
    'unidadeCurricular' => 'Banco de Dados',
    'aprovado' => false,]
    ];

function lerSituacoes(string $arquivo, array $situacoesIniciais): array
{
    if (!file_exists($arquivo)) {
        return $situacoesIniciais;
    }

    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        throw new RuntimeException('Não foi possível ler os registros.');
    }

    $situacoes = json_decode($conteudo, true);
    if (!is_array($situacoes)) {
        throw new RuntimeException('Não foi possível ler os registros.');
    }

    return $situacoes;
}

function salvarSituacoes(string $arquivo, array $situacoes): bool
{
    $conteudo = json_encode($situacoes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $conteudo !== false && file_put_contents($arquivo, $conteudo, LOCK_EX) !== false;
}

$app = AppFactory::create();
$arquivo = getenv('SITUACOES_ARQUIVO');
if ($arquivo === false || $arquivo === '') {
    $arquivo = __DIR__ . '/situacoes.json';
}

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(false, false, false);
$errorMiddleware->setDefaultErrorHandler(function ($request, $exception) {
    $status = $exception instanceof HttpException ? $exception->getCode() : 500;
    $mensagem = 'Ocorreu um erro interno.';

    if ($status === 404) {
        $mensagem = 'Rota não encontrada.';
    } elseif ($status === 405) {
        $mensagem = 'Método HTTP não permitido.';
    } elseif ($status === 400) {
        $mensagem = 'Requisição inválida.';
    }

    $response = (new ResponseFactory())->createResponse($status);
    $response->getBody()->write(json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

// Status
$app->get('/status', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// GET para listar
$app->get('/situacao', function ($request, $response) use ($arquivo, $situacao) {
    $situacoes = lerSituacoes($arquivo, $situacao);
    $response->getBody()->write(json_encode($situacoes, JSON_UNESCAPED_UNICODE));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

// POST 
$app->post('/situacao', function ($request, $response) use ($arquivo, $situacao) {
    $dados = $request->getParsedBody();

    if (!is_array($dados)
        || !isset($dados['nome'], $dados['unidadeCurricular'], $dados['aprovado'])
        || !is_string($dados['nome'])
        || trim($dados['nome']) === ''
        || !is_string($dados['unidadeCurricular'])
        || trim($dados['unidadeCurricular']) === ''
        || !is_bool($dados['aprovado'])) {
        $response->getBody()->write(json_encode([
            'erro' => 'Informe nome, unidadeCurricular e aprovado corretamente.',
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(400);
    }

    $situacoes = lerSituacoes($arquivo, $situacao);
    $proximoId = 1;
    foreach ($situacoes as $situacao) {
        $proximoId = max($proximoId, $situacao['id'] + 1);
    }

    $novaSituacao = [
        'id' => $proximoId,
        'nome' => trim($dados['nome']),
        'unidadeCurricular' => trim($dados['unidadeCurricular']),
        'aprovado' => $dados['aprovado'],
    ];
    $situacoes[] = $novaSituacao;
    if (!salvarSituacoes($arquivo, $situacoes)) {
        throw new RuntimeException('Não foi possível salvar o registro.');
    }

    $response->getBody()->write(json_encode($novaSituacao, JSON_UNESCAPED_UNICODE));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(201);
});

// GET por ID
$app->get('/situacao/{id}', function ($request, $response, $args) use ($arquivo, $situacao) {
    $situacoes = lerSituacoes($arquivo, $situacao);

    foreach ($situacoes as $situacao) {
        if ($situacao['id'] === (int) $args['id']) {
            $dados = [
                'nome' => $situacao['nome'],
                'unidadeCurricular' => $situacao['unidadeCurricular'],
                'aprovado' => $situacao['aprovado'],
            ];
            $response->getBody()->write(json_encode($dados, JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withStatus(200);
        }
    }

    $response->getBody()->write(json_encode(['erro' => 'Estudante não encontrado.'], JSON_UNESCAPED_UNICODE));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(404);
});

// PUT
$app->put('/situacao/{id}', function ($request, $response, $args) use ($arquivo, $situacao) {
    $dados = $request->getParsedBody();

    if (!is_array($dados)
        || count($dados) !== 1
        || !array_key_exists('aprovado', $dados)
        || !is_bool($dados['aprovado'])) {
        $response->getBody()->write(json_encode([
            'erro' => 'Informe somente o campo aprovado como true ou false.',
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(400);
    }

    $situacoes = lerSituacoes($arquivo, $situacao);
    foreach ($situacoes as &$situacao) {
        if ($situacao['id'] === (int) $args['id']) {
            $situacao['aprovado'] = $dados['aprovado'];
            $resposta = [
                'nome' => $situacao['nome'],
                'unidadeCurricular' => $situacao['unidadeCurricular'],
                'aprovado' => $situacao['aprovado'],
            ];
            unset($situacao);

            if (!salvarSituacoes($arquivo, $situacoes)) {
                throw new RuntimeException('Não foi possível salvar o registro.');
            }

            $response->getBody()->write(json_encode($resposta, JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withStatus(200);
        }
    }
    unset($situacao);

    $response->getBody()->write(json_encode(['erro' => 'Estudante não encontrado.'], JSON_UNESCAPED_UNICODE));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(404);
});

// DELETE
$app->delete('/situacao/{id}', function ($request, $response, $args) use ($arquivo, $situacao) {
    $situacoes = lerSituacoes($arquivo, $situacao);

    foreach ($situacoes as $indice => $situacao) {
        if ($situacao['id'] === (int) $args['id']) {
            array_splice($situacoes, $indice, 1);
            if (!salvarSituacoes($arquivo, $situacoes)) {
                throw new RuntimeException('Não foi possível remover o registro.');
            }

            $response->getBody()->write(json_encode([
                'mensagem' => 'Registro removido com sucesso.',
            ], JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withStatus(200);
        }
    }

    $response->getBody()->write(json_encode(['erro' => 'Estudante não encontrado.'], JSON_UNESCAPED_UNICODE));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(404);
});
