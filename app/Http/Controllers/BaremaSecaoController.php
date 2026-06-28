<?php

namespace App\Http\Controllers;

use App\Models\Barema;
use App\Models\BaremaSecao;
use Illuminate\Http\Request;

class BaremaSecaoController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function store(Request $request, Barema $barema)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'peso'      => ['required', 'numeric', 'min:0', 'max:999'],
            'ordem'     => ['required', 'integer', 'min:1'],
        ]);

        $data['barema_id'] = $barema->id;
        $data['ativo']     = true;

        BaremaSecao::create($data);

        return redirect()
            ->route('baremas.edit', $barema)
            ->with('success', 'Seção adicionada.');
    }

    public function update(Request $request, BaremaSecao $secao)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'peso'      => ['required', 'numeric', 'min:0', 'max:999'],
            'ordem'     => ['required', 'integer', 'min:1'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        $data['ativo'] = $request->boolean('ativo');

        $secao->update($data);

        return redirect()
            ->route('baremas.edit', $secao->barema_id)
            ->with('success', 'Seção atualizada.');
    }

    public function destroy(BaremaSecao $secao)
    {
        $this->autorizarSecretaria();

        $baremaId = $secao->barema_id;
        $secao->delete();

        return redirect()
            ->route('baremas.edit', $baremaId)
            ->with('success', 'Seção excluída.');
    }
}
