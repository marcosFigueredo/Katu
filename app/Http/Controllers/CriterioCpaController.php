<?php

namespace App\Http\Controllers;

use App\Models\CriterioCpa;
use App\Models\Programa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CriterioCpaController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    private function programaAtual(): ?Programa
    {
        return auth()->user()
            ->programaUsuarios()
            ->where('ativo', true)
            ->with('programa')
            ->first()
            ?->programa;
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->programaAtual();
        $criterios = CriterioCpa::query()
            ->when($programa, fn ($query) => $query->where('programa_id', $programa->id), fn ($query) => $query->whereKey(-1))
            ->orderBy('etapa')
            ->orderBy('ordem')
            ->orderBy('codigo')
            ->get();

        return view('criterios-cpa.index', compact('programa', 'criterios'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        return view('criterios-cpa.form', [
            'programa' => $this->programaAtual(),
            'criterio' => new CriterioCpa(['ativo' => true, 'obrigatorio' => true, 'ordem' => 1, 'versao' => '1.0']),
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->programaAtual();
        abort_unless($programa, 422, 'A Secretaria não possui programa ativo vinculado.');

        $data = $this->validar($request);
        $data['programa_id'] = $programa->id;
        $data['criado_por'] = auth()->id();
        $data['obrigatorio'] = $request->boolean('obrigatorio');
        $data['ativo'] = $request->boolean('ativo', true);
        CriterioCpa::create($data);

        return redirect()->route('criterios-cpa.index')->with('status', 'Critério da CPA criado.');
    }

    public function edit(CriterioCpa $criterioCpa)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($criterioCpa);

        return view('criterios-cpa.form', [
            'programa' => $this->programaAtual(),
            'criterio' => $criterioCpa,
        ]);
    }

    public function update(Request $request, CriterioCpa $criterioCpa)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($criterioCpa);

        $data = $this->validar($request, $criterioCpa);
        $data['obrigatorio'] = $request->boolean('obrigatorio');
        $data['ativo'] = $request->boolean('ativo');
        $criterioCpa->update($data);

        return redirect()->route('criterios-cpa.index')->with('status', 'Critério da CPA atualizado.');
    }

    public function destroy(CriterioCpa $criterioCpa)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($criterioCpa);
        $criterioCpa->update(['ativo' => false]);

        return redirect()->route('criterios-cpa.index')->with('status', 'Critério inativado; o histórico foi preservado.');
    }

    private function validar(Request $request, ?CriterioCpa $criterio = null): array
    {
        return $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('criterios_cpa', 'codigo')
                    ->where('programa_id', $this->programaAtual()?->id)
                    ->where('etapa', $request->input('etapa'))
                    ->where('versao', $request->input('versao'))
                    ->ignore($criterio?->id),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'etapa' => ['required', 'in:qualificacao,defesa'],
            'tipo_evidencia' => ['required', 'in:arquivo,texto,link,doi,numero,declaracao'],
            'nivel' => ['nullable', 'in:mestrado,doutorado'],
            'ordem' => ['required', 'integer', 'min:1', 'max:9999'],
            'versao' => ['required', 'string', 'max:20'],
            'obrigatorio' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }

    private function garantirPrograma(CriterioCpa $criterio): void
    {
        abort_unless($criterio->programa_id === $this->programaAtual()?->id, 404);
    }
}
