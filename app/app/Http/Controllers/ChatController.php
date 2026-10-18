<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
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
    }
}
