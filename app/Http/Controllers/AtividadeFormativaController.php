<?php

namespace App\Http\Controllers;

use App\Models\AtividadeFormativa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lista de atividades formativas do programa (Configurações da Secretaria); vale para todos os cursos. */
class AtividadeFormativaController extends Controller
{
    public function index()
    {
        $this->autorizar();
        $programa = $this->programaDaSecretaria();
        AtividadeFormativa::criarPadroes($programa);

        $atividades = AtividadeFormativa::where('programa_id', $programa->id)
            ->orderByDesc('ativo')->orderBy('ordem')->orderBy('id')->get();

        return view('atividades-formativas.index', compact('programa', 'atividades'));
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $programa = $this->programaDaSecretaria();

        AtividadeFormativa::create($this->validar($request) + [
            'programa_id' => $programa->id,
            'ativo' => true,
            'ordem' => (int) AtividadeFormativa::where('programa_id', $programa->id)->max('ordem') + 1,
        ]);

        return redirect()->route('atividades-formativas.index')->with('success', 'Atividade formativa cadastrada.');
    }

    public function update(Request $request, AtividadeFormativa $atividadeFormativa)
    {
        $this->autorizar();
        $this->garantirProgramaDaSecretaria($atividadeFormativa->programa_id);

        $atividadeFormativa->update($this->validar($request));

        return redirect()->route('atividades-formativas.index')->with('success', 'Atividade formativa atualizada.');
    }

    public function inativar(AtividadeFormativa $atividadeFormativa)
    {
        $this->autorizar();
        $this->garantirProgramaDaSecretaria($atividadeFormativa->programa_id);

        $atividadeFormativa->update(['ativo' => ! $atividadeFormativa->ativo]);

        return redirect()->route('atividades-formativas.index')
            ->with('success', $atividadeFormativa->ativo ? 'Atividade reativada.' : 'Atividade inativada: deixa de ser exigida.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'detalhes' => ['nullable', 'string', 'max:2000'],
            'quantidade_exigida' => ['required', 'integer', 'min:1', 'max:50'],
            'etapa' => ['required', Rule::in(array_keys(AtividadeFormativa::ETAPAS))],
            'informada_por' => ['required', Rule::in(array_keys(AtividadeFormativa::INFORMADA_POR))],
        ], [
            'quantidade_exigida.min' => 'A quantidade exigida é de pelo menos 1.',
        ]);
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }
}
