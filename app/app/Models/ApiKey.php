<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['nome', 'hash'];

    // Cria a chave e devolve o texto dela. Só o hash fica no banco.
    public static function gerar(string $nome): string
    {
        $chave = 'sk-'.bin2hex(random_bytes(24));

        static::create(['nome' => $nome, 'hash' => hash('sha256', $chave)]);

        return $chave;
    }
}
