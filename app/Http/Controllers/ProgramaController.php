<?php

namespace App\Http\Controllers;

use App\Models\Programa;
use Illuminate\Http\Request;

class ProgramaController extends Controller
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

        $programas = Programa::orderBy('nome')->get();

        return view('programas.index', compact('programas'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        return view('programas.create');
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'codigo_capes' => ['nullable', 'string', 'max:50'],
            'area_avaliacao' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'descricao' => ['nullable', 'string'],
        ]);

        $dados['oferta_mestrado'] = $request->boolean('oferta_mestrado');
        $dados['oferta_doutorado'] = $request->boolean('oferta_doutorado');
        $dados['ativo'] = true;

        Programa::create($dados);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Programa cadastrado com sucesso.');
    }

    public function edit(Programa $programa)
    {
        $this->autorizarSecretaria();

        return view('programas.edit', compact('programa'));
    }

    public function update(Request $request, Programa $programa)
    {
        $this->autorizarSecretaria();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'codigo_capes' => ['nullable', 'string', 'max:50'],
            'area_avaliacao' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'descricao' => ['nullable', 'string'],
        ]);

        $dados['oferta_mestrado'] = $request->boolean('oferta_mestrado');
        $dados['oferta_doutorado'] = $request->boolean('oferta_doutorado');

        $programa->update($dados);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Programa atualizado com sucesso.');
    }

    public function destroy(Programa $programa)
    {
        $this->autorizarSecretaria();

        if ($programa->areasConcentracao()->exists()) {
            return redirect()
                ->route('programas.index')
                ->with('status', 'Não é possível remover programa com áreas de concentração cadastradas.');
        }

        $programa->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Programa removido com sucesso.');
    }
}