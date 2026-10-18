<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private array $corpo = ['messages' => [['role' => 'user', 'content' => 'oi']]];

    public function test_sem_chave_recebe_401(): void
    {
        $this->postJson('/chat/completions', $this->corpo)->assertStatus(401);
    }

    public function test_chave_invalida_recebe_401(): void
    {
        $this->withToken('sk-errada')->postJson('/chat/completions', $this->corpo)->assertStatus(401);
    }

    public function test_sem_messages_recebe_422(): void
    {
        $chave = ApiKey::gerar('teste');

        $this->withToken($chave)->postJson('/chat/completions', [])->assertStatus(422);
    }

    public function test_chave_valida_espera_e_responde(): void
    {
        $chave = ApiKey::gerar('teste');

        $inicio = microtime(true);
        $resposta = $this->withToken($chave)->postJson('/chat/completions', $this->corpo);

        $resposta->assertOk()->assertJsonPath('choices.0.message.role', 'assistant');
        $this->assertGreaterThanOrEqual(2.0, microtime(true) - $inicio);
    }
}
