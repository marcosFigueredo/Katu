<?php

namespace App\Http\Controllers;

use App\Models\DocenteProgramaVinculo;
use App\Models\MembroCpa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A Secretaria designa os membros da CPA do seu programa (mandato e número do ato). */
class MembroCpaController extends Controller
{
    public function index()
    {
        $programa = $this->secretaria();

        $membros = MembroCpa::where('programa_id', $programa->id)->with('user', 'registradoPor')
            ->orderByDesc('inicio_em')->get();
        $docentes = DocenteProgramaVinculo::where('programa_id', $programa->id)->where('ativo', true)
            ->with('docenteProfile.user')->get()
            ->map(fn ($v) => $v->docenteProfile?->user)->filter()->sortBy('name')->values();

        return view('cpa.membros', compact('membros', 'docentes'));
    }

    public function store(Request $request)
    {
        $programa = $this->secretaria();
        $dados = $request->validate([
            'user_id' => ['required', 'integer'],
            'inicio_em' => ['required', 'date'],
            'fim_em' => ['nullable', 'date', 'after_or_equal:inicio_em'],
            'numero_ato' => ['required', 'string', 'max:150'],
        ], [
            'numero_ato.required' => 'Informe o número do ato de designação.',
            'fim_em.after_or_equal' => 'O fim do mandato não pode ser anterior ao início.',
        ]);

        $docenteDoPrograma = User::whereKey($dados['user_id'])->where('categoria', 'docente')
            ->whereHas('docenteProfile.vinculosPrograma', fn ($q) => $q->where('programa_id', $programa->id)->where('ativo', true))
            ->exists();
        if (! $docenteDoPrograma) {
            throw ValidationException::withMessages(['user_id' => 'Selecione um docente ativo do programa.']);
        }

        MembroCpa::create($dados + ['programa_id' => $programa->id, 'registrado_por' => auth()->id()]);

        return redirect()->route('membros-cpa.index')->with('success', 'Membro da CPA designado.');
    }

    /** Registro de acessos dos membros à área da CPA (auditoria). */
    public function acessos()
    {
        $programa = $this->secretaria();
        $acessos = \App\Models\AcessoCpa::where('programa_id', $programa->id)->with('user')->latest('id')->limit(500)->get();

        return view('cpa.acessos', compact('acessos'));
    }

    public function encerrar(MembroCpa $membroCpa)
    {
        $this->secretaria();
        $this->garantirProgramaDaSecretaria($membroCpa->programa_id);

        $membroCpa->update(['fim_em' => today()]);

        return redirect()->route('membros-cpa.index')->with('success', 'Mandato encerrado.');
    }

    private function secretaria(): \App\Models\Programa
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);

        return $this->programaDaSecretaria();
    }
}
