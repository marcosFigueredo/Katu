<?php

namespace App\Http\Controllers;

use App\Models\AreaConcentracao;
use App\Models\LinhaPesquisa;
use Illuminate\Http\Request;

class LinhaPesquisaController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function index(AreaConcentracao $areaConcentracao)
    {
        $this->autorizarSecretaria();

        $areaConcentracao->load([
            'programa',
            'linhasPesquisa' => function ($query) {
                $query->orderBy('nome');
            },
        ]);

        return view('linhas-pesquisa.index', compact('areaConcentracao'));
    }

    public function create(AreaConcentracao $areaConcentracao)
    {
        $this->autorizarSecretaria();

        $areaConcentracao->load('programa');

        return view('linhas-pesquisa.create', compact('areaConcentracao'));
    }

    public function store(Request $request, AreaConcentracao $areaConcentracao)
    {
        $this->autorizarSecretaria();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string'],
            'ods' => ['nullable', 'array'],
            'ods.*' => ['integer', 'between:1,17'],
        ]);

        $dados['ods'] = collect($request->input('ods', []))
            ->map(fn ($ods) => (int) $ods)
            ->values()
            ->all();

        $dados['ativo'] = true;

        $areaConcentracao->linhasPesquisa()->create($dados);

        return redirect()
            ->route('areas.linhas.index', $areaConcentracao)
            ->with('status', 'Linha de pesquisa cadastrada com sucesso.');
    }

    public function edit(LinhaPesquisa $linhaPesquisa)
    {
        $this->autorizarSecretaria();

        $linhaPesquisa->load('areaConcentracao.programa');

        return view('linhas-pesquisa.edit', compact('linhaPesquisa'));
    }

    public function update(Request $request, LinhaPesquisa $linhaPesquisa)
    {
        $this->autorizarSecretaria();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string'],
            'ods' => ['nullable', 'array'],
            'ods.*' => ['integer', 'between:1,17'],
        ]);

        $dados['ods'] = collect($request->input('ods', []))
            ->map(fn ($ods) => (int) $ods)
            ->values()
            ->all();

        $linhaPesquisa->update($dados);

        return redirect()
            ->route('areas.linhas.index', $linhaPesquisa->areaConcentracao)
            ->with('status', 'Linha de pesquisa atualizada com sucesso.');
    }

    public function destroy(LinhaPesquisa $linhaPesquisa)
    {
        $this->autorizarSecretaria();

        $areaConcentracao = $linhaPesquisa->areaConcentracao;

        $linhaPesquisa->delete();

        return redirect()
            ->route('areas.linhas.index', $areaConcentracao)
            ->with('status', 'Linha de pesquisa removida com sucesso.');
    }
}