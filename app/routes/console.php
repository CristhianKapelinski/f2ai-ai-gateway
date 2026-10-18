<?php

use App\Models\ApiKey;
use Illuminate\Support\Facades\Artisan;

Artisan::command('chave:criar {nome}', function (string $nome) {
    $this->line(ApiKey::gerar($nome));
})->purpose('Cria uma chave de API e mostra o texto dela');
