<?php
/**
 * Gera tabela consolidada de todos os resultados de qualidade de software
 * para o artigo do PPGMSB.
 *
 * Uso: php gerar-relatorio-qualidade.php [arquivo-sus.json]
 *
 * Lê os resultados dos testes 1–5 dos arquivos JSON gerados automaticamente.
 * O teste 6 (SUS) pode ser passado como argumento ou digitado interativamente.
 */

$base = __DIR__;

// ── Teste 1: PHPUnit + Cobertura ──────────────────────────────────────────────
function lerCobertura(string $base): array
{
    $txt = $base . '/coverage.txt';
    if (!file_exists($txt)) return ['linhas' => null, 'metodos' => null, 'classes' => null];

    $conteudo = file_get_contents($txt);
    preg_match('/Lines:\s+([\d.]+)%/', $conteudo, $ml);
    preg_match('/Methods:\s+([\d.]+)%/', $conteudo, $mm);
    preg_match('/Classes:\s+([\d.]+)%/', $conteudo, $mc);

    return [
        'linhas'  => isset($ml[1]) ? (float)$ml[1] : null,
        'metodos' => isset($mm[1]) ? (float)$mm[1] : null,
        'classes' => isset($mc[1]) ? (float)$mc[1] : null,
    ];
}

// ── Teste 2: Lighthouse ───────────────────────────────────────────────────────
function lerLighthouse(string $base): array
{
    $json = $base . '/lh-resumo.json';
    if (!file_exists($json)) return [];

    return json_decode(file_get_contents($json), true) ?? [];
}

// ── Teste 3: Análise de Código ────────────────────────────────────────────────
function lerQualidadeCodigo(string $base): array
{
    $json = $base . '/qualidade-codigo.json';
    if (!file_exists($json)) {
        // tenta phpstan-niveis.json
        $json2 = $base . '/phpstan-niveis.json';
        if (file_exists($json2)) {
            return ['phpstan' => json_decode(file_get_contents($json2), true)];
        }
        return [];
    }
    return json_decode(file_get_contents($json), true) ?? [];
}

// ── Teste 4: k6 ──────────────────────────────────────────────────────────────
function lerK6(string $base): array
{
    $json = $base . '/k6-resumo.json';
    if (!file_exists($json)) return [];
    return json_decode(file_get_contents($json), true) ?? [];
}

// ── Teste 5: OWASP ZAP ───────────────────────────────────────────────────────
function lerZap(string $base): array
{
    $json = $base . '/zap-resumo.json';
    if (!file_exists($json)) return [];
    return json_decode(file_get_contents($json), true) ?? [];
}

// ── Teste 6: SUS ─────────────────────────────────────────────────────────────
function lerSus(string $base, ?string $arquivo): array
{
    $json = $arquivo ?? $base . '/sus-resultados.json';
    if (!file_exists($json)) return [];
    $dados = json_decode(file_get_contents($json), true) ?? [];

    // Filtra exemplos
    $dados['participantes'] = array_filter(
        $dados['participantes'] ?? [],
        fn($p) => empty($p['_exemplo'])
    );

    if (empty($dados['participantes'])) return $dados;

    // Calcula médias por perfil
    $porPerfil = [];
    foreach ($dados['participantes'] as $p) {
        $porPerfil[$p['perfil']][] = $p['score'];
    }

    $medias = [];
    foreach ($porPerfil as $perfil => $scores) {
        $medias[$perfil] = round(array_sum($scores) / count($scores), 1);
    }

    $todos = array_map(fn($p) => $p['score'], $dados['participantes']);
    $dados['media_geral']  = round(array_sum($todos) / count($todos), 1);
    $dados['medias_perfil'] = $medias;
    $dados['n_total']      = count($todos);

    return $dados;
}

function classificarSus(float $score): string
{
    if ($score >= 90) return 'Excelente (A+)';
    if ($score >= 80) return 'Ótimo (A)';
    if ($score >= 70) return 'Bom (B)';
    if ($score >= 50) return 'Marginal (C–D)';
    return 'Insatisfatório (F)';
}

// ──────────────────────────────────────────────────────────────────────────────

$arquivoSus = $argv[1] ?? null;

$cobertura = lerCobertura($base);
$lighthouse = lerLighthouse($base);
$qualidade  = lerQualidadeCodigo($base);
$k6         = lerK6($base);
$zap        = lerZap($base);
$sus        = lerSus($base, $arquivoSus);

$sep = str_repeat('─', 70);

echo "\n{$sep}\n";
echo " RELATÓRIO DE QUALIDADE DE SOFTWARE — Sistema PPGMSB/UNEB\n";
echo " ISO 25010 · " . date('d/m/Y') . "\n";
echo "{$sep}\n\n";

// ── TEST 1: PHPUnit ───────────────────────────────────────────────────────────
echo "TESTE 1 — TESTES AUTOMATIZADOS (PHPUnit + Xdebug)\n";
echo "──────────────────────────────────────────────────\n";
if ($cobertura['linhas'] !== null) {
    printf("  Cobertura de linhas:   %.1f%%\n", $cobertura['linhas']);
    printf("  Cobertura de métodos:  %.1f%%\n", $cobertura['metodos']);
    printf("  Cobertura de classes:  %.1f%%\n", $cobertura['classes']);
} else {
    echo "  [!] coverage.txt não encontrado. Execute: php artisan test --coverage\n";
}
echo "\n";

// ── TEST 2: Lighthouse ────────────────────────────────────────────────────────
echo "TESTE 2 — DESEMPENHO FRONTEND (Lighthouse)\n";
echo "──────────────────────────────────────────\n";
if (!empty($lighthouse)) {
    printf("  %-22s %12s %16s %14s %6s\n", 'Página', 'Performance', 'Acessibilidade', 'Boas Práticas', 'SEO');
    echo "  " . str_repeat('-', 72) . "\n";
    $totP = $totA = $totB = $totS = 0;
    foreach ($lighthouse as $pg) {
        printf("  %-22s %12d %16d %14d %6d\n",
            $pg['pagina'], $pg['performance'], $pg['acessibilidade'],
            $pg['boasPraticas'], $pg['seo']);
        $totP += $pg['performance'];
        $totA += $pg['acessibilidade'];
        $totB += $pg['boasPraticas'];
        $totS += $pg['seo'];
    }
    $n = count($lighthouse);
    echo "  " . str_repeat('-', 72) . "\n";
    printf("  %-22s %12.0f %16.0f %14.0f %6.0f\n", 'MÉDIA',
        $totP/$n, $totA/$n, $totB/$n, $totS/$n);
} else {
    echo "  [!] lh-resumo.json não encontrado. Execute: node lh-audit.mjs\n";
}
echo "\n";

// ── TEST 3: Análise Estática ──────────────────────────────────────────────────
echo "TESTE 3 — ANÁLISE ESTÁTICA DE CÓDIGO (PHPStan + phpmetrics)\n";
echo "─────────────────────────────────────────────────────────────\n";
if (!empty($qualidade)) {
    printf("  Classes analisadas: %d\n",      $qualidade['classes_analisadas'] ?? 0);
    printf("  LOC total:          %d\n",      $qualidade['loc_total'] ?? 0);
    printf("  Complexidade média: %.2f\n",    $qualidade['cc_media'] ?? 0);
    printf("  Complexidade máx.:  %d\n",      $qualidade['cc_maxima'] ?? 0);
    printf("  %% comentários:     %.1f%%\n",  $qualidade['percentual_comentario'] ?? 0);

    if (!empty($qualidade['phpstan_erros'])) {
        echo "  PHPStan por nível:\n";
        foreach ($qualidade['phpstan_erros'] as $item) {
            printf("    Nível %d: %d erro(s)\n", $item['nivel'], $item['erros']);
        }
    }
} else {
    echo "  [!] qualidade-codigo.json não encontrado.\n";
}
echo "\n";

// ── TEST 4: k6 ────────────────────────────────────────────────────────────────
echo "TESTE 4 — TESTE DE CARGA (k6)\n";
echo "──────────────────────────────\n";
if (!empty($k6)) {
    printf("  Cenário:           %s\n", $k6['cenario'] ?? '');
    printf("  Total requisições: %d\n", $k6['total_reqs'] ?? 0);
    printf("  Taxa de falhas:    %.0f%%\n", $k6['taxa_falha_pct'] ?? 0);
    printf("  Checks OK:         %.0f%%\n", $k6['checks_ok_pct'] ?? 0);
    if (!empty($k6['rotas'])) {
        printf("\n  %-10s %9s %9s %9s %9s\n", 'Rota', 'Avg(ms)', 'p50(ms)', 'p95(ms)', 'Máx(ms)');
        echo "  " . str_repeat('-', 50) . "\n";
        foreach ($k6['rotas'] as $r) {
            printf("  %-10s %9d %9d %9d %9d\n",
                $r['rota'], $r['avg_ms'], $r['p50_ms'], $r['p95_ms'], $r['max_ms']);
        }
    }
    if (!empty($k6['observacao'])) {
        echo "\n  Nota: " . $k6['observacao'] . "\n";
    }
} else {
    echo "  [!] k6-resumo.json não encontrado.\n";
}
echo "\n";

// ── TEST 5: OWASP ZAP ────────────────────────────────────────────────────────
echo "TESTE 5 — SEGURANÇA (OWASP ZAP Baseline Scan)\n";
echo "────────────────────────────────────────────────\n";
if (!empty($zap)) {
    $alertas = $zap['alertas'] ?? $zap;
    $por_risco = [];
    if (is_array($alertas)) {
        foreach ($alertas as $a) {
            $r = $a['risco'] ?? $a['risk'] ?? 'Informativo';
            $por_risco[$r] = ($por_risco[$r] ?? 0) + 1;
        }
    }
    $riscos_ordem = ['Alto', 'Médio', 'Baixo', 'Informativo', 'High', 'Medium', 'Low', 'Informational'];
    foreach ($riscos_ordem as $r) {
        if (isset($por_risco[$r])) {
            printf("  Risco %-12s: %d alerta(s)\n", $r, $por_risco[$r]);
        }
    }
    echo "  Total de alertas: " . array_sum($por_risco) . "\n";
} else {
    echo "  [!] zap-resumo.json não encontrado. Execute o scan ZAP via Docker.\n";
}
echo "\n";

// ── TEST 6: SUS ───────────────────────────────────────────────────────────────
echo "TESTE 6 — USABILIDADE (SUS — System Usability Scale)\n";
echo "──────────────────────────────────────────────────────\n";
if (!empty($sus) && isset($sus['media_geral'])) {
    printf("  Participantes:      %d\n", $sus['n_total']);
    if (!empty($sus['medias_perfil'])) {
        foreach ($sus['medias_perfil'] as $perfil => $media) {
            printf("  Média %-13s: %.1f — %s\n", $perfil, $media, classificarSus($media));
        }
    }
    printf("  Média Geral:        %.1f — %s\n", $sus['media_geral'], classificarSus($sus['media_geral']));
} else {
    echo "  [!] sus-resultados.json não encontrado ou sem dados reais.\n";
    echo "      Aplique o questionário (sus-questionario.html) com usuários reais.\n";
    echo "      Use a calculadora (sus-calculadora.html) para registrar e exportar.\n";
    echo "      Depois execute: php gerar-relatorio-qualidade.php sus-resultados.json\n";
}
echo "\n";

// ── Resumo ISO 25010 ──────────────────────────────────────────────────────────
echo "{$sep}\n";
echo " MAPEAMENTO ISO 25010\n";
echo "{$sep}\n";
$dimensoes = [
    ['Confiabilidade',      'Teste 1',  'Cobertura de ' . ($cobertura['linhas'] ? round($cobertura['linhas']) . '%' : '?') . ' das linhas'],
    ['Eficiência de Desempenho', 'Testes 2+4', 'Lighthouse + k6 load test'],
    ['Manutenibilidade',   'Teste 3',  'PHPStan nível 5 + phpmetrics'],
    ['Segurança',          'Teste 5',  'OWASP ZAP Baseline Scan'],
    ['Usabilidade',        'Teste 6',  'SUS — média ' . ($sus['media_geral'] ?? '?')],
];
foreach ($dimensoes as [$dim, $teste, $detalhe]) {
    printf("  %-30s %-10s %s\n", $dim, $teste, $detalhe);
}
echo "\n";
echo "  Relatório gerado em: " . date('d/m/Y H:i:s') . "\n\n";
