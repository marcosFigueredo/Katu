<?php

namespace App\Http\Controllers;

use App\Models\Barema;
use App\Models\LinhaPesquisa;
use App\Models\MabacAnalise;
use App\Services\MabacService;
use Illuminate\Http\Request;

class MabacController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function index()
    {
        $this->autorizarSecretaria();

        $programa = $this->programaDaSecretaria();

        $analises = MabacAnalise::with(['barema', 'linhaPesquisa'])
            ->whereHas('barema', fn ($q) => $q->where('programa_id', $programa->id))
            ->withCount('resultados')
            ->orderByDesc('created_at')
            ->get();

        return view('mabac.index', compact('analises'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        $programa = $this->programaDaSecretaria();
        $baremas = Barema::where('programa_id', $programa->id)->where('ativo', true)->orderBy('nome')->get();
        $linhas  = LinhaPesquisa::whereHas('areaConcentracao', fn ($q) => $q->where('programa_id', $programa->id))
            ->orderBy('nome')->get();

        return view('mabac.create', compact('baremas', 'linhas'));
    }

    public function store(Request $request, MabacService $mabacService)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'nome'             => ['required', 'string', 'max:200'],
            'barema_id'        => ['required', \Illuminate\Validation\Rule::exists('baremas', 'id')->where('programa_id', $this->programaDaSecretaria()->id)],
            'tipo_banca'       => ['required', 'in:qualificacao,defesa,ambos'],
            'linha_pesquisa_id'=> ['nullable', 'exists:linhas_pesquisa,id'],
            'periodo_inicio'   => ['required', 'date'],
            'periodo_fim'      => ['required', 'date', 'after_or_equal:periodo_inicio'],
        ]);

        $analise = MabacAnalise::create([
            ...$data,
            'criado_por' => auth()->user()->name,
        ]);

        $mabacService->analisar($analise);

        return redirect()->route('mabac.show', $analise)
            ->with('success', 'Análise MABAC gerada com sucesso.');
    }

    public function show(MabacAnalise $mabac)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($mabac->barema?->programa_id);

        $mabac->loadMissing([
            'barema',
            'linhaPesquisa',
            'resultados.banca.trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'resultados.banca.trabalhoAcademico.linhaPesquisa',
        ]);

        return view('mabac.show', compact('mabac'));
    }

    public function destroy(MabacAnalise $mabac)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($mabac->barema?->programa_id);

        $mabac->delete();

        return redirect()->route('mabac.index')
            ->with('success', 'Análise MABAC excluída.');
    }
}
