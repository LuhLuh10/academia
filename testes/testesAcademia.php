<?php

use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

$arquivoTeste = tempnam(sys_get_temp_dir(), 'situacoes-');
unlink($arquivoTeste);
putenv('SITUACOES_ARQUIVO=' . $arquivoTeste);

require_once __DIR__ . '/../src/app.php';

function conferir(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function fazerRequisicao(string $metodo, string $caminho, ?array $dados = null): array
{
    global $app;

    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest($metodo, 'http://localhost' . $caminho);

    if ($dados !== null) {
        $streamFactory = new StreamFactory();
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode($dados)));
    }

    $response = $app->handle($request);

    return [
        $response->getStatusCode(),
        json_decode((string) $response->getBody(), true),
    ];
}

try {
    [$status, $resposta] = fazerRequisicao('GET', '/status');
    conferir($status === 200 && $resposta === ['status' => 'ok'], 'A verificação da API falhou.');

    [$status, $lista] = fazerRequisicao('GET', '/situacao');
    conferir(
        $status === 200
            && $lista === [
                [
                    'id' => 1,
                    'nome' => 'João da Silva',
                    'unidadeCurricular' => 'Programação Web',
                    'aprovado' => true,
                ],
                [
                    'id' => 2,
                    'nome' => 'Maria Souza',
                    'unidadeCurricular' => 'Banco de Dados',
                    'aprovado' => false,
                ],
            ],
        'A lista inicial de situações acadêmicas está incorreta.'
    );

    [$status, $buscada] = fazerRequisicao('GET', '/situacao/1');
    conferir(
        $status === 200
            && $buscada === [
                'nome' => 'João da Silva',
                'unidadeCurricular' => 'Programação Web',
                'aprovado' => true,
            ],
        'A consulta por ID falhou.'
    );

    $dados = [
        'nome' => 'Maria da Silva',
        'unidadeCurricular' => 'Desenvolvimento Web',
        'aprovado' => true,
    ];

    [$status, $criada] = fazerRequisicao('POST', '/situacao', $dados);
    conferir($status === 201 && $criada['id'] === 3, 'O cadastro da situação falhou.');

    [$status, $lista] = fazerRequisicao('GET', '/situacao');
    conferir($status === 200 && count($lista) === 3, 'A consulta das situações falhou.');

    [$status, $buscada] = fazerRequisicao('GET', '/situacao/3');
    conferir(
        $status === 200
            && $buscada === [
                'nome' => 'Maria da Silva',
                'unidadeCurricular' => 'Desenvolvimento Web',
                'aprovado' => true,
            ],
        'A consulta por ID falhou.'
    );

    [$status, $atualizada] = fazerRequisicao('PUT', '/situacao/3', ['aprovado' => false]);
    conferir(
        $status === 200
            && $atualizada['aprovado'] === false
            && $atualizada['nome'] === 'Maria da Silva'
            && $atualizada['unidadeCurricular'] === 'Desenvolvimento Web',
        'A atualização do campo aprovado falhou.'
    );

    [$status] = fazerRequisicao('PUT', '/situacao/3', ['aprovado' => true, 'nome' => 'Outro nome']);
    conferir($status === 400, 'O PUT deveria aceitar somente o campo aprovado.');

    [$status, $removida] = fazerRequisicao('DELETE', '/situacao/3');
    conferir(
        $status === 200 && $removida === ['mensagem' => 'Registro removido com sucesso.'],
        'A remoção falhou.'
    );

    [$status, $erro] = fazerRequisicao('GET', '/situacao/3');
    conferir(
        $status === 404 && $erro === ['erro' => 'Estudante não encontrado.'],
        'O registro excluído ainda foi encontrado ou a resposta de erro está incorreta.'
    );

    [$status, $lista] = fazerRequisicao('GET', '/situacao');
    conferir($status === 200 && count($lista) === 2, 'A exclusão não atualizou a lista.');

    [$status] = fazerRequisicao('POST', '/situacao', ['nome' => 'Sem os outros dados']);
    conferir($status === 400, 'Dados incompletos deveriam ser rejeitados.');

    echo "Todos os testes passaram.\n";
} finally {
    putenv('SITUACOES_ARQUIVO');
    unlink($arquivoTeste);
}