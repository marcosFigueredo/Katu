<?php

namespace App\Http\Controllers;

use App\Models\AutoavaliacaoOrientador;
use App\Models\Banca;
use App\Models\Barema;
use App\Models\DocenteProgramaVinculo;
use App\Services\BaremasDaBancaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Autoavaliação do orientador (presidente) na banca de defesa: envio único, respostas privadas à CPA. */
class AutoavaliacaoOrientadorController extends Controller
{
    public function __construct(private readonly BaremasDaBancaService $baremas)
    {
    }

    public function form(Banca $banca)
    {
        [$vinculo, $barema] = $this->autorizar($banca);

        if ($this->enviada($banca, $vinculo)) {
            return redirect()->route('avaliacoes.banca', $banca);
        }

        $barema->load(['secoes' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')
            ->with(['itens' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')])]);
        $banca->load('trabalhoAcademico.discenteVinculo.discenteProfile.user');

        return view('avaliacoes.autoavaliacao', compact('banca', 'barema'));
    }

    public function store(Request $request, Banca $banca)
    {
        [$vinculo, $barema] = $this->autorizar($banca);

        if ($this->enviada($banca, $vinculo)) {
            throw ValidationException::withMessages(['autoavaliacao' => 'A autoavaliação já foi concluída.']);
        }

        $itens = $barema->secoes()->where('ativo', true)->with(['itens' => fn ($q) => $q->where('ativo', true)])->get()
            ->flatMap(fn ($secao) => $secao->itens->map(fn ($item) => [$item]));

        $regras = ['observacoes' => ['nullable', 'string', 'max:5000']];
        $mensagens = [];
        foreach ($itens as [$item]) {
            $regras["notas.{$item->id}"] = ['required', 'numeric', 'min:0', 'max:'.(float) $item->nota_maxima];
            $mensagens["notas.{$item->id}.required"] = 'Responda todas as afirmações.';
        }
        $dados = $request->validate($regras, $mensagens);

        $obtido = 0.0;
        $maximo = 0.0;
        // Mesma regra de BancaAvaliacao::notaCalculada: Σ(nota × peso) / Σ(nota_max × peso) × 10.
        foreach ($itens as [$item]) {
            $peso = (float) $item->peso;
            $obtido += (float) $dados['notas'][$item->id] * $peso;
            $maximo += (float) $item->nota_maxima * $peso;
        }

        DB::transaction(function () use ($banca, $vinculo, $barema, $itens, $dados, $obtido, $maximo) {
            $autoavaliacao = AutoavaliacaoOrientador::create([
                'banca_id' => $banca->id,
                'docente_programa_vinculo_id' => $vinculo->id,
                'barema_id' => $barema->id,
                'nota_final' => $maximo > 0 ? round($obtido / $maximo * 10, 2) : 0,
                'observacoes' => $dados['observacoes'] ?? null,
                'enviada_em' => now(),
            ]);

            foreach ($itens as [$item]) {
                $autoavaliacao->notas()->create([
                    'barema_item_id' => $item->id,
                    'nota' => $dados['notas'][$item->id],
                    'dimensao_qualidade_id' => $item->dimensao_qualidade_id,
                ]);
            }
        });

        return redirect()->route('avaliacoes.banca', $banca)->with('success', 'Autoavaliação concluída.');
    }

    /** @return array{0: DocenteProgramaVinculo, 1: Barema} */
    private function autorizar(Banca $banca): array
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'docente', 403);

        $vinculo = $user->docenteProfile?->vinculosPrograma()->first();
        $ehOrientador = $vinculo && $banca->trabalhoAcademico?->orientacoes()
            ->where('tipo', 'orientador')
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->exists();

        abort_unless($ehOrientador, 403, 'Apenas o orientador (presidente da banca) faz a autoavaliação.');
        abort_unless($banca->tipo === 'defesa', 403, 'A autoavaliação é feita só na banca de defesa.');
        abort_unless(
            $banca->homologacao_status === 'homologada' && ! in_array($banca->status, ['finalizada', 'cancelada'], true),
            403,
            'A autoavaliação fica disponível com a banca homologada e ainda não finalizada.'
        );

        $barema = $this->baremas->baremaAtivo($banca, 'autoavaliacao_orientador');
        abort_unless($barema, 404, 'Barema de Autoavaliação do orientador não cadastrado.');

        return [$vinculo, $barema];
    }

    private function enviada(Banca $banca, DocenteProgramaVinculo $vinculo): bool
    {
        return AutoavaliacaoOrientador::where('banca_id', $banca->id)
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->exists();
    }
}
