<?php

// O /chat/completions: confere a chave e responde depois de 2 s, num servidor com event loop (Swoole).
// A espera de 2 s é uma corrotina: enquanto ela dorme, o worker atende as outras requisições.

use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

const RESPOSTA = '{"id":"chatcmpl-demo","object":"chat.completion","model":"demo","choices":[{"index":0,"message":{"role":"assistant","content":"Resposta fictícia."},"finish_reason":"stop"}]}';

// Confere o cabeçalho "Authorization: Bearer <chave>".
function chaveValida(string $cabecalho): bool
{
    // hashes já confirmados no banco; uma variável static continua existindo entre as chamadas
    static $validas = [];

    if (strncasecmp($cabecalho, 'Bearer ', 7) !== 0) {
        return false;
    }

    $hash = hash('sha256', substr($cabecalho, 7));

    if (isset($validas[$hash])) {
        return true;
    }

    // chave ainda não vista: confere no banco do Laravel, assim uma chave nova vale na hora
    $banco = new PDO('sqlite:'.getenv('DB_DATABASE'));
    $consulta = $banco->prepare('SELECT 1 FROM api_keys WHERE hash = ?');
    $consulta->execute([$hash]);

    if (! $consulta->fetchColumn()) {
        return false;
    }

    $validas[$hash] = true;

    return true;
}

// um processo só: com 1 vCPU, um event loop já usa o núcleo inteiro
$servidor = new Server('0.0.0.0', 9501, SWOOLE_BASE);
$servidor->set(['worker_num' => 1]);

$servidor->on('request', function (Request $req, Response $res) {
    $res->header('Content-Type', 'application/json');

    if ($req->server['request_method'] !== 'POST') {
        $res->status(405);
        $res->end('{"error":"use POST"}');

        return;
    }

    if (! chaveValida($req->header['authorization'] ?? '')) {
        $res->status(401);
        $res->end('{"error":"chave inválida"}');

        return;
    }

    $corpo = json_decode($req->rawContent() ?: '', true);
    $messages = is_array($corpo) ? ($corpo['messages'] ?? null) : null;

    if (! is_array($messages) || $messages === []) {
        $res->status(422);
        $res->end('{"error":"messages é obrigatório"}');

        return;
    }

    Coroutine::sleep(2);
    $res->end(RESPOSTA);
});

$servidor->start();
