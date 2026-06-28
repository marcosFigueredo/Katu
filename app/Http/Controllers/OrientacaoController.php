<?php

namespace App\Http\Controllers;

use App\Models\DocenteProgramaVinculo;
use App\Models\Orientacao;
use App\Models\TrabalhoAcademico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrientacaoController extends Controller
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

        $trabalhos = TrabalhoAcademico::with([
            'discenteVinculo.discenteProfile.user',
            'discenteVinculo.linhaPesquisa',
            'programa',
            'orientacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tipo')->orderBy('ordem'),
            'orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return view('orientacoes.index', compact('trabalhos'));
    }

    public function edit(TrabalhoAcademico $trabalho)
    {
        $this->autorizarSecretaria();

        $trabalho->load([
            'discenteVinculo.discenteProfile.user',
            'discenteVinculo.linhaPesquisa',
            'programa',
            'orientacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tipo')->orderBy('ordem'),
            'orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $docentes = DocenteProgramaVinculo::where('programa_id', $trabalho->programa_id)
            ->where('pode_orientar', true)
            ->where('ativo', true)
            ->with('docenteProfile.user')
            ->get()
            ->sortBy('docenteProfile.user.name');

        $orientadorAtual    = $trabalho->orientacoes->firstWhere('tipo', 'orientador');
        $coorientadoresAtuais = $trabalho->orientacoes->where('tipo', 'coorientador')
            ->pluck('docente_programa_vinculo_id')
            ->toArray();

        return view('orientacoes.edit', compact(
            'trabalho',
            'docentes',
            'orientadorAtual',
            'coorientadoresAtuais',
        ));
    }

    public function update(Request $request, TrabalhoAcademico $trabalho)
    {
        $this->autorizarSecretaria();

        $request->validate([
            'orientador_vinculo_id'   => ['nullable', 'exists:docente_programa_vinculos,id'],
            'coorientadores'          => ['nullable', 'array'],
            'coorientadores.*'        => ['exists:docente_programa_vinculos,id'],
        ], [
            'orientador_vinculo_id.exists' => 'Orientador inválido.',
            'coorientadores.*.exists'      => 'Um ou mais coorientadores inválidos.',
        ]);

        DB::transaction(function () use ($request, $trabalho) {
            $trabalho->orientacoes()->delete();

            if ($request->filled('orientador_vinculo_id')) {
                Orientacao::create([
                    'trabalho_academico_id'       => $trabalho->id,
                    'docente_programa_vinculo_id'  => $request->orientador_vinculo_id,
                    'tipo'                        => 'orientador',
                    'ordem'                       => 1,
                    'ativo'                       => true,
                ]);
            }

            $coorientadores = collect($request->coorientadores ?? [])
                ->reject(fn ($id) => $id == $request->orientador_vinculo_id)
                ->values();

            foreach ($coorientadores as $ordem => $vinculoId) {
                Orientacao::create([
                    'trabalho_academico_id'       => $trabalho->id,
                    'docente_programa_vinculo_id'  => $vinculoId,
                    'tipo'                        => 'coorientador',
                    'ordem'                       => $ordem + 1,
                    'ativo'                       => true,
                ]);
            }
        });

        return redirect()
            ->route('orientacoes.index')
            ->with('success', 'Orientação atualizada com sucesso.');
    }
}
