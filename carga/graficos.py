"""Gera os gráficos do README a partir de resultados/inicial e resultados/final (precisa de matplotlib)."""

import json
from pathlib import Path

import matplotlib.pyplot as plt

RESULTADOS = Path(__file__).resolve().parent.parent / "resultados"
CLIENTES = [1, 4, 16, 64]
VERSOES = {"inicial": ("Inicial (PHP-FPM, 4 workers)", "#e74c3c", "o"), "final": ("Final (Swoole, event loop)", "#27ae60", "^")}


def metricas(versao, vus):
    return json.loads((RESULTADOS / versao / f"k6-{vus}.json").read_text())["metrics"]


def eixo_clientes(ax):
    ax.set_xscale("log", base=2)
    ax.set_xticks(CLIENTES, [str(c) for c in CLIENTES])
    ax.set_xlabel("requisições simultâneas")
    ax.grid(alpha=0.3)


fig, ax = plt.subplots(figsize=(6.4, 4))
ax.plot(CLIENTES, [c / 2 for c in CLIENTES], color="#34495e", lw=6, alpha=0.2, label="teto do teste (clientes ÷ 2 s)")
for versao, (nome, cor, marca) in VERSOES.items():
    ax.plot(CLIENTES, [metricas(versao, c)["checks"]["passes"] / 60 for c in CLIENTES], marker=marca, color=cor, label=nome)
eixo_clientes(ax)
ax.set_ylabel("respostas válidas por segundo")
ax.legend()
fig.tight_layout()
fig.savefig(RESULTADOS / "vazao.png", dpi=150)

fig, ax = plt.subplots(figsize=(6.4, 4))
for versao, (nome, cor, marca) in VERSOES.items():
    ax.plot(CLIENTES, [metricas(versao, c)["http_req_duration"]["med"] / 1000 for c in CLIENTES], marker=marca, color=cor, label=f"{nome}: mediana")
    ax.plot(CLIENTES, [metricas(versao, c)["http_req_duration"]["p(95)"] / 1000 for c in CLIENTES], ls="--", color=cor, alpha=0.6, label=f"{nome}: p95")
eixo_clientes(ax)
ax.set_yscale("log", base=2)
ax.set_yticks([2, 4, 8, 16, 32], ["2", "4", "8", "16", "32"])
ax.set_ylabel("latência (s)")
ax.legend(fontsize=8)
fig.tight_layout()
fig.savefig(RESULTADOS / "latencia.png", dpi=150)
