<?php

function lerSituacoes(string $arquivo): array
{
    if (!file_exists($arquivo)) {
        return [];
    }

    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        throw new RuntimeException('Não foi possível ler os dados salvos.');
    }

    $situacoes = json_decode($conteudo, true);

    if (!is_array($situacoes)) {
        throw new RuntimeException('Não foi possível ler os dados salvos.');
    }

    return $situacoes;
}

function salvarSituacoes(string $arquivo, array $situacoes): bool
{
    $conteudo = json_encode($situacoes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return file_put_contents($arquivo, $conteudo, LOCK_EX) !== false;
}

function dadosValidos($dados): bool
{
    return is_array($dados)
        && isset($dados['nome'], $dados['unidadeCurricular'], $dados['aprovado'])
        && is_string($dados['nome'])
        && trim($dados['nome']) !== ''
        && is_string($dados['unidadeCurricular'])
        && trim($dados['unidadeCurricular']) !== ''
        && is_bool($dados['aprovado']);
}

function processarRequisicao(string $metodo, string $caminho, $dados, string $arquivo): array
{
    $partes = explode('/', trim(parse_url($caminho, PHP_URL_PATH), '/'));
    $situacoes = lerSituacoes($arquivo);

    if ($partes[0] !== 'situacao' || count($partes) > 2) {
        return [404, ['erro' => 'Endpoint não encontrado.']];
    }

    if (count($partes) === 1 && $metodo === 'POST') {
        if (!dadosValidos($dados)) {
            return [400, ['erro' => 'Informe nome, unidadeCurricular e aprovado corretamente.']];
        }

        $id = 1;
        foreach ($situacoes as $situacao) {
            $id = max($id, $situacao['id'] + 1);
        }

        $novaSituacao = [
            'id' => $id,
            'nome' => trim($dados['nome']),
            'unidadeCurricular' => trim($dados['unidadeCurricular']),
            'aprovado' => $dados['aprovado'],
        ];
        $situacoes[] = $novaSituacao;

        if (!salvarSituacoes($arquivo, $situacoes)) {
            return [500, ['erro' => 'Não foi possível salvar a situação.']];
        }

        return [201, $novaSituacao];
    }

    if (count($partes) === 1 && $metodo === 'GET') {
        return [200, $situacoes];
    }

    if (count($partes) === 2) {
        if (!ctype_digit($partes[1]) || (int) $partes[1] < 1) {
            return [404, ['erro' => 'Estudante não encontrado.']];
        }

        $id = (int) $partes[1];
        foreach ($situacoes as $indice => $situacao) {
            if ($situacao['id'] !== $id) {
                continue;
            }

            if ($metodo === 'GET') {
                return [200, [
                    'nome' => $situacao['nome'],
                    'unidadeCurricular' => $situacao['unidadeCurricular'],
                    'aprovado' => $situacao['aprovado'],
                ]];
            }

            if ($metodo === 'PUT') {
                if (!is_array($dados)
                    || count($dados) !== 1
                    || !array_key_exists('aprovado', $dados)
                    || !is_bool($dados['aprovado'])) {
                    return [400, ['erro' => 'Informe somente o campo aprovado como true ou false.']];
                }

                $situacoes[$indice]['aprovado'] = $dados['aprovado'];

                if (!salvarSituacoes($arquivo, $situacoes)) {
                    return [500, ['erro' => 'Não foi possível salvar a situação.']];
                }

                return [200, [
                    'nome' => $situacoes[$indice]['nome'],
                    'unidadeCurricular' => $situacoes[$indice]['unidadeCurricular'],
                    'aprovado' => $situacoes[$indice]['aprovado'],
                ]];
            }

            if ($metodo === 'DELETE') {
                array_splice($situacoes, $indice, 1);

                if (!salvarSituacoes($arquivo, $situacoes)) {
                    return [500, ['erro' => 'Não foi possível remover a situação.']];
                }

                return [200, ['mensagem' => 'Registro removido com sucesso.']];
            }

            return [405, ['erro' => 'Método não permitido.']];
        }

        return [404, ['erro' => 'Estudante não encontrado.']];
    }

    return [405, ['erro' => 'Método não permitido.']];
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');

    $conteudo = file_get_contents('php://input');
    $dados = $conteudo === '' ? null : json_decode($conteudo, true);

    try {
        [$status, $resposta] = processarRequisicao(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $dados,
            __DIR__ . '/situacoes.json'
        );
    } catch (RuntimeException $erro) {
        http_response_code(500);
        echo json_encode(['erro' => $erro->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($status);
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
}