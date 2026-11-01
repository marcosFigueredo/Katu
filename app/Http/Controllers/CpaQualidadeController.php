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
        $periodos = PeriodoQualidade::where('programa_id', $programa->id)->with('pesosQualidade')->orderByDesc('inicio_em')->get();

        // Quadriênio escolhido; senão o que contém hoje; senão o mais recente.
        $periodo = $periodos->firstWhere('id', (int) $request->query('periodo'))
            ?? $periodos->first(fn ($p) => $p->inicio_em->lte(today()) && $p->fim_em->gte(today()))
            ?? $periodos->first();

        $filtros = [
            'linhas' => array_map('intval', (array) $request->query('linhas', [])),
            'nivel' => in_array($request->query('nivel'), array_keys(CpaPainelService::NIVEIS), true) ? $request->query('nivel') : null,
        ];

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

    /** Programa em que o usuário logado tem mandato vigente na CPA; senão 403. */
    protected function programaCpa(): Programa
    {
        $programa = auth()->user()->programaCpa();
        abort_unless($programa, 403);

        return $programa;
    }
}
