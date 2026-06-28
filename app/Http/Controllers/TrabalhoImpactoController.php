<?php

namespace App\Http\Controllers;

use App\Models\TrabalhoImpacto;
use Illuminate\Http\Request;

class TrabalhoImpactoController extends Controller
{
    private function resolverTrabalho()
    {
        $user    = auth()->user();
        $vinculo = $user->discenteProfile?->vinculosPrograma?->first();
        return $vinculo?->trabalhoAcademico;
    }

    // GET /meu-trabalho/impacto
    public function edit()
    {
        if (auth()->user()->categoria !== 'discente') {
            abort(403);
        }

        $trabalho = $this->resolverTrabalho();

        if (! $trabalho) {
            return redirect()->route('meu-trabalho.index')
                ->with('error', 'Nenhum trabalho acadêmico encontrado.');
        }

        $impacto = $trabalho->impacto ?? new TrabalhoImpacto(['trabalho_academico_id' => $trabalho->id]);

        return view('trabalho-impacto.edit', compact('trabalho', 'impacto'));
    }

    // PUT /meu-trabalho/impacto
    public function update(Request $request)
    {
        if (auth()->user()->categoria !== 'discente') {
            abort(403);
        }

        $trabalho = $this->resolverTrabalho();

        if (! $trabalho) {
            abort(404);
        }

        $dimensoes = [
            'cientifico', 'metodologico', 'tecnologico',
            'social', 'ambiental', 'formativo', 'institucional',
        ];

        $rules = [];
        foreach ($dimensoes as $dim) {
            $rules["impacto_{$dim}_aplicavel"]   = ['nullable', 'boolean'];
            $rules["impacto_{$dim}_descricao"]   = ['nullable', 'string', 'max:2000'];
        }

        $dados = $request->validate($rules);

        // Checkbox booleanos
        foreach ($dimensoes as $dim) {
            $dados["impacto_{$dim}_aplicavel"] = $request->boolean("impacto_{$dim}_aplicavel");
        }

        TrabalhoImpacto::updateOrCreate(
            ['trabalho_academico_id' => $trabalho->id],
            $dados
        );

        return redirect()->route('trabalho-impacto.edit')
            ->with('success', 'Dimensões de impacto salvas com sucesso.');
    }
}
