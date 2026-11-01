import lighthouse from 'file:///C:/Users/marco/AppData/Roaming/npm/node_modules/lighthouse/core/index.js';
import * as chromeLauncher from 'file:///C:/Users/marco/AppData/Roaming/npm/node_modules/lighthouse/node_modules/chrome-launcher/dist/index.js';
import { writeFileSync } from 'fs';

const BASE = 'http://localhost:8000';

const PAGES = [
  { slug: 'login',            url: `${BASE}/login`,             auth: false },
  { slug: 'recuperar-senha',  url: `${BASE}/forgot-password`,   auth: false },
  { slug: 'baremas',          url: `${BASE}/baremas`,           auth: true  },
  { slug: 'bancas',           url: `${BASE}/bancas`,            auth: true  },
  { slug: 'mabac',            url: `${BASE}/mabac`,             auth: true  },
  { slug: 'avaliacoes',       url: `${BASE}/avaliacoes`,        auth: true  },
];

// ── Obtém cookie de sessão fazendo login ────────────────────────────────────
async function obterCookiesSessao() {
  // 1. pega o CSRF token da página de login
  const r1 = await fetch(`${BASE}/login`);
  const html = await r1.text();
  const csrfMatch = html.match(/name="_token"\s+value="([^"]+)"/);
  if (!csrfMatch) throw new Error('CSRF token não encontrado');
  const token = csrfMatch[1];
  const cookies1 = r1.headers.getSetCookie?.() ?? [];

  // 2. faz o POST de login
  const r2 = await fetch(`${BASE}/login`, {
    method: 'POST',
    redirect: 'manual',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Cookie': cookies1.map(c => c.split(';')[0]).join('; '),
    },
    body: new URLSearchParams({ email: 'audit@ppgmsb.test', password: 'auditpass', _token: token }),
  });

  const cookies2 = r2.headers.getSetCookie?.() ?? [];
  const allCookies = [...cookies1, ...cookies2]
    .map(c => c.split(';')[0])
    .filter(Boolean)
    .join('; ');

  return allCookies;
}

// ── Auditoria ───────────────────────────────────────────────────────────────
const chrome = await chromeLauncher.launch({
  chromeFlags: ['--headless', '--no-sandbox', '--disable-gpu'],
  chromePath:  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
});

let cookieHeader = '';
try {
  cookieHeader = await obterCookiesSessao();
  console.log('✓ Cookie de sessão obtido\n');
} catch (e) {
  console.warn('Aviso: não foi possível fazer login.', e.message);
}

const resultados = [];

for (const page of PAGES) {
  console.log(`Auditando: ${page.slug} (${page.url})`);
  try {
    const extraHeaders = (page.auth && cookieHeader)
      ? { Cookie: cookieHeader }
      : {};

    const result = await lighthouse(page.url, {
      port:           chrome.port,
      output:         'json',
      onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
      logLevel:       'error',
      extraHeaders,
    });

    writeFileSync(`lh-${page.slug}.json`, result.report);

    const cats  = result.lhr.categories;
    const scores = {
      pagina:         page.slug,
      performance:    Math.round(cats.performance.score * 100),
      acessibilidade: Math.round(cats.accessibility.score * 100),
      boasPraticas:   Math.round(cats['best-practices'].score * 100),
      seo:            Math.round(cats.seo.score * 100),
    };
    resultados.push(scores);

    console.log(`  Performance:    ${scores.performance}`);
    console.log(`  Acessibilidade: ${scores.acessibilidade}`);
    console.log(`  Boas Práticas:  ${scores.boasPraticas}`);
    console.log(`  SEO:            ${scores.seo}`);
  } catch (e) {
    console.error(`  ERRO: ${e.message}`);
  }
  console.log('');
}

try { await chrome.kill(); } catch {}

// Salva tabela resumo
writeFileSync('lh-resumo.json', JSON.stringify(resultados, null, 2));
console.log('✓ Resumo salvo em lh-resumo.json');
