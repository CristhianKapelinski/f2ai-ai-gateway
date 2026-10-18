# AI Gateway (desafio F2AI)

`POST /chat/completions` autenticado por chave de API, com resposta fictícia depois de 2 s, dentro de 1 vCPU, 512 MB e 4 workers.

![Vazão](resultados/vazao.png)

## Como rodar

```sh
docker compose up -d --build
```

Criar uma chave pela tela, em http://localhost:8080, ou pelo terminal:

```sh
docker compose exec -u www-data laravel php artisan chave:criar minha
```

![Tela de chaves](resultados/tela.png)

Testar o endpoint:

```sh
curl -X POST localhost:8080/chat/completions \
  -H "Authorization: Bearer <chave>" -H "Content-Type: application/json" \
  -d '{"messages":[{"role":"user","content":"oi"}]}'
```

Teste rápido do gateway (200, 401, 422, 405 e a chave depois de reiniciar): `gateway/teste.sh`

Testes do Laravel:

```sh
docker run --rm -v "$PWD/app:/app" -w /app composer:2 sh -c "composer install -q && vendor/bin/phpunit"
```

## Como funciona

| | Versão inicial (tag `inicial`) | Versão final (`main`) |
|---|---|---|
| Tela de chaves | Laravel no PHP-FPM | Laravel no PHP-FPM |
| `/chat/completions` | Laravel no PHP-FPM, `sleep(2)` | `gateway/servidor.php` (Swoole), `Coroutine::sleep(2)` |
| Workers | 4 do PHP-FPM | 1 do PHP-FPM + 1 do gateway |
| CPU (soma = 1 vCPU) | nginx 0,1 · Laravel 0,9 | nginx 0,4 · Laravel 0,1 · gateway 0,5 |
| Memória (soma = 512 MB) | nginx 64 · Laravel 448 | nginx 64 · Laravel 96 · gateway 352 |

Todos os serviços ficam no núcleo 0. O nginx manda `/chat/completions` para o gateway e o resto para o Laravel. As chaves ficam num SQLite em volume do Docker: o Laravel grava e o gateway lê.

## Medição

```sh
carga/rodar.sh <nome>        # 1, 4, 16 e 64 clientes, 60 s cada
python3 carga/graficos.py    # gráficos (precisa de matplotlib)
```

A versão inicial está no branch `versao-inicial` (o código da tag `inicial` com os scripts de medição atuais): `git checkout versao-inicial && docker compose up -d --build --remove-orphans`, depois `carga/rodar.sh inicial`.

- **Ferramenta**: k6 1.3.0 em Docker, nos núcleos 1 a 3, para não disputar CPU com a solução.
- **Carga**: cada cliente manda uma requisição, espera a resposta e manda a próxima (`constant-vus`).
- **Válida**: só resposta 200. Timeout do cliente: 60 s. Só conta o que termina dentro dos 60 s, e há 35 s de pausa antes de cada cenário para a fila do anterior esvaziar.
- **CPU**: tempo de CPU que o kernel contou para os contêineres da solução (cgroup), somado e dividido pelos 60 s; 100% = 1 núcleo. O `docker stats` arredonda para 0% nessa carga, por isso não serve aqui.
- **Memória**: `docker stats` a cada 2 s, mais ou menos, somando os serviços. Na versão final, uns 30 MB são do worker do PHP-FPM com o Laravel carregado para a tela.
- **Máquina**: AMD Ryzen 5 8600G (12 threads), 32 GB, Linux 7.0, Docker 29.4.

Resultados completos em `resultados/inicial` e `resultados/final`.

### Inicial (Laravel no PHP-FPM, 4 workers)

| simultâneas | válidas/s | mediana | p95 | erros | timeouts | CPU média | memória média / pico |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 0.48 | 2.01 s | 2.01 s | 0 | 0 | 0.48% | 36 / 38 MiB |
| 4 | 1.93 | 2.01 s | 2.01 s | 0 | 0 | 0.76% | 36 / 37 MiB |
| 16 | 1.93 | 8.03 s | 8.05 s | 0 | 0 | 0.83% | 37 / 38 MiB |
| 64 | 1.93 | 30.14 s | 32.13 s | 0 | 0 | 0.80% | 38 / 39 MiB |

### Final (gateway Swoole)

| simultâneas | válidas/s | mediana | p95 | erros | timeouts | CPU média | memória média / pico |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 0.48 | 2.00 s | 2.00 s | 0 | 0 | 0.03% | 41 / 41 MiB |
| 4 | 1.93 | 2.00 s | 2.00 s | 0 | 0 | 0.05% | 41 / 41 MiB |
| 16 | 7.73 | 2.00 s | 2.00 s | 0 | 0 | 0.14% | 42 / 43 MiB |
| 64 | 30.93 | 2.00 s | 2.01 s | 0 | 0 | 0.46% | 45 / 46 MiB |

![Latência](resultados/latencia.png)

### Autenticação sob carga

`carga/misto.js`: 64 clientes por 60 s, metade com chave inválida.

```sh
docker run --rm --network host --cpuset-cpus 1-3 -v "$PWD/carga:/carga" -e CHAVE=<chave> grafana/k6:1.3.0 run /carga/misto.js
```

| clientes | respostas certas | por segundo | erradas |
|---|---:|---:|---:|
| 32 com chave válida (esperado: 200) | 871 | 14.5 | 0 |
| 32 com chave inválida (esperado: 401) | 30415 | 507 | 0 |

Toda chave inválida foi recusada. As válidas ficam um pouco abaixo do teto de 16 por segundo porque as inválidas, sem espera, chegam a mais de 500 por segundo e ocupam a CPU do nginx.

## Gargalo, tentativas e limite

- **Gargalo**: na versão inicial, o `sleep(2)` prende o worker os 2 s inteiros. Com 4 workers, saem no máximo 4 respostas a cada 2 s (2 por segundo), e o resto vira fila: com 64 clientes, a latência chega a 30 s. A CPU fica perto de 0%; o limite não é processamento, é worker parado.
- **O que tentei**: mais workers e threads não são permitidos (contam no limite de 4). Descartei o Laravel Octane sem rodar: ele usa o Swoole, mas desliga as corrotinas (`'enable_coroutine' => false` em `src/Commands/StartSwooleCommand.php`, versão 2.22), então cada worker continua atendendo uma requisição por vez. O ganho veio de não prender o worker na espera: o gateway em Swoole faz a espera numa corrotina e, enquanto ela dorme, atende as outras requisições. Um processo só dá conta, porque com 1 vCPU mais processos não somam CPU. No teste com chaves inválidas, o nginx travou nos 0,1 vCPU que tinha; passei para 0,4 e o gateway para 0,5.
- **O que melhorou**: com 64 clientes, de 1,93 para 30,93 respostas válidas por segundo (16 vezes), e a latência mediana de 30 s para 2 s. A CPU por resposta caiu junto: a versão inicial gastou 0,80% de um núcleo para 1,9 respostas por segundo; a final, 0,46% para 31.
- **Limite que permaneceu**: o próprio teste. Cada cliente espera 2 s por resposta, então a vazão máxima é clientes ÷ 2 s (32 por segundo com 64 clientes), e a versão final fica nesse teto em todos os cenários. Os números ficam um pouco abaixo porque as primeiras respostas só chegam depois de 2 s e o que está em andamento no segundo 60 é cortado. Acima disso, o primeiro limite real que apareceu foi a CPU do nginx: no teste com chaves inválidas, a mais de 500 requisições por segundo, ele chegou aos 0,4 vCPU dele.

## Decisões

- **Laravel só no cadastro**, como o enunciado exige. O endpoint fica num servidor Swoole de um arquivo (`gateway/servidor.php`), porque no PHP-FPM e no Octane cada worker atende uma requisição por vez, e o Laravel guarda estado global (a requisição atual, por exemplo) que não pode ser dividido entre corrotinas.
- **SQLite num volume**: sem processo extra nem memória, e a chave sobrevive ao reinício.
- **Só o hash SHA-256 da chave no banco**. A chave tem 192 bits aleatórios, então não precisa de um hash lento como o bcrypt, que a 32 verificações por segundo passaria do vCPU inteiro.
- **Chaves em memória no gateway**: o banco só é consultado para uma chave ainda não vista, então uma chave nova vale na hora.
- **Só a requisição válida espera**: chave errada recebe 401 e corpo sem `messages` recebe 422, na hora.
- **Workers**: 1 do PHP-FPM e 1 do gateway. O mestre do PHP-FPM só gerencia os filhos; o nginx só repassa conexões. Mesmo contando o nginx, são 3.

## Uso de IA

Usei ferramentas de IA na escrita do código, dos testes de carga e da documentação. Revisei, executei e validei todas as partes.
