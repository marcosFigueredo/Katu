<?php

namespace App\Http\Controllers;

use App\Models\Banca;
use App\Models\OrientacaoAvaliacao;
use App\Services\IeoService;
use Illuminate\Http\Request;

class OrientacaoAvaliacaoController extends Controller
{
    public function __construct(private IeoService $ieoService) {}

    // GET /bancas/{banca}/orientacao-avaliacao/create — discente
    public function create(Banca $banca)
    {
        $user = auth()->user();

        if ($user->categoria !== 'discente') {
            abort(403);
        }

        if (in_array($banca->status, ['finalizada', 'cancelada'])) {
            return redirect()->route('meu-trabalho.index')
                ->with('error', 'Esta banca já foi encerrada e não aceita mais avaliações.');
        }

        $banca->loadMissing([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $trabalho = $banca->trabalhoAcademico;
        $vinculo  = $trabalho?->discenteVinculo;

        // Garante que o discente logado é o dono deste trabalho
        if ($vinculo?->discenteProfile?->user_id !== $user->id) {
            abort(403);
        }

        $existente = OrientacaoAvaliacao::where('banca_id', $banca->id)
            ->where('discente_programa_vinculo_id', $vinculo->id)
            ->exists();

        if ($existente) {
            return redirect()->route('meu-trabalho.index')
                ->with('info', 'Você já preencheu o formulário de avaliação da orientação para esta banca.');
        }

        $orientacao = $trabalho->orientacoes->firstWhere('tipo', 'orientador');

        return view('orientacao-avaliacao.form', compact('banca', 'trabalho', 'orientacao', 'vinculo'));
    }

    // POST /bancas/{banca}/orientacao-avaliacao — discente
    public function store(Request $request, Banca $banca)
    {
        $user = auth()->user();

        if ($user->categoria !== 'discente') {
            abort(403);
        }

        if (in_array($banca->status, ['finalizada', 'cancelada'])) {
            abort(403);
        }

        $banca->loadMissing([
            'trabalhoAcademico.discenteVinculo.discenteProfile',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo',
        ]);

        $trabalho = $banca->trabalhoAcademico;
        $vinculo  = $trabalho?->discenteVinculo;

        if ($vinculo?->discenteProfile?->user_id !== $user->id) {
            abort(403);
        }

        $orientacao = $trabalho->orientacoes->firstWhere('tipo', 'orientador');

        if (! $orientacao) {
            return back()->with('error', 'Orientador não encontrado para esta banca.');
        }

        // Evita duplicatas
        if (OrientacaoAvaliacao::where('banca_id', $banca->id)->where('discente_programa_vinculo_id', $vinculo->id)->exists()) {
            return redirect()->route('meu-trabalho.index')
                ->with('info', 'Avaliação já registrada.');
        }

        $dados = $request->validate([
            'item_12' => ['required', 'integer', 'min:1', 'max:5'],
            'item_21' => ['required', 'integer', 'min:1', 'max:5'],
            'item_22' => ['required', 'integer', 'min:1', 'max:5'],
            'item_31' => ['required', 'integer', 'min:1', 'max:5'],
            'item_32' => ['required', 'integer', 'min:1', 'max:5'],
            'item_41' => ['required', 'integer', 'min:1', 'max:5'],
            'item_42' => ['required', 'integer', 'min:1', 'max:5'],
            'item_51' => ['required', 'integer', 'min:1', 'max:5'],
            'item_52' => ['required', 'integer', 'min:1', 'max:5'],
            'item_61' => ['required', 'integer', 'min:1', 'max:5'],
            'item_62' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $item11 = $this->ieoService->calcularItem11Objetivo($banca);

        $avaliacao = OrientacaoAvaliacao::create(array_merge($dados, [
            'banca_id'                    => $banca->id,
            'discente_programa_vinculo_id'=> $vinculo->id,
            'docente_programa_vinculo_id' => $orientacao->docente_programa_vinculo_id,
            'item_11_objetivo'            => $item11,
        ]));

        $this->ieoService->calcular($avaliacao);

        return redirect()->route('meu-trabalho.index')
            ->with('success', 'Avaliação da orientação registrada com sucesso. Obrigado!');
    }

    // GET /orientacao-avaliacao — secretaria (visão agregada)
    public function index()
    {
        $user = auth()->user();

        if ($user->categoria !== 'sec') {
            abort(403);
        }

        $avaliacoes = OrientacaoAvaliacao::with([
            'banca.trabalhoAcademico.linhaPesquisa',
            'docenteProgramaVinculo.docenteProfile.user',
        ])
            ->whereNotNull('ieo_final')
            ->orderByDesc('respondido_em')
            ->get();

        // Agrupamento por orientador
        $porOrientador = $avaliacoes->groupBy('docente_programa_vinculo_id')
            ->map(function ($grupo) {
                $docente = $grupo->first()->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—';
                return [
                    'nome'       => $docente,
                    'total'      => $grupo->count(),
                    'ieo_medio'  => round($grupo->avg('ieo_final'), 1),
                    'cat1_medio' => round($grupo->avg('nota_cat1'), 1),
                    'cat2_medio' => round($grupo->avg('nota_cat2'), 1),
                    'cat3_medio' => round($grupo->avg('nota_cat3'), 1),
                    'cat4_medio' => round($grupo->avg('nota_cat4'), 1),
                    'cat5_medio' => round($grupo->avg('nota_cat5'), 1),
                    'cat6_medio' => round($grupo->avg('nota_cat6'), 1),
                ];
            })
            ->sortByDesc('ieo_medio')
            ->values();

        return view('orientacao-avaliacao.index', compact('porOrientador', 'avaliacoes'));
    }
}
