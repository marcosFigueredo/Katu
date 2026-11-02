<?php

namespace App\Http\Controllers;

use App\Models\LinhaPesquisa;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Services\CpaPainelService;
use App\Services\IndiceQualidadeService;
use Illuminate\Http\Request;

/** Área da CPA: dados gerais e calculados da qualidade das dissertações e teses do quadriênio. */
class CpaQualidadeController extends Controller
{
    public function index(Request $request, CpaPainelService $painel)
    {
        $programa = $this->programaCpa();
        [$periodos, $periodo, $filtros] = $this->contexto($request, $programa);

        return view('cpa.index', [
            'programa' => $programa,
            'periodos' => $periodos,
            'periodo' => $periodo,
            'filtros' => $filtros,
            'linhas' => LinhaPesquisa::whereHas('areaConcentracao', fn ($q) => $q->where('programa_id', $programa->id))->orderBy('nome')->get(),
            'painel' => $periodo ? $painel->montar($periodo, $filtros, auth()->user()) : null,
            'classes' => IndiceQualidadeService::CLASSES,
            'dimensoes' => PeriodoQualidade::rotulos('dimensao'),
            'fontes' => PeriodoQualidade::FONTES,
        ]);
    }

    /** Planilha (CSV, ";" e UTF-8 com BOM) com os mesmos blocos do painel — sem nomes. */
    public function exportar(Request $request, CpaPainelService $servico)
    {
        $programa = $this->programaCpa();
        [, $periodo, $filtros] = $this->contexto($request, $programa);
        abort_unless($periodo, 404);
        $painel = $servico->montar($periodo, $filtros, auth()->user());
        $dimensoes = PeriodoQualidade::rotulos('dimensao');
        $fmt = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');

        $linhas = [];
        $p = $painel['panorama'];
        $linhas[] = ['Panorama', $periodo->nome];
        $linhas[] = ['Trabalhos', $p['n']];
        $linhas[] = ['Índice do programa', $fmt($p['indice']), $p['classe'] ? IndiceQualidadeService::CLASSES[$p['classe']] : ''];
        $linhas[] = ['IC 95%', $fmt($p['intervalo'][0] ?? null), $fmt($p['intervalo'][1] ?? null)];
        foreach (IndiceQualidadeService::CLASSES as $n => $nome) {
            $linhas[] = ['Trabalhos na classe', "{$n}. {$nome}", $p['distribuicao'][$n] ?? 0];
        }
        $linhas[] = [];
        $linhas[] = ['Distribuição por dimensão', 'Programa', 'n', 'Mínimo', 'Q1', 'Mediana', 'Q3', 'Máximo'];
        foreach ($dimensoes as $codigo => $nome) {
            $r = $p['resumo'][$codigo] ?? null;
            $linhas[] = [$nome, $fmt($p['dimensoes'][$codigo] ?? null), $r['n'] ?? 0, $fmt($r['min'] ?? null), $fmt($r['q1'] ?? null), $fmt($r['mediana'] ?? null), $fmt($r['q3'] ?? null), $fmt($r['max'] ?? null)];
        }
        $linhas[] = [];
        $linhas[] = array_merge(['Evolução', 'n', 'Índice (mediana)'], array_values($dimensoes));
        foreach ($painel['evolucao'] as $ano => $e) {
            $linhas[] = array_merge([$ano, $e['n'], $fmt($e['indice'])], array_map($fmt, array_values($e['dimensoes'])));
        }
        $linhas[] = [];
        $linhas[] = ['Recortes', 'Grupo', 'n', 'Mediana', 'Q1', 'Q3'];
        foreach ($painel['recortes'] as $tipo => $grupos) {
            foreach ($grupos as $rotulo => $g) {
                $linhas[] = [$tipo, $rotulo, $g['n'], $fmt($g['mediana']), $fmt($g['q1']), $fmt($g['q3'])];
            }
        }
        $linhas[] = [];
        $linhas[] = ['Pontos fortes', 'Dimensão', 'Mediana'];
        foreach ($painel['pontos']['fortes'] ?? [] as $l) {
            $linhas[] = [$l['pergunta'], $l['dimensao'], $fmt($l['mediana'])];
        }
        $linhas[] = ['Pontos fracos', 'Dimensão', 'Mediana'];
        foreach ($painel['pontos']['fracos'] ?? [] as $l) {
            $linhas[] = [$l['pergunta'], $l['dimensao'], $fmt($l['mediana'])];
        }
        $linhas[] = [];
        $c = $painel['confiabilidade'];
        $linhas[] = ['Confiabilidade', 'Diferença mediana entre avaliadores', $fmt($c['concordancia']['mediana_amplitude'] ?? null), '% até 2 pontos', $fmt($c['concordancia']['percentual_ate_2'] ?? null)];
        $linhas[] = ['Dimensão', 'Padrão', 'Sigiloso', 'Estudante − banca', 'Orientador − banca'];
        foreach ($dimensoes as $codigo => $nome) {
            $linhas[] = [$nome, $fmt($c['aberto_confidencial'][$codigo]['aberto'] ?? null), $fmt($c['aberto_confidencial'][$codigo]['confidencial'] ?? null),
                $fmt($c['espelho_banca'][$codigo] ?? null), $fmt($c['orientador_banca'][$codigo] ?? null)];
        }

        return response()->streamDownload(function () use ($linhas) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            foreach ($linhas as $linha) {
                fputcsv($saida, array_map([self::class, 'celulaSegura'], $linha), ';');
            }
            fclose($saida);
        }, 'cpa-qualidade-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Texto que começa com = + - @ (ou tab/CR) vira texto literal na planilha, para não ser executado como fórmula; números negativos ficam. */
    public static function celulaSegura($valor)
    {
        if (is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) && ! preg_match('/^-?\d+(,\d+)?$/', $valor)) {
            return "'".$valor;
        }

        return $valor;
    }

    /** [períodos, quadriênio escolhido (ou o que contém hoje, ou o mais recente), filtros]. */
    private function contexto(Request $request, Programa $programa): array
    {
        $periodos = PeriodoQualidade::where('programa_id', $programa->id)->with('pesosQualidade')->orderByDesc('inicio_em')->get();
        $periodo = $periodos->firstWhere('id', (int) $request->query('periodo'))
            ?? $periodos->first(fn ($p) => $p->inicio_em->lte(today()) && $p->fim_em->gte(today()))
            ?? $periodos->first();
        $filtros = [
            'linhas' => array_map('intval', (array) $request->query('linhas', [])),
            'nivel' => in_array($request->query('nivel'), array_keys(CpaPainelService::NIVEIS), true) ? $request->query('nivel') : null,
        ];

        return [$periodos, $periodo, $filtros];
    }

    /** Programa em que o usuário logado tem mandato vigente na CPA; senão 403. */
    protected function programaCpa(): Programa
    {
        $programa = auth()->user()->programaCpa();
        abort_unless($programa, 403);

        // Auditoria (conflito de interesse): todo acesso do membro à área da CPA fica registrado.
        \App\Models\AcessoCpa::create([
            'user_id' => auth()->id(),
            'programa_id' => $programa->id,
            'pagina' => (string) (request()->route()?->getName() ?? request()->path()),
            'filtros' => request()->query() ?: null,
        ]);

        return $programa;
    }
}
