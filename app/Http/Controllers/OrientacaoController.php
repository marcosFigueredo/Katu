<?php

namespace App\Http\Controllers;

use App\Models\DocenteProgramaVinculo;
use App\Models\Orientacao;
use App\Models\TrabalhoAcademico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        ->where('programa_id', $this->programaDaSecretaria()->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return view('orientacoes.index', compact('trabalhos'));
    }

    public function edit(TrabalhoAcademico $trabalho)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($trabalho->programa_id);

        $trabalho->load([
            'discenteVinculo.discenteProfile.user',
            'discenteVinculo.linhaPesquisa',
            'programa',
            'orientacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tipo')->orderBy('ordem'),
            'orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $vinculosAtivos = DocenteProgramaVinculo::where('programa_id', $trabalho->programa_id)
            ->where('ativo', true)
            ->with('docenteProfile.user')
            ->get()
            ->sortBy('docenteProfile.user.name')
            ->values();

        $opcao = fn (DocenteProgramaVinculo $v) => [
            'valor' => $v->id,
            'rotulo' => $v->docenteProfile?->user?->name ?? '?',
            'detalhe' => trim(($v->tipo_vinculo === 'externo' ? 'externo' : 'interno')
                . ($v->docenteProfile?->instituicao_sigla ? ', ' . $v->docenteProfile->instituicao_sigla : '')),
        ];

        $opcoesOrientador = $vinculosAtivos->where('pode_orientar', true)->map($opcao)->values()->all();
        $opcoesCoorientador = $vinculosAtivos->map($opcao)->all();
        $maxCoorientadores = (int) ($trabalho->programa?->max_coorientadores ?? 1);

        $orientadorAtual = $trabalho->orientacoes->firstWhere('tipo', 'orientador');
        $coorientadoresAtuais = $trabalho->orientacoes->where('tipo', 'coorientador')
            ->sortBy('ordem')
            ->pluck('docente_programa_vinculo_id')
            ->values()
            ->all();

        return view('orientacoes.edit', compact(
            'trabalho',
            'opcoesOrientador',
            'opcoesCoorientador',
            'maxCoorientadores',
            'orientadorAtual',
            'coorientadoresAtuais',
        ));
    }

    public function update(Request $request, TrabalhoAcademico $trabalho)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($trabalho->programa_id);

        $request->merge([
            'coorientadores' => collect($request->input('coorientadores', []))->filter(fn ($id) => filled($id))->values()->all(),
        ]);

        $maxCoorientadores = (int) ($trabalho->programa?->max_coorientadores ?? 1);

        try {
            $dados = $request->validate([
            'orientador_vinculo_id' => [
                'nullable',
                Rule::exists('docente_programa_vinculos', 'id')->where(fn ($q) => $q
                    ->where('programa_id', $trabalho->programa_id)
                    ->where('pode_orientar', true)
                    ->where('ativo', true)),
            ],
            'coorientadores' => ['array', 'max:' . $maxCoorientadores],
            'coorientadores.*' => [
                'integer',
                'distinct',
                'different:orientador_vinculo_id',
                Rule::exists('docente_programa_vinculos', 'id')->where(fn ($q) => $q
                    ->where('programa_id', $trabalho->programa_id)
                    ->where('ativo', true)),
            ],
        ], [
            'orientador_vinculo_id.exists' => 'O orientador principal deve ser docente ativo do programa com permissão de orientação.',
            'coorientadores.max' => "O programa permite no máximo {$maxCoorientadores} coorientador(es) por discente.",
            'coorientadores.*.distinct' => 'O mesmo docente foi escolhido mais de uma vez como coorientador.',
            'coorientadores.*.different' => 'O orientador principal não pode ser também coorientador.',
            'coorientadores.*.exists' => 'Selecione coorientadores ativos vinculados ao programa do discente.',
        ]);
        } catch (ValidationException $e) {
            // Agrupa os erros de cada campo coorientadores.N em "coorientadores".
            $erros = collect($e->errors())
                ->mapToGroups(fn ($mensagens, $campo) => [
                    str_starts_with($campo, 'coorientadores.') ? 'coorientadores' : $campo => $mensagens,
                ])
                ->map(fn ($grupo) => $grupo->flatten()->unique()->values()->all());

            throw ValidationException::withMessages($erros->all());
        }

        DB::transaction(function () use ($dados, $trabalho) {
            $trabalho->orientacoes()->delete();

            if (! empty($dados['orientador_vinculo_id'])) {
                Orientacao::create([
                    'trabalho_academico_id'       => $trabalho->id,
                    'discente_programa_vinculo_id' => $trabalho->discente_programa_vinculo_id,
                    'docente_programa_vinculo_id'  => $dados['orientador_vinculo_id'],
                    'tipo'                        => 'orientador',
                    'ordem'                       => 1,
                    'ativo'                       => true,
                ]);
            }

            $coorientadores = collect($dados['coorientadores'] ?? [])->values();

            foreach ($coorientadores as $ordem => $vinculoId) {
                Orientacao::create([
                    'trabalho_academico_id'       => $trabalho->id,
                    'discente_programa_vinculo_id' => $trabalho->discente_programa_vinculo_id,
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
