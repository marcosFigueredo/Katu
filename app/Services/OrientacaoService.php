<?php

namespace App\Services;

use App\Mail\BancaRevisaoTrocaOrientadorMail;
use App\Models\Banca;
use App\Models\DocenteProgramaVinculo;
use App\Models\Orientacao;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Operações sobre os períodos de orientação de um trabalho. Nada é apagado:
 * encerrar um período = ativo false e fim_em hoje.
 */
class OrientacaoService
{
    public function definirOrientadorInicial(TrabalhoAcademico $trabalho, int $docenteVinculoId, User $secretaria): Orientacao
    {
        return DB::transaction(function () use ($trabalho, $docenteVinculoId, $secretaria) {
            $vigentes = $this->vigentesTravadas($trabalho);

            if ($vigentes->contains('tipo', 'orientador')) {
                $this->erro('orientador_vinculo_id', 'O discente já tem orientador. Use a troca de orientador.');
            }

            $this->exigirOrientadorElegivel($trabalho, $docenteVinculoId);

            $orientacao = $this->criar($trabalho, $docenteVinculoId, 'orientador', 1, $secretaria);
            app(ProjetoPesquisaService::class)->vincular($trabalho, 'orientador');

            return $orientacao;
        });
    }

    public function trocarOrientador(
        TrabalhoAcademico $trabalho,
        int $novoVinculoId,
        string $processoSei,
        string $justificativa,
        User $secretaria,
    ): Orientacao {
        $erros = [];
        if (trim($processoSei) === '') {
            $erros['processo_sei'] = 'Informe o número do processo SEI que validou a troca.';
        }
        if (trim($justificativa) === '') {
            $erros['justificativa'] = 'Informe a justificativa da troca.';
        }
        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        return DB::transaction(function () use ($trabalho, $novoVinculoId, $processoSei, $justificativa, $secretaria) {
            $vigentes = $this->vigentesTravadas($trabalho);
            $atual = $vigentes->firstWhere('tipo', 'orientador');

            if (! $atual) {
                $this->erro('orientador_vinculo_id', 'O discente ainda não tem orientador. Defina o orientador inicial.');
            }

            if ($motivo = $this->trocaBloqueada($trabalho)) {
                $this->erro('orientador_vinculo_id', $motivo);
            }

            if ($atual->docente_programa_vinculo_id === $novoVinculoId) {
                $this->erro('orientador_vinculo_id', 'O novo orientador é o mesmo que o atual.');
            }

            $this->exigirOrientadorElegivel($trabalho, $novoVinculoId);

            $this->encerrar($atual);

            $coorientacao = $vigentes->first(fn (Orientacao $o) => $o->tipo === 'coorientador'
                && $o->docente_programa_vinculo_id === $novoVinculoId);
            if ($coorientacao) {
                $this->encerrar($coorientacao);
            }

            $nova = $this->criar($trabalho, $novoVinculoId, 'orientador', 1, $secretaria, [
                'processo_sei'  => trim($processoSei),
                'justificativa' => trim($justificativa),
            ]);

            $this->devolverQualificacaoAberta($trabalho, trim($processoSei));
            app(ProjetoPesquisaService::class)->vincular($trabalho, 'troca_orientador');

            return $nova;
        });
    }

    /** Motivo que impede a troca de orientador, ou null quando ela é permitida. */
    public function trocaBloqueada(TrabalhoAcademico $trabalho): ?string
    {
        $bloqueia = Banca::where('trabalho_academico_id', $trabalho->id)
            ->where(fn ($q) => $q
                ->where(fn ($b) => $b->where('tipo', 'qualificacao')->whereIn('status', ['em_avaliacao', 'finalizada']))
                ->orWhere(fn ($b) => $b->where('tipo', 'defesa')->where('status', '!=', 'cancelada')))
            ->exists();

        return $bloqueia ? 'A troca de orientador só é possível antes da qualificação.' : null;
    }

    /**
     * A qualificação ainda não iniciada volta para revisão: a composição foi conferida com o orientador anterior.
     * Avaliadores de banca já homologada recebem aviso depois do commit.
     */
    private function devolverQualificacaoAberta(TrabalhoAcademico $trabalho, string $processoSei): void
    {
        $bancas = Banca::where('trabalho_academico_id', $trabalho->id)
            ->where('tipo', 'qualificacao')
            ->where('status', 'cadastrada')
            ->whereIn('homologacao_status', ['proposta', 'aguardando', 'devolvida', 'homologada'])
            ->lockForUpdate()
            ->get();

        $nota = 'Orientador trocado em '.today()->format('d/m/Y')." (SEI {$processoSei}) — revisar a composição e reenviar.";

        foreach ($bancas as $banca) {
            $estavaHomologada = $banca->homologacao_status === 'homologada';
            $observacoes = filled($banca->homologacao_observacoes) ? $nota."\n\n".$banca->homologacao_observacoes : $nota;

            Banca::whereKey($banca->id)->update([
                'homologacao_status'      => 'devolvida',
                'homologacao_observacoes' => $observacoes,
            ]);

            if ($estavaHomologada) {
                $banca->load('avaliadores.docenteProgramaVinculo.docenteProfile.user', 'trabalhoAcademico.discenteVinculo.discenteProfile.user');

                foreach ($banca->avaliadores as $membro) {
                    $avaliador = $membro->docenteProgramaVinculo?->docenteProfile?->user;
                    if ($avaliador?->email) {
                        DB::afterCommit(fn () => Mail::to($avaliador->email)->send(new BancaRevisaoTrocaOrientadorMail($banca, $avaliador)));
                    }
                }
            }
        }
    }

    public function incluirCoorientador(TrabalhoAcademico $trabalho, int $docenteVinculoId, User $secretaria): Orientacao
    {
        return DB::transaction(function () use ($trabalho, $docenteVinculoId, $secretaria) {
            $vigentes = $this->vigentesTravadas($trabalho);
            $coorientacoes = $vigentes->where('tipo', 'coorientador');

            $vinculo = DocenteProgramaVinculo::find($docenteVinculoId);
            if (! $vinculo || ! $vinculo->ativo || $vinculo->programa_id !== $trabalho->programa_id) {
                $this->erro('coorientador_vinculo_id', 'Selecione um docente ativo do programa do discente.');
            }

            if ($vigentes->contains(fn (Orientacao $o) => $o->tipo === 'orientador' && $o->docente_programa_vinculo_id === $docenteVinculoId)) {
                $this->erro('coorientador_vinculo_id', 'O orientador não pode ser também coorientador.');
            }

            if ($coorientacoes->contains('docente_programa_vinculo_id', $docenteVinculoId)) {
                $this->erro('coorientador_vinculo_id', 'Este docente já é coorientador do discente.');
            }

            $maximo = (int) ($trabalho->programa?->max_coorientadores ?? 1);
            if ($coorientacoes->count() >= $maximo) {
                $this->erro('coorientador_vinculo_id', "O programa permite no máximo {$maximo} coorientador(es) por discente.");
            }

            return $this->criar($trabalho, $docenteVinculoId, 'coorientador', ((int) $coorientacoes->max('ordem')) + 1, $secretaria);
        });
    }

    public function retirarCoorientador(Orientacao $coorientacao, User $secretaria): void
    {
        DB::transaction(function () use ($coorientacao) {
            $atual = Orientacao::whereKey($coorientacao->id)->lockForUpdate()->first();

            if (! $atual || $atual->tipo !== 'coorientador' || ! $atual->ativo) {
                $this->erro('coorientador_vinculo_id', 'Esta coorientação não está vigente.');
            }

            $this->encerrar($atual);
        });

        $coorientacao->refresh();
    }

    public function exigirOrientadorElegivel(TrabalhoAcademico $trabalho, int $docenteVinculoId): void
    {
        $vinculo = DocenteProgramaVinculo::find($docenteVinculoId);

        if (! $vinculo || ! $vinculo->ativo || ! $vinculo->pode_orientar || $vinculo->programa_id !== $trabalho->programa_id) {
            $this->erro('orientador_vinculo_id', 'O orientador deve ser docente ativo do programa com permissão de orientação.');
        }
    }

    /** @return Collection<int, Orientacao> */
    private function vigentesTravadas(TrabalhoAcademico $trabalho): Collection
    {
        return Orientacao::where('trabalho_academico_id', $trabalho->id)
            ->vigentes()
            ->lockForUpdate()
            ->get();
    }

    private function criar(TrabalhoAcademico $trabalho, int $docenteVinculoId, string $tipo, int $ordem, User $secretaria, array $extra = []): Orientacao
    {
        return Orientacao::create([
            'trabalho_academico_id'        => $trabalho->id,
            'discente_programa_vinculo_id' => $trabalho->discente_programa_vinculo_id,
            'docente_programa_vinculo_id'  => $docenteVinculoId,
            'tipo'                         => $tipo,
            'ordem'                        => $ordem,
            'ativo'                        => true,
            'inicio_em'                    => today(),
            'registrado_por'               => $secretaria->id,
            ...$extra,
        ]);
    }

    private function encerrar(Orientacao $orientacao): void
    {
        $orientacao->update(['ativo' => false, 'fim_em' => today()]);
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
