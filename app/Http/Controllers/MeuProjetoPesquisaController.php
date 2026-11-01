<?php

namespace App\Http\Controllers;

use App\Models\DocenteProgramaVinculo;
use App\Models\ProjetoPesquisa;
use App\Services\ProjetoPesquisaService;
use Illuminate\Http\Request;

/** O docente permanente cadastra, corrige e troca o próprio projeto de pesquisa. */
class MeuProjetoPesquisaController extends Controller
{
    public function __construct(private readonly ProjetoPesquisaService $service)
    {
    }

    public function edit()
    {
        // Um projeto por programa em que o docente é permanente.
        $secoes = $this->vinculosPermanentes()->map(function (DocenteProgramaVinculo $vinculo) {
            $projetos = $vinculo->projetos()->withCount(['vinculos as orientandos_count' => fn ($q) => $q->where('ativo', true)])
                ->orderByDesc('id')->get();

            return [
                'vinculo' => $vinculo->loadMissing('programa'),
                'ativo' => $projetos->firstWhere('situacao', 'ativo'),
                'concluidos' => $projetos->where('situacao', 'concluido'),
            ];
        });

        return view('docente.projeto-pesquisa', ['secoes' => $secoes, 'variosProgramas' => $secoes->count() > 1]);
    }

    public function store(Request $request)
    {
        $vinculos = $this->vinculosPermanentes();
        $docente = $request->filled('vinculo_id')
            ? $vinculos->firstWhere('id', (int) $request->input('vinculo_id'))
            : $vinculos->first();
        abort_unless($docente, 403);

        $this->service->cadastrar($docente, $this->validar($request), auth()->user());

        return redirect()->route('meu-projeto.edit')->with('success', 'Projeto de pesquisa cadastrado. Seus orientandos foram vinculados a ele.');
    }

    public function update(Request $request, ProjetoPesquisa $projeto)
    {
        abort_unless($this->vinculosPermanentes()->contains('id', $projeto->docente_programa_vinculo_id), 403);

        $this->service->atualizar($projeto, $this->validar($request));

        return redirect()->route('meu-projeto.edit')->with('success', 'Projeto de pesquisa atualizado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:500'],
            'descricao' => ['required', 'string', 'max:5000'],
            'inicio_em' => ['required', 'date'],
            'fim_em' => ['nullable', 'date', 'after_or_equal:inicio_em'],
        ], [
            'fim_em.after_or_equal' => 'O fim do projeto não pode ser anterior ao início.',
        ]);
    }

    /** Vínculos ativos em que o docente logado é permanente; nenhum → 403. */
    private function vinculosPermanentes(): \Illuminate\Support\Collection
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'docente', 403);

        $vinculos = ($user->docenteProfile?->vinculosPrograma()->where('ativo', true)->get() ?? collect())
            ->filter(fn (DocenteProgramaVinculo $v) => $v->ehPermanente())
            ->values();
        abort_if($vinculos->isEmpty(), 403, 'Só docente permanente cadastra projeto de pesquisa.');

        return $vinculos;
    }
}
