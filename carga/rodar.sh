#!/bin/sh
# Uso: carga/rodar.sh inicial|final
# Com a versão no ar (cd versao-<nome> && docker compose up -d --build), roda 1, 4, 16 e 64
# clientes por 60 s e grava em resultados/<nome>/.
set -e
cd "$(dirname "$0")/.."

raiz=$PWD
saida="$raiz/resultados/$1"
mkdir -p "$saida"
cd "versao-$1"
chave=$(docker compose exec -T -u www-data laravel php artisan chave:criar carga)

# tempo de CPU (µs) usado até agora pelos contêineres da versão, lido do cgroup de cada um
cpu_total() {
    for id in $(docker compose ps -q); do
        cat "/sys/fs/cgroup/system.slice/docker-$id.scope/cpu.stat"
    done | awk '/^usage_usec/ { s += $2 } END { print s }'
}

for vus in 1 4 16 64; do
    # espera a fila do cenário anterior esvaziar (até 32 s com 64 clientes na versão inicial)
    sleep 35

    # memória dos contêineres da versão, uma leitura a cada 2 s, mais ou menos
    while true; do
        { echo ---; docker stats --no-stream --format '{{.MemUsage}}' $(docker compose ps -q); } >> "$saida/memoria-$vus.csv"
    done &
    stats=$!
    antes=$(cpu_total)

    # o gerador fica fora do núcleo 0, onde está a solução
    docker run --rm -u "$(id -u)" --network host --cpuset-cpus 1-3 -v "$raiz/carga:/carga" -v "$saida:/saida" \
        -e CHAVE="$chave" -e VUS="$vus" grafana/k6:1.3.0 run --quiet \
        --summary-export "/saida/k6-$vus.json" /carga/teste.js

    echo $(( $(cpu_total) - antes )) > "$saida/cpu-$vus.txt"
    kill $stats
done

python3 "$raiz/carga/resumo.py" "$saida" | tee "$saida/resumo.md"
