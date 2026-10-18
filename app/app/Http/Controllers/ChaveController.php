<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChaveController extends Controller
{
    public function index(): View
    {
        return view('chaves', ['chaves' => ApiKey::latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(['nome' => 'required|string|max:100']);

        $chave = ApiKey::gerar($dados['nome']);

        return redirect()->route('chaves.index')->with('chave', $chave);
    }
}
