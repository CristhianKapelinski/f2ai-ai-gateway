#!/bin/sh
# Uso: carga/rodar.sh <nome-da-medicao>
# Roda 1, 4, 16 e 64 clientes por 60 s e grava em resultados/<nome>/.
set -e
cd "$(dirname "$0")/.."

saida="resultados/$1"
mkdir -p "$saida"
chave=$(docker compose exec -T -u www-data laravel php artisan chave:criar carga)

# tempo de CPU (µs) usado até agora por todos os serviços, lido do cgroup de cada contêiner
cpu_total() {
    for id in $(docker compose ps -q); do
        cat "/sys/fs/cgroup/system.slice/docker-$id.scope/cpu.stat"
    done | awk '/^usage_usec/ { s += $2 } END { print s }'
}

for vus in 1 4 16 64; do
    # espera a fila do cenário anterior esvaziar (até 32 s com 64 clientes na versão inicial)
    sleep 35

    # memória dos serviços, uma leitura a cada 2 s, mais ou menos
    while true; do
        { echo ---; docker stats --no-stream --format '{{.Name}},{{.MemUsage}}'; } >> "$saida/memoria-$vus.csv"
    done &
    stats=$!
    antes=$(cpu_total)

    # o gerador fica fora do núcleo 0, onde está a solução
    docker run --rm -u "$(id -u)" --network host --cpuset-cpus 1-3 -v "$PWD/carga:/carga" -v "$PWD/$saida:/saida" \
        -e CHAVE="$chave" -e VUS="$vus" grafana/k6:1.3.0 run --quiet \
        --summary-export "/saida/k6-$vus.json" /carga/teste.js

    echo $(( $(cpu_total) - antes )) > "$saida/cpu-$vus.txt"
    kill $stats
done

python3 carga/resumo.py "$saida" | tee "$saida/resumo.md"
