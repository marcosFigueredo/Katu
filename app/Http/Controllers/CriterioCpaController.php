<?php

namespace App\Http\Controllers;

use App\Models\CriterioCpa;
use App\Models\Programa;
use Illuminate\Http\Request;

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
            ->orderByDesc('ativo')
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        return view('criterios-cpa.index', compact('programa', 'criterios'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        return view('criterios-cpa.form', [
            'programa' => $this->programaAtual(),
            'criterio' => new CriterioCpa(['ativo' => true, 'ordem' => 1]),
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
        CriterioCpa::create($data);

        return redirect()->route('criterios-cpa.index')->with('status', 'Pergunta da CPA cadastrada.');
    }

    public function edit(CriterioCpa $criterioCpa)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($criterioCpa);
        abort_unless($criterioCpa->ativo, 404);

        return view('criterios-cpa.form', [
            'programa' => $this->programaAtual(),
            'criterio' => $criterioCpa,
        ]);
    }

    public function update(Request $request, CriterioCpa $criterioCpa)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($criterioCpa);
        abort_unless($criterioCpa->ativo, 404);

        $criterioCpa->update($this->validar($request));

        return redirect()->route('criterios-cpa.index')->with('status', 'Pergunta da CPA atualizada.');
    }

    public function inativar(CriterioCpa $criterioCpa)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($criterioCpa);

        if ($criterioCpa->ativo) {
            $criterioCpa->inativar();
        }

        return redirect()->route('criterios-cpa.index')->with('status', 'Pergunta inativada; o histórico foi preservado.');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'pergunta' => ['required', 'string', 'max:1000'],
            'etapa' => ['nullable', 'in:qualificacao,defesa'],
            'nivel' => ['nullable', 'in:mestrado,doutorado'],
            'ordem' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);
        $data['etapa'] = $data['etapa'] ?: null;
        $data['nivel'] = $data['nivel'] ?: null;

        return $data;
    }

    private function garantirPrograma(CriterioCpa $criterio): void
    {
        abort_unless($criterio->programa_id === $this->programaAtual()?->id, 404);
    }
}
