<?php

namespace App\Http\Controllers;

use App\Models\Banca;
use App\Models\Barema;
use App\Models\EspelhoDiscente;
use App\Services\BaremasDaBancaService;
use App\Services\PedidoHomologacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Espelho do discente na defesa: notas de 0 a 10 às perguntas do barema padrão da banca; envio único, privado à CPA. */
class EspelhoDiscenteController extends Controller
{
    public function __construct(
        private readonly BaremasDaBancaService $baremas,
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {}

    public function form()
    {
        [$banca, $barema] = $this->autorizar();

        if (EspelhoDiscente::where('banca_id', $banca->id)->exists()) {
            return redirect()->route('meu-trabalho.index')->with('success', 'Seu espelho da avaliação já foi enviado.');
        }

        $barema->load(['secoes' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')
            ->with(['itens' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')])]);

        return view('meu-trabalho.espelho', compact('banca', 'barema'));
    }

    public function store(Request $request)
    {
        [$banca, $barema] = $this->autorizar();

        if (EspelhoDiscente::where('banca_id', $banca->id)->exists()) {
            throw ValidationException::withMessages(['espelho' => 'O espelho da avaliação já foi enviado.']);
        }

        $itens = $barema->secoes()->where('ativo', true)->with(['itens' => fn ($q) => $q->where('ativo', true)])->get()
            ->flatMap->itens;

        $regras = [];
        $mensagens = [];
        foreach ($itens as $item) {
            $regras["notas.{$item->id}"] = ['required', 'numeric', 'min:0', 'max:'.(float) $item->nota_maxima];
            $mensagens["notas.{$item->id}.required"] = 'Responda todas as perguntas.';
        }
        $dados = $request->validate($regras, $mensagens);

        $obtido = 0.0;
        $maximo = 0.0;
        // Mesma regra de BancaAvaliacao::notaCalculada: Σ(nota × peso) / Σ(nota_max × peso) × 10.
        foreach ($itens as $item) {
            $peso = (float) $item->peso;
            $obtido += (float) $dados['notas'][$item->id] * $peso;
            $maximo += (float) $item->nota_maxima * $peso;
        }

        DB::transaction(function () use ($banca, $barema, $itens, $dados, $obtido, $maximo) {
            $espelho = EspelhoDiscente::create([
                'banca_id' => $banca->id,
                'barema_id' => $barema->id,
                'nota_final' => $maximo > 0 ? round($obtido / $maximo * 10, 2) : 0,
                'enviada_em' => now(),
            ]);

            foreach ($itens as $item) {
                $espelho->notas()->create([
                    'barema_item_id' => $item->id,
                    'nota' => $dados['notas'][$item->id],
                    'dimensao_qualidade_id' => $item->dimensao_qualidade_id,
                ]);
            }
        });

        return redirect()->route('meu-trabalho.index')->with('success', 'Espelho da avaliação enviado.');
    }

    /** @return array{0: Banca, 1: Barema} */
    private function autorizar(): array
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'discente', 403);

        $trabalho = $user->discenteProfile?->vinculosPrograma()->with('trabalhoAcademico')->get()
            ->pluck('trabalhoAcademico')->filter()->first();
        $banca = $trabalho ? $this->pedidoHomologacao->bancaDoPedido($trabalho) : null;
        abort_unless($banca && $banca->tipo === 'defesa', 404, 'O espelho da avaliação é respondido no pedido da banca de defesa.');

        $barema = $this->baremas->baremaAtivo($banca, 'defesa');
        abort_unless($barema, 404, 'Barema de Defesa não cadastrado.');

        return [$banca, $barema];
    }
}
