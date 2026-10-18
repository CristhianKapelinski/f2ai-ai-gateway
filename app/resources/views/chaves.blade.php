<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chaves de API</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 3rem auto; padding: 0 1rem; color: #222; }
        input, button { font: inherit; padding: .5rem .75rem; }
        .chave { background: #eef7ee; border: 1px solid #9c9; padding: 1rem; margin: 1rem 0; }
        code { font-size: 1.05rem; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        td, th { text-align: left; padding: .4rem; border-bottom: 1px solid #ddd; }
        .erro { color: #b00; }
    </style>
</head>
<body>
    <h1>Chaves de API</h1>

    @if (session('chave'))
        <div class="chave">
            <p>Copie a chave agora. Ela não aparece de novo.</p>
            <code>{{ session('chave') }}</code>
        </div>
    @endif

    <form method="post" action="{{ route('chaves.store') }}">
        @csrf
        <input name="nome" placeholder="Nome da chave" required maxlength="100" value="{{ old('nome') }}">
        <button type="submit">Criar chave</button>
        @error('nome') <p class="erro">{{ $message }}</p> @enderror
    </form>

    @if ($chaves->isNotEmpty())
        <table>
            <tr><th>Nome</th><th>Criada em</th></tr>
            @foreach ($chaves as $chave)
                <tr><td>{{ $chave->nome }}</td><td>{{ $chave->created_at->format('d/m/Y H:i') }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
