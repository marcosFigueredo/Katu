<?php

namespace App\Http\Controllers;

use App\Models\AvaliacaoOrientando;
use App\Models\Banca;
use App\Models\DimensaoQualidade;
use App\Models\DocenteProgramaVinculo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** O orientador (presidente) avalia o orientando na defesa: envio único, privado à CPA, condição para finalizar. */
class AvaliacaoOrientandoController extends Controller
{
    public function form(Banca $banca)
    {
        $this->autorizar($banca);

        if (AvaliacaoOrientando::where('banca_id', $banca->id)->exists()) {
            return redirect()->route('avaliacoes.banca', $banca);
        }

        $banca->load('trabalhoAcademico.discenteVinculo.discenteProfile.user');

        return view('avaliacoes.avaliacao-orientando', compact('banca'));
    }

    public function store(Request $request, Banca $banca)
    {
        $vinculo = $this->autorizar($banca);

        if (AvaliacaoOrientando::where('banca_id', $banca->id)->exists()) {
            throw ValidationException::withMessages(['avaliacao_orientando' => 'A avaliação do orientando já foi concluída.']);
        }

        $regras = ['observacoes' => ['nullable', 'string', 'max:5000']];
        $mensagens = [];
        foreach (array_keys(AvaliacaoOrientando::ELEMENTOS) as $elemento) {
            $regras["notas.{$elemento}"] = ['required', 'numeric', 'min:0', 'max:10'];
            $mensagens["notas.{$elemento}.required"] = 'Dê nota a todos os elementos.';
        }
        $dados = $request->validate($regras, $mensagens);
        $autonomia = DimensaoQualidade::doCodigo('autonomia')?->id;

        try {
            DB::transaction(function () use ($banca, $vinculo, $dados, $autonomia) {
                $avaliacao = AvaliacaoOrientando::create([
                    'banca_id' => $banca->id,
                    'docente_programa_vinculo_id' => $vinculo->id,
                    'observacoes' => $dados['observacoes'] ?? null,
                    'enviada_em' => now(),
                ]);
                foreach (array_keys(AvaliacaoOrientando::ELEMENTOS) as $elemento) {
                    $avaliacao->notas()->create([
                        'elemento' => $elemento,
                        'nota' => $dados['notas'][$elemento],
                        'dimensao_qualidade_id' => $autonomia,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['avaliacao_orientando' => 'A avaliação do orientando já foi concluída.']);
        }

        return redirect()->route('avaliacoes.banca', $banca)->with('success', 'Avaliação do orientando concluída.');
    }

    private function autorizar(Banca $banca): DocenteProgramaVinculo
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'docente', 403);

        $vinculo = $user->docenteProfile?->vinculosPrograma()->first();
        $ehOrientador = $vinculo && $banca->trabalhoAcademico?->orientacoes()
            ->where('tipo', 'orientador')
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->exists();
        abort_unless($ehOrientador, 403, 'Apenas o orientador (presidente da banca) avalia o orientando.');
        abort_unless($banca->tipo === 'defesa', 404, 'A avaliação do orientando é feita só na banca de defesa.');
        abort_unless(
            $banca->homologacao_status === 'homologada' && ! in_array($banca->status, ['finalizada', 'cancelada'], true),
            403,
            'A avaliação do orientando fica disponível com a banca homologada e ainda não finalizada.'
        );

        return $vinculo;
    }
}
