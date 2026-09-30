<?php

namespace App\Http\Controllers;

use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use Illuminate\Http\Request;

class BaremaItemController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome'            => ['required', 'string', 'max:200'],
            'descricao'       => ['nullable', 'string'],
            'nota_maxima'     => ['required', 'numeric', 'min:0', 'max:9999'],
            'peso'            => ['required', 'numeric', 'min:0', 'max:999'],
            'obrigatorio'     => ['nullable', 'boolean'],
            'indicador_capes' => ['nullable', 'string', 'max:100'],
            'ordem'           => ['required', 'integer', 'min:1'],
        ]);
    }

    public function store(Request $request, BaremaSecao $secao)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($secao->barema?->programa_id);

        $data = $this->validar($request);
        $data['barema_secao_id'] = $secao->id;
        $data['obrigatorio']     = $request->boolean('obrigatorio');
        $data['ativo']           = true;

        BaremaItem::create($data);

        return redirect()
            ->route('baremas.edit', $secao->barema_id)
            ->with('success', 'Item adicionado.');
    }

    public function update(Request $request, BaremaItem $item)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($item->secao?->barema?->programa_id);

        $data = $this->validar($request);
        $data['obrigatorio'] = $request->boolean('obrigatorio');
        $data['ativo']       = $request->boolean('ativo');

        $item->update($data);

        return redirect()
            ->route('baremas.edit', $item->secao->barema_id)
            ->with('success', 'Item atualizado.');
    }

    public function destroy(BaremaItem $item)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($item->secao?->barema?->programa_id);

        $baremaId = $item->secao->barema_id;
        $item->delete();

        return redirect()
            ->route('baremas.edit', $baremaId)
            ->with('success', 'Item excluído.');
    }
}
