#!/bin/sh
# Confere o /chat/completions com a solução no ar: 200, 401, 422, 405 e a chave depois de reiniciar.
set -e
cd "$(dirname "$0")/.."

chave=$(docker compose exec -T -u www-data laravel php artisan chave:criar teste)
corpo='{"messages":[{"role":"user","content":"oi"}]}'

confere() {
    esperado=$1
    nome=$2
    shift 2
    obtido=$(curl -s -o /dev/null -w '%{http_code} em %{time_total} s' "$@" localhost:8080/chat/completions)
    echo "$nome: $obtido (esperado $esperado)"
    [ "${obtido%% *}" = "$esperado" ]
}

confere 200 "chave válida" -X POST -H "Authorization: Bearer $chave" -d "$corpo"
confere 401 "chave inválida" -X POST -H "Authorization: Bearer sk-errada" -d "$corpo"
confere 401 "sem chave" -X POST -d "$corpo"
confere 422 "sem messages" -X POST -H "Authorization: Bearer $chave" -d '{}'
confere 405 "GET" -H "Authorization: Bearer $chave"

docker compose restart > /dev/null 2>&1
sleep 3
confere 200 "depois de reiniciar" -X POST -H "Authorization: Bearer $chave" -d "$corpo"

echo "tudo certo"
