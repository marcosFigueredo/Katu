<?php

namespace App\Http\Controllers;

use App\Models\AreaConcentracao;
use App\Models\Programa;
use Illuminate\Http\Request;

class AreaConcentracaoController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function index(Programa $programa)
    {
        $this->autorizarSecretaria();

        $programa->load(['areasConcentracao' => function ($query) {
            $query->orderBy('nome');
        }]);

        return view('areas-concentracao.index', compact('programa'));
    }

    public function create(Programa $programa)
    {
        $this->autorizarSecretaria();

        return view('areas-concentracao.create', compact('programa'));
    }

    public function store(Request $request, Programa $programa)
    {
        $this->autorizarSecretaria();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string'],
        ]);

        $programa->areasConcentracao()->create($dados + [
            'ativo' => true,
        ]);

        return redirect()
            ->route('programas.areas.index', $programa)
            ->with('status', 'Área de concentração cadastrada com sucesso.');
    }

    public function edit(AreaConcentracao $areaConcentracao)
    {
        $this->autorizarSecretaria();

        $areaConcentracao->load('programa');

        return view('areas-concentracao.edit', compact('areaConcentracao'));
    }

    public function update(Request $request, AreaConcentracao $areaConcentracao)
    {
        $this->autorizarSecretaria();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string'],
        ]);

        $areaConcentracao->update($dados);

        return redirect()
            ->route('programas.areas.index', $areaConcentracao->programa)
            ->with('status', 'Área de concentração atualizada com sucesso.');
    }

    public function destroy(AreaConcentracao $areaConcentracao)
    {
        $this->autorizarSecretaria();

        if ($areaConcentracao->linhasPesquisa()->exists()) {
            return redirect()
                ->route('programas.areas.index', $areaConcentracao->programa)
                ->with('status', 'Não é possível remover área com linhas de pesquisa cadastradas.');
        }

        $programa = $areaConcentracao->programa;

        $areaConcentracao->delete();

        return redirect()
            ->route('programas.areas.index', $programa)
            ->with('status', 'Área de concentração removida com sucesso.');
    }
}