<?php

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('chaves', ['chaves' => ApiKey::latest()->get()]));

Route::post('/chaves', function (Request $request) {
    $dados = $request->validate(['nome' => 'required|string|max:100']);

    return redirect('/')->with('chave', ApiKey::gerar($dados['nome']));
});
