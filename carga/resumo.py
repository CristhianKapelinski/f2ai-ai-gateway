"""Junta os resultados de uma medição numa tabela em Markdown."""

import json
import sys
from pathlib import Path

PROJETO = "f2ai-ai-gateway-"


def mib(texto):
    """Converte o texto do docker stats ("13.57MiB") para MiB."""
    for unidade, fator in (("KiB", 1 / 1024), ("MiB", 1), ("GiB", 1024)):
        if texto.endswith(unidade):
            return float(texto[: -len(unidade)]) * fator
    return float(texto.rstrip("B")) / 1024**2  # em bytes


def memoria(arquivo):
    """Memória (MiB) somando os serviços, por leitura do docker stats."""
    leituras = []
    for bloco in arquivo.read_text().split("---")[1:]:
        linhas = [l.split(",") for l in bloco.strip().splitlines() if l.startswith(PROJETO)]
        if linhas:
            leituras.append(sum(mib(m.split(" / ")[0]) for _, m in linhas))
    return leituras


pasta = Path(sys.argv[1])
print("| simultâneas | válidas/s | mediana | p95 | erros | timeouts | CPU média | memória média / pico |")
print("|---:|---:|---:|---:|---:|---:|---:|---:|")
for vus in (1, 4, 16, 64):
    k6 = json.loads((pasta / f"k6-{vus}.json").read_text())["metrics"]
    validas = k6["checks"]["passes"]
    erros = k6["checks"]["fails"]
    timeouts = k6.get("sem_resposta", {}).get("count", 0)
    duracao = k6["http_req_duration"]
    # µs de CPU em 60 s; 100% = um núcleo inteiro
    cpu = int((pasta / f"cpu-{vus}.txt").read_text()) / 60e6 * 100
    mem = memoria(pasta / f"memoria-{vus}.csv")
    print(
        f"| {vus} | {validas / 60:.2f} | {duracao['med'] / 1000:.2f} s | {duracao['p(95)'] / 1000:.2f} s"
        f" | {erros} | {timeouts} | {cpu:.2f}% | {sum(mem) / len(mem):.0f} / {max(mem):.0f} MiB |"
    )
