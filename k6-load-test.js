import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

// ── Métricas customizadas ────────────────────────────────────────────────────
const loginDuration    = new Trend('duracao_login_ms',    true);
const baremasDuration  = new Trend('duracao_baremas_ms',  true);
const bancasDuration   = new Trend('duracao_bancas_ms',   true);
const mabacDuration    = new Trend('duracao_mabac_ms',    true);
const taxaErros        = new Rate('taxa_erros');
const requisicoes      = new Counter('total_requisicoes');

const BASE = 'http://localhost:8000';

// ── Cenário de carga ─────────────────────────────────────────────────────────
export const options = {
    scenarios: {
        carga_baixa: {
            executor:          'ramping-vus',
            startVUs:          0,
            stages: [
                { duration: '20s', target: 5  },   // ramp-up para 5 usuários
                { duration: '30s', target: 5  },   // sustenta 5 VUs
                { duration: '20s', target: 20 },   // ramp-up para 20
                { duration: '30s', target: 20 },   // sustenta 20 VUs
                { duration: '20s', target: 50 },   // pico 50 VUs
                { duration: '20s', target: 50 },   // sustenta pico
                { duration: '20s', target: 0  },   // ramp-down
            ],
        },
    },
    thresholds: {
        http_req_duration:    ['p(95)<2000'],   // 95% das req < 2 s
        http_req_failed:      ['rate<0.05'],    // < 5% de falhas
        duracao_baremas_ms:   ['p(95)<1500'],
        duracao_bancas_ms:    ['p(95)<1500'],
    },
};

// ── Obtém CSRF + cookie de sessão ────────────────────────────────────────────
function autenticar() {
    const r1 = http.get(`${BASE}/login`);
    const csrf = r1.html().find('input[name="_token"]').attr('value');
    if (!csrf) return null;

    const r2 = http.post(
        `${BASE}/login`,
        { email: 'audit@ppgmsb.test', password: 'auditpass', _token: csrf },
        { redirects: 0 }
    );
    // Retorna os cookies da sessão autenticada
    return r2.status === 302 ? r2.cookies : null;
}

// ── Cenário principal ────────────────────────────────────────────────────────
export default function () {
    const cookies = autenticar();
    if (!cookies) {
        taxaErros.add(1);
        return;
    }

    const params = { cookies };

    group('Páginas Públicas', () => {
        const r = http.get(`${BASE}/login`);
        loginDuration.add(r.timings.duration);
        requisicoes.add(1);
        check(r, { 'login: status 200': (r) => r.status === 200 });
        taxaErros.add(r.status !== 200);
    });

    sleep(0.5);

    group('Gestão de Baremas', () => {
        const r = http.get(`${BASE}/baremas`, params);
        baremasDuration.add(r.timings.duration);
        requisicoes.add(1);
        check(r, { 'baremas: status 200': (r) => r.status === 200 });
        taxaErros.add(r.status !== 200);
    });

    sleep(0.3);

    group('Gestão de Bancas', () => {
        const r = http.get(`${BASE}/bancas`, params);
        bancasDuration.add(r.timings.duration);
        requisicoes.add(1);
        check(r, { 'bancas: status 200': (r) => r.status === 200 });
        taxaErros.add(r.status !== 200);
    });

    sleep(0.3);

    group('Módulo MABAC', () => {
        const r = http.get(`${BASE}/mabac`, params);
        mabacDuration.add(r.timings.duration);
        requisicoes.add(1);
        check(r, { 'mabac: status 200': (r) => r.status === 200 });
        taxaErros.add(r.status !== 200);
    });

    sleep(0.5);
}
