<?php

namespace App\Http\Controllers;

use App\Models\AutoavaliacaoPeriodo;
use App\Models\Programa;
use App\Services\AutoavaliacaoIndicadorService;
use Illuminate\Http\Request;

class AutoavaliacaoController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    private function programa(): Programa
    {
        return $this->programaDaSecretaria();
    }

    public function index()
    {
        $this->autorizarSecretaria();

        $programa = $this->programa();

        $periodos = AutoavaliacaoPeriodo::with('programa')
            ->where('programa_id', $programa->id)
            ->withCount('indicadores')
            ->orderByDesc('ano')
            ->orderByDesc('semestre')
            ->get();

        return view('autoavaliacao.index', compact('periodos', 'programa'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        $programa = $this->programa();

        return view('autoavaliacao.create', compact('programa'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();

        $programa = $this->programa();

        $data = $request->validate([
            'nome'         => ['required', 'string', 'max:200'],
            'ano'          => ['required', 'integer', 'min:2000', 'max:2099'],
            'semestre'     => ['nullable', 'in:1,2'],
            'data_inicio'  => ['required', 'date'],
            'data_fim'     => ['required', 'date', 'after_or_equal:data_inicio'],
        ]);

        $periodo = AutoavaliacaoPeriodo::create([
            ...$data,
            'programa_id' => $programa->id,
            'status'      => 'em_andamento',
        ]);

        return redirect()->route('autoavaliacao.show', $periodo)
            ->with('success', 'Período de autoavaliação criado.');
    }

    public function show(AutoavaliacaoPeriodo $autoavaliacao)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($autoavaliacao->programa_id);

        $autoavaliacao->loadMissing(['programa', 'indicadores']);

        return view('autoavaliacao.show', compact('autoavaliacao'));
    }

    public function calcular(AutoavaliacaoPeriodo $autoavaliacao, AutoavaliacaoIndicadorService $service)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($autoavaliacao->programa_id);

        $service->calcular($autoavaliacao);

        return redirect()->route('autoavaliacao.show', $autoavaliacao)
            ->with('success', 'Indicadores calculados com sucesso.');
    }

    public function destroy(AutoavaliacaoPeriodo $autoavaliacao)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($autoavaliacao->programa_id);

        $autoavaliacao->delete();

        return redirect()->route('autoavaliacao.index')
            ->with('success', 'Período excluído.');
    }
}
