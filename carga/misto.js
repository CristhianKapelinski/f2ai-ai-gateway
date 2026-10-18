// Autenticação sob carga: metade dos clientes usa uma chave inválida.
import http from 'k6/http';
import { check } from 'k6';

const corpo = JSON.stringify({ messages: [{ role: 'user', content: 'oi' }] });

export const options = {
  scenarios: {
    carga: { executor: 'constant-vus', vus: 64, duration: '60s', gracefulStop: '0s' },
  },
  summaryTrendStats: ['med', 'p(95)', 'max'],
};

export default function () {
  const invalida = __VU % 2 === 0;
  const chave = invalida ? 'sk-invalida' : __ENV.CHAVE;
  const r = http.post('http://localhost:8080/chat/completions', corpo, {
    headers: { Authorization: `Bearer ${chave}`, 'Content-Type': 'application/json' },
    timeout: '60s',
    tags: { chave: invalida ? 'invalida' : 'valida' },
  });

  if (invalida) {
    check(r, { 'invalida recusada (401)': (x) => x.status === 401 });
  } else {
    check(r, { 'valida respondida (200)': (x) => x.status === 200 });
  }
}
