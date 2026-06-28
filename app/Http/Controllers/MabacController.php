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

        $analises = MabacAnalise::with(['barema', 'linhaPesquisa'])
            ->withCount('resultados')
            ->orderByDesc('created_at')
            ->get();

        return view('mabac.index', compact('analises'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        $baremas = Barema::where('ativo', true)->orderBy('nome')->get();
        $linhas  = LinhaPesquisa::orderBy('nome')->get();

        return view('mabac.create', compact('baremas', 'linhas'));
    }

    public function store(Request $request, MabacService $mabacService)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'nome'             => ['required', 'string', 'max:200'],
            'barema_id'        => ['required', 'exists:baremas,id'],
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

        $mabac->delete();

        return redirect()->route('mabac.index')
            ->with('success', 'Análise MABAC excluída.');
    }
}
