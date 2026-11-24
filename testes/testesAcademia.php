<?php

require_once __DIR__ . '/../scr/app.php';

function conferir(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$arquivo = tempnam(sys_get_temp_dir(), 'situacoes-');
file_put_contents($arquivo, '[]');

try {
    $dados = [
        'nome' => 'Maria da Silva',
        'unidadeCurricular' => 'Desenvolvimento Web',
        'aprovado' => true,
    ];

    [$status, $criada] = processarRequisicao('POST', '/situacao', $dados, $arquivo);
    conferir($status === 201 && $criada['id'] === 1, 'O cadastro da situação falhou.');

    [$status, $lista] = processarRequisicao('GET', '/situacao', null, $arquivo);
    conferir($status === 200 && count($lista) === 1, 'A consulta das situações falhou.');

    [$status, $buscada] = processarRequisicao('GET', '/situacao/1', null, $arquivo);
    conferir(
        $status === 200
            && $buscada === [
                'nome' => 'Maria da Silva',
                'unidadeCurricular' => 'Desenvolvimento Web',
                'aprovado' => true,
            ],
        'A consulta por ID falhou.'
    );

    [$status, $atualizada] = processarRequisicao('PUT', '/situacao/1', ['aprovado' => false], $arquivo);
    conferir(
        $status === 200
            && $atualizada['aprovado'] === false
            && $atualizada['nome'] === 'Maria da Silva'
            && $atualizada['unidadeCurricular'] === 'Desenvolvimento Web',
        'A atualização do campo aprovado falhou.'
    );

    [$status] = processarRequisicao(
        'PUT',
        '/situacao/1',
        ['aprovado' => true, 'nome' => 'Outro nome'],
        $arquivo
    );
    conferir($status === 400, 'O PUT deveria aceitar somente o campo aprovado.');

    [$status, $removida] = processarRequisicao('DELETE', '/situacao/1', null, $arquivo);
    conferir(
        $status === 200 && $removida === ['mensagem' => 'Registro removido com sucesso.'],
        'A remoção falhou.'
    );

    [$status, $erro] = processarRequisicao('GET', '/situacao/1', null, $arquivo);
    conferir(
        $status === 404 && $erro === ['erro' => 'Estudante não encontrado.'],
        'O registro excluído ainda foi encontrado ou a resposta de erro está incorreta.'
    );

    [$status] = processarRequisicao('POST', '/situacao', ['nome' => 'Sem os outros dados'], $arquivo);
    conferir($status === 400, 'Dados incompletos deveriam ser rejeitados.');

    echo "Todos os testes passaram.\n";
} finally {
    unlink($arquivo);
}