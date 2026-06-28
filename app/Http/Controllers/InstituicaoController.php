<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use Illuminate\Http\Request;

class InstituicaoController extends Controller
{
    public function edit()
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }

        $instituicao = Instituicao::first();

        return view('instituicoes.edit', compact('instituicao'));
    }

    public function update(Request $request)
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:20'],
            'site' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
        ]);

        $instituicao = Instituicao::first();

        if ($instituicao) {
            $instituicao->update($dados + [
                'logo_path' => $instituicao->logo_path ?: 'images/logoUneb.png',
            ]);
        } else {
            Instituicao::create($dados + [
                'logo_path' => 'images/logoUneb.png',
                'ativo' => true,
            ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Instituição salva ou atualizada com sucesso.');
    }
}
