#!/bin/sh
# Uso: carga/rodar.sh <nome-da-medicao>
# Roda 1, 4, 16 e 64 clientes por 60 s e grava em resultados/<nome>/.
set -e
cd "$(dirname "$0")/.."

saida="resultados/$1"
mkdir -p "$saida"
chave=$(docker compose exec -T -u www-data laravel php artisan chave:criar carga)

for vus in 1 4 16 64; do
    # CPU e memória dos serviços, uma leitura por segundo
    while true; do
        { echo ---; docker stats --no-stream --format '{{.Name}},{{.CPUPerc}},{{.MemUsage}}'; } >> "$saida/recursos-$vus.csv"
    done &
    stats=$!

    # o gerador fica fora do núcleo 0, onde está a solução
    docker run --rm --network host --cpuset-cpus 1-3 -v "$PWD/carga:/carga" -v "$PWD/$saida:/saida" \
        -e CHAVE="$chave" -e VUS="$vus" grafana/k6:1.3.0 run --quiet \
        --summary-export "/saida/k6-$vus.json" /carga/teste.js

    kill $stats
done

python3 carga/resumo.py "$saida" | tee "$saida/resumo.md"
