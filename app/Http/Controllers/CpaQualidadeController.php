<?php

namespace App\Http\Controllers;

use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Services\IndiceQualidadeService;
use Illuminate\Http\Request;

/** Área da CPA: qualidade das dissertações e teses do programa. */
class CpaQualidadeController extends Controller
{
    public function index(Request $request, IndiceQualidadeService $servico)
    {
        $programa = $this->programaCpa();
        $periodos = PeriodoQualidade::where('programa_id', $programa->id)->orderByDesc('inicio_em')->get();

        // Período escolhido; senão o que contém hoje; senão o mais recente.
        $periodo = $periodos->firstWhere('id', (int) $request->query('periodo'))
            ?? $periodos->first(fn ($p) => $p->inicio_em->lte(today()) && $p->fim_em->gte(today()))
            ?? $periodos->first();

        $resultado = $periodo ? $servico->indicePrograma($periodo->load('pesosQualidade')) : null;

        return view('cpa.index', [
            'programa' => $programa,
            'periodos' => $periodos,
            'periodo' => $periodo,
            'resultado' => $resultado,
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
