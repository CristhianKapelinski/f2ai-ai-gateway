<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_abre(): void
    {
        $this->get('/')->assertOk()->assertSee('Criar chave');
    }

    public function test_cria_chave_e_guarda_so_o_hash(): void
    {
        $this->post('/chaves', ['nome' => 'minha'])->assertRedirect('/');

        $chave = session('chave');
        $this->assertStringStartsWith('sk-', $chave);
        $this->assertDatabaseHas('api_keys', ['nome' => 'minha', 'hash' => hash('sha256', $chave)]);
        $this->assertDatabaseMissing('api_keys', ['hash' => $chave]);
    }
}
