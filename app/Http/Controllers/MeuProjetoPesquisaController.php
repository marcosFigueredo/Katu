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
        $docente = $this->docentePermanente();
        $projetos = $docente->projetos()->withCount(['vinculos as orientandos_count' => fn ($q) => $q->where('ativo', true)])
            ->orderByDesc('id')->get();

        return view('docente.projeto-pesquisa', [
            'ativo' => $projetos->firstWhere('situacao', 'ativo'),
            'concluidos' => $projetos->where('situacao', 'concluido'),
        ]);
    }

    public function store(Request $request)
    {
        $docente = $this->docentePermanente();
        $this->service->cadastrar($docente, $this->validar($request), auth()->user());

        return redirect()->route('meu-projeto.edit')->with('success', 'Projeto de pesquisa cadastrado. Seus orientandos foram vinculados a ele.');
    }

    public function update(Request $request, ProjetoPesquisa $projeto)
    {
        $docente = $this->docentePermanente();
        abort_unless($projeto->docente_programa_vinculo_id === $docente->id, 403);

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

    private function docentePermanente(): DocenteProgramaVinculo
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'docente', 403);

        $vinculo = $user->docenteProfile?->vinculosPrograma()->where('ativo', true)->get()
            ->first(fn (DocenteProgramaVinculo $v) => $v->ehPermanente());
        abort_unless($vinculo, 403, 'Só docente permanente cadastra projeto de pesquisa.');

        return $vinculo;
    }
}
