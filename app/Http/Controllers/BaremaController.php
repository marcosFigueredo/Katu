<?php

namespace App\Http\Controllers;

use App\Models\Barema;
use App\Models\Programa;
use Illuminate\Http\Request;

class BaremaController extends Controller
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

        $baremas = Barema::with('secoes')
            ->when($programa, fn ($q) => $q->where('programa_id', $programa->id))
            ->orderBy('tipo')
            ->orderBy('nivel')
            ->orderByDesc('ativo')
            ->orderByDesc('versao')
            ->get();

        return view('baremas.index', compact('baremas', 'programa'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        $programa = $this->programaAtual();

        return view('baremas.create', compact('programa'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();

        $programa = $this->programaAtual();

        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'tipo'      => ['required', 'in:qualificacao,defesa,qualificacao_confidencial,defesa_confidencial'],
            'nivel'     => ['required', 'in:mestrado,doutorado,ambos'],
            'versao'    => ['required', 'string', 'max:20'],
            'descricao' => ['nullable', 'string'],
        ]);

        $data['programa_id'] = $programa?->id;
        $data['ativo']       = false;

        $barema = Barema::create($data);

        return redirect()
            ->route('baremas.edit', $barema)
            ->with('success', 'Barema criado. Adicione as seções e os itens de avaliação.');
    }

    public function edit(Barema $barema)
    {
        $this->autorizarSecretaria();

        $barema->load(['secoes.itens']);

        return view('baremas.edit', compact('barema'));
    }

    public function update(Request $request, Barema $barema)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'tipo'      => ['required', 'in:qualificacao,defesa,qualificacao_confidencial,defesa_confidencial'],
            'nivel'     => ['required', 'in:mestrado,doutorado,ambos'],
            'versao'    => ['required', 'string', 'max:20'],
            'descricao' => ['nullable', 'string'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        $data['ativo'] = $request->boolean('ativo');

        $barema->update($data);

        return redirect()
            ->route('baremas.edit', $barema)
            ->with('success', 'Barema atualizado com sucesso.');
    }

    public function destroy(Barema $barema)
    {
        $this->autorizarSecretaria();

        $barema->delete();

        return redirect()
            ->route('baremas.index')
            ->with('success', 'Barema excluído.');
    }
}
