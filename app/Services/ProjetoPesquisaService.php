<?php

namespace App\Services;

use App\Models\DocenteProgramaVinculo;
use App\Models\Orientacao;
use App\Models\ProjetoPesquisa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoProjetoVinculo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Projetos de pesquisa dos docentes permanentes e o vínculo automático dos orientandos.
 * Um projeto ativo por docente; nunca fica sem projeto (só se conclui cadastrando outro);
 * o estudante só se liga a projeto ativo e muda junto quando o projeto do orientador muda.
 */
class ProjetoPesquisaService
{
    public function cadastrar(DocenteProgramaVinculo $docente, array $dados, User $autor): ProjetoPesquisa
    {
        if (! $docente->ehPermanente()) {
            throw ValidationException::withMessages(['titulo' => 'Projeto de pesquisa só para docente permanente do programa.']);
        }

        return DB::transaction(function () use ($docente, $dados, $autor) {
            $docente->projetos()->where('situacao', 'ativo')->get()->each(fn (ProjetoPesquisa $p) => $p->update([
                'situacao' => 'concluido',
                'fim_em' => $p->fim_em && $p->fim_em->lte(today()) ? $p->fim_em : today(),
            ]));

            $projeto = $docente->projetos()->create([
                'titulo' => trim($dados['titulo']),
                'descricao' => trim($dados['descricao']),
                'inicio_em' => $dados['inicio_em'],
                'fim_em' => $dados['fim_em'] ?? null,
                'situacao' => 'ativo',
                'registrado_por' => $autor->id,
            ]);

            Orientacao::where('docente_programa_vinculo_id', $docente->id)
                ->where('tipo', 'orientador')
                ->where('ativo', true)
                ->with('trabalhoAcademico')
                ->get()
                ->each(fn (Orientacao $o) => $o->trabalhoAcademico && $this->vincular($o->trabalhoAcademico, 'novo_projeto'));

            return $projeto;
        });
    }

    /** Correção do projeto ativo (texto e datas); não muda o vínculo dos orientandos. */
    public function atualizar(ProjetoPesquisa $projeto, array $dados): void
    {
        if ($projeto->situacao !== 'ativo') {
            throw ValidationException::withMessages(['titulo' => 'Projeto concluído não pode ser alterado.']);
        }

        $projeto->update([
            'titulo' => trim($dados['titulo']),
            'descricao' => trim($dados['descricao']),
            'inicio_em' => $dados['inicio_em'],
            'fim_em' => $dados['fim_em'] ?? null,
        ]);
    }

    /** Liga o trabalho ao projeto ativo do orientador vigente, encerrando o vínculo anterior quando muda. */
    public function vincular(TrabalhoAcademico $trabalho, string $motivo): ?TrabalhoProjetoVinculo
    {
        $projeto = $this->orientadorVigente($trabalho)?->projetoAtivo();
        $vigente = $trabalho->projetoVinculoVigente();

        if ($vigente && $projeto && $vigente->projeto_pesquisa_id === $projeto->id) {
            return $vigente;
        }

        $vigente?->update(['ativo' => false, 'fim_em' => today()]);

        if (! $projeto) {
            return null;
        }

        return $trabalho->projetoVinculos()->create([
            'projeto_pesquisa_id' => $projeto->id,
            'motivo' => $motivo,
            'inicio_em' => today(),
            'ativo' => true,
        ]);
    }

    /** Objetivo do último vínculo encerrado que tinha objetivo (para o estudante dizer se mantém). */
    public function objetivoAnterior(TrabalhoAcademico $trabalho): ?string
    {
        return $trabalho->projetoVinculos()
            ->where('ativo', false)
            ->whereNotNull('objetivo')
            ->latest('id')
            ->value('objetivo');
    }

    /** O estudante precisa de vínculo com projeto: o orientador é permanente ou já tem projeto ativo. */
    public function projetoExigido(TrabalhoAcademico $trabalho): bool
    {
        $orientador = $this->orientadorVigente($trabalho);

        return $orientador !== null && ($orientador->ehPermanente() || $orientador->projetoAtivo() !== null);
    }

    /**
     * O objetivo pode ser alterado? Sempre que o vínculo vigente ainda não tem objetivo (projeto mudou);
     * fora isso, antes de enviar a qualificação e entre a qualificação finalizada e o envio da defesa.
     */
    public function objetivoEditavel(TrabalhoAcademico $trabalho): bool
    {
        $vigente = $trabalho->projetoVinculoVigente();
        if (! $vigente) {
            return false;
        }
        if ($vigente->objetivo === null) {
            return true;
        }

        if (app(PedidoHomologacaoService::class)->defesaEnviada($trabalho)) {
            return false;
        }

        return ! $trabalho->bancas()
            ->where('tipo', 'qualificacao')
            ->where('status', '!=', 'cancelada')
            ->where('status', '!=', 'finalizada')
            ->whereIn('homologacao_status', ['aguardando', 'homologada'])
            ->exists();
    }

    private function orientadorVigente(TrabalhoAcademico $trabalho): ?DocenteProgramaVinculo
    {
        return $trabalho->orientacoes()
            ->where('tipo', 'orientador')
            ->with('docenteProgramaVinculo')
            ->first()
            ?->docenteProgramaVinculo;
    }
}
