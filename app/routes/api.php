<?php

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoint da versão inicial. Na versão final, o nginx manda o /chat/completions
// para o gateway (gateway/servidor.php) e esta rota não recebe tráfego.
Route::post('/chat/completions', function (Request $request) {
    if (! ApiKey::valida($request->bearerToken())) {
        return response()->json(['error' => 'chave inválida'], 401);
    }

    $messages = $request->json('messages');

    if (! is_array($messages) || $messages === []) {
        return response()->json(['error' => 'messages é obrigatório'], 422);
    }

    // Simula o tempo de um modelo. Este sleep prende o worker os 2 s inteiros.
    sleep(2);

    return response()->json([
        'id' => 'chatcmpl-demo',
        'object' => 'chat.completion',
        'model' => 'demo',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Resposta fictícia.'],
            'finish_reason' => 'stop',
        ]],
    ]);
});
