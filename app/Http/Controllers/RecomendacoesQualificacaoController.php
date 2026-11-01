<?php

namespace App\Http\Controllers;

use App\Models\RecomendacaoResposta;
use App\Models\TrabalhoAcademico;
use App\Services\PedidoHomologacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** O estudante responde, na submissão da defesa, cada recomendação da banca de qualificação. */
class RecomendacoesQualificacaoController extends Controller
{
    public function __construct(
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {}

    public function edit()
    {
        $trabalho = $this->trabalho();
        $recomendacoes = $this->pedidoHomologacao->recomendacoesDaQualificacao($trabalho)->load('resposta');
        $travado = $this->pedidoHomologacao->defesaEnviada($trabalho);

        return view('meu-trabalho.recomendacoes', compact('trabalho', 'recomendacoes', 'travado'));
    }

    public function update(Request $request)
    {
        $trabalho = $this->trabalho();
        if ($this->pedidoHomologacao->defesaEnviada($trabalho)) {
            throw ValidationException::withMessages(['travado' => TrabalhoImpactoController::MENSAGEM_TRAVADO]);
        }

        $recomendacoes = $this->pedidoHomologacao->recomendacoesDaQualificacao($trabalho);
        $regras = [];
        $mensagens = [];
        foreach ($recomendacoes as $r) {
            $p = "respostas.{$r->id}";
            $regras["{$p}.situacao"] = ['required', Rule::in(array_keys(RecomendacaoResposta::SITUACOES))];
            // Onde está no texto: atendida ou em parte. Justificativa: em parte ou não atendida.
            $regras["{$p}.onde"] = ["exclude_if:{$p}.situacao,nao_atendida", 'required', 'string', 'max:500'];
            $regras["{$p}.justificativa"] = ["exclude_if:{$p}.situacao,atendida", 'required', 'string', 'max:2000'];
            $mensagens["{$p}.situacao.required"] = 'Responda todas as recomendações.';
            $mensagens["{$p}.onde.required"] = 'Indique onde a recomendação foi atendida no texto.';
            $mensagens["{$p}.justificativa.required"] = 'Justifique a recomendação não atendida ou atendida em parte.';
        }
        $dados = $request->validate($regras, $mensagens);

        DB::transaction(function () use ($recomendacoes, $dados) {
            foreach ($recomendacoes as $r) {
                $resposta = $dados['respostas'][$r->id];
                RecomendacaoResposta::updateOrCreate(['banca_recomendacao_id' => $r->id], [
                    'situacao' => $resposta['situacao'],
                    'onde' => $resposta['situacao'] === 'nao_atendida' ? null : $resposta['onde'],
                    'justificativa' => $resposta['situacao'] === 'atendida' ? null : $resposta['justificativa'],
                ]);
            }
        });

        return redirect()->route('meu-trabalho.recomendacoes.edit')->with('success', 'Respostas às recomendações salvas.');
    }

    private function trabalho(): TrabalhoAcademico
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'discente', 403);

        $trabalho = $user->discenteProfile?->vinculosPrograma()->with('trabalhoAcademico')->get()
            ->pluck('trabalhoAcademico')->filter()->first();
        abort_unless($trabalho, 404);

        return $trabalho;
    }
}
