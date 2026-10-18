import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const semResposta = new Counter('sem_resposta');
const corpo = JSON.stringify({ messages: [{ role: 'user', content: 'oi' }] });
const cabecalhos = { Authorization: `Bearer ${__ENV.CHAVE}`, 'Content-Type': 'application/json' };

export const options = {
  vus: Number(__ENV.VUS),
  duration: '60s',
  // conta só o que termina dentro dos 60 s
  gracefulStop: '0s',
  summaryTrendStats: ['med', 'p(95)', 'max'],
};

export default function () {
  const r = http.post('http://localhost:8080/chat/completions', corpo, { headers: cabecalhos, timeout: '60s' });
  check(r, { valida: (x) => x.status === 200 });
  if (r.status === 0) semResposta.add(1);
}
