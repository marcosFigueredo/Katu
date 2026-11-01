<?php

namespace App\Http\Controllers;

use App\Models\PeriodoQualidade;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A Secretaria cadastra os quadriênios de avaliação; a CPA só ajusta faixas e pesos de cada um. */
class QuadrienioController extends Controller
{
    public function index()
    {
        $programa = $this->secretaria();
        $quadrienios = PeriodoQualidade::where('programa_id', $programa->id)->orderByDesc('inicio_em')->get();

        return view('quadrienios.index', compact('quadrienios'));
    }

    public function store(Request $request)
    {
        $programa = $this->secretaria();
        $dados = $this->validar($request, $programa->id);

        PeriodoQualidade::create($dados + ['programa_id' => $programa->id, 'criado_por' => auth()->id()]);

        return redirect()->route('quadrienios.index')->with('success', 'Quadriênio cadastrado.');
    }

    public function update(Request $request, PeriodoQualidade $periodo)
    {
        $programa = $this->secretaria();
        $this->garantirProgramaDaSecretaria($periodo->programa_id);

        $periodo->update($this->validar($request, $programa->id, $periodo->id));

        return redirect()->route('quadrienios.index')->with('success', 'Quadriênio atualizado.');
    }

    private function validar(Request $request, int $programaId, ?int $ignorar = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'inicio_em' => ['required', 'date'],
            'fim_em' => ['required', 'date', 'after:inicio_em'],
        ], ['fim_em.after' => 'O fim do quadriênio deve ser posterior ao início.']);

        $sobreposto = PeriodoQualidade::where('programa_id', $programaId)
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))
            ->whereDate('inicio_em', '<=', $dados['fim_em'])
            ->whereDate('fim_em', '>=', $dados['inicio_em'])
            ->exists();
        if ($sobreposto) {
            throw ValidationException::withMessages(['inicio_em' => 'Já existe um quadriênio nesse intervalo.']);
        }

        return $dados;
    }

    private function secretaria(): \App\Models\Programa
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);

        return $this->programaDaSecretaria();
    }
}
