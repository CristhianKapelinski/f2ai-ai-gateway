"""Junta os resultados de uma medição numa tabela em Markdown."""

import json
import sys
from pathlib import Path

PROJETO = "f2ai-ai-gateway-"


def mib(texto):
    numero = float(texto.rstrip("KMGiB"))
    unidade = texto.lstrip("0123456789.")
    return numero * {"KiB": 1 / 1024, "MiB": 1, "GiB": 1024}.get(unidade, 1 / 1024**2)


def recursos(arquivo):
    """CPU (% de um núcleo) e memória (MiB) somando os serviços, por leitura."""
    cpu, mem = [], []
    for bloco in arquivo.read_text().split("---")[1:]:
        linhas = [l.split(",") for l in bloco.strip().splitlines() if l.startswith(PROJETO)]
        if linhas:
            cpu.append(sum(float(c.rstrip("%")) for _, c, _ in linhas))
            mem.append(sum(mib(m.split(" / ")[0]) for _, _, m in linhas))
    return cpu, mem


pasta = Path(sys.argv[1])
print("| simultâneas | válidas/s | mediana | p95 | erros | timeouts | CPU média / pico | memória média / pico |")
print("|---:|---:|---:|---:|---:|---:|---:|---:|")
for vus in (1, 4, 16, 64):
    k6 = json.loads((pasta / f"k6-{vus}.json").read_text())["metrics"]
    validas = k6["checks"]["passes"]
    erros = k6["checks"]["fails"]
    timeouts = k6.get("sem_resposta", {}).get("count", 0)
    duracao = k6["http_req_duration"]
    cpu, mem = recursos(pasta / f"recursos-{vus}.csv")
    print(
        f"| {vus} | {validas / 60:.2f} | {duracao['med'] / 1000:.2f} s | {duracao['p(95)'] / 1000:.2f} s"
        f" | {erros} | {timeouts} | {sum(cpu) / len(cpu):.0f}% / {max(cpu):.0f}%"
        f" | {sum(mem) / len(mem):.0f} / {max(mem):.0f} MiB |"
    )
