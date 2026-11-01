<?php

namespace App\Http\Controllers;

use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoImpacto;
use App\Services\PedidoHomologacaoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Tipo de impacto da dissertação, informado pelo estudante; exigido no pedido de defesa. */
class TrabalhoImpactoController extends Controller
{
    public const DIMENSOES = [
        'cientifico', 'metodologico', 'tecnologico',
        'social', 'ambiental', 'formativo', 'institucional',
    ];

    public const MENSAGEM_TRAVADO = 'O pedido de defesa já foi enviado à homologação; estes dados não podem mais ser alterados.';

    public function __construct(
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {}

    // GET /meu-trabalho/impacto
    public function edit()
    {
        $trabalho = $this->trabalho();
        $impacto = $trabalho->impacto ?? new TrabalhoImpacto(['trabalho_academico_id' => $trabalho->id]);
        $travado = $this->pedidoHomologacao->defesaEnviada($trabalho);

        return view('trabalho-impacto.edit', compact('trabalho', 'impacto', 'travado'));
    }

    // PUT /meu-trabalho/impacto
    public function update(Request $request)
    {
        $trabalho = $this->trabalho();

        if ($this->pedidoHomologacao->defesaEnviada($trabalho)) {
            throw ValidationException::withMessages(['travado' => self::MENSAGEM_TRAVADO]);
        }

        $rules = [];
        $mensagens = [];
        foreach (self::DIMENSOES as $dim) {
            $rules["impacto_{$dim}_aplicavel"] = ['nullable', 'boolean'];
            // Descrição de tipo não marcado é ignorada (e gravada vazia).
            $rules["impacto_{$dim}_descricao"] = ["exclude_unless:impacto_{$dim}_aplicavel,1", 'required', 'string', 'max:2000'];
            $mensagens["impacto_{$dim}_descricao.required"] = 'Descreva o impacto marcado.';
        }

        $validados = $request->validate($rules, $mensagens);

        $dados = [];
        foreach (self::DIMENSOES as $dim) {
            $aplicavel = $request->boolean("impacto_{$dim}_aplicavel");
            $dados["impacto_{$dim}_aplicavel"] = $aplicavel;
            $dados["impacto_{$dim}_descricao"] = $aplicavel ? $validados["impacto_{$dim}_descricao"] : null;
        }

        TrabalhoImpacto::updateOrCreate(
            ['trabalho_academico_id' => $trabalho->id],
            $dados
        );

        return redirect()->route('trabalho-impacto.edit')
            ->with('success', 'Dimensões de impacto salvas com sucesso.');
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
