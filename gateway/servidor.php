<?php

// O mesmo /chat/completions do Laravel, num servidor com event loop (Swoole).
// A espera de 2 s é uma corrotina: enquanto ela dorme, o worker atende as outras.

use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

const RESPOSTA = '{"id":"chatcmpl-demo","object":"chat.completion","model":"demo","choices":[{"index":0,"message":{"role":"assistant","content":"Resposta fictícia."},"finish_reason":"stop"}]}';

// hashes das chaves já confirmadas no banco
$validas = [];

function chaveValida(string $cabecalho, array &$validas): bool
{
    if (strncasecmp($cabecalho, 'Bearer ', 7) !== 0) {
        return false;
    }

    $hash = hash('sha256', substr($cabecalho, 7));

    if (isset($validas[$hash])) {
        return true;
    }

    // chave ainda não vista: confere no banco do Laravel (assim uma chave nova vale na hora)
    $consulta = (new PDO('sqlite:'.getenv('DB_DATABASE')))->prepare('SELECT 1 FROM api_keys WHERE hash = ?');
    $consulta->execute([$hash]);

    if (! $consulta->fetchColumn()) {
        return false;
    }

    return $validas[$hash] = true;
}

// um processo só: com 1 vCPU, um event loop já usa o núcleo inteiro
$servidor = new Server('0.0.0.0', 9501, SWOOLE_BASE);
$servidor->set(['worker_num' => 1]);

$servidor->on('request', function (Request $req, Response $res) use (&$validas) {
    $res->header('Content-Type', 'application/json');

    if (! chaveValida($req->header['authorization'] ?? '', $validas)) {
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
