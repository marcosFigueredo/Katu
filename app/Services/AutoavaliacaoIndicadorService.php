<?php

namespace App\Services;

use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\Banca;
use App\Models\BancaIndicador;
use App\Models\DocumentoAnalise;
use App\Models\TrabalhoIndicador;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AutoavaliacaoIndicadorService
{
    public function calcular(AutoavaliacaoPeriodo $periodo): void
    {
        $inicio = $periodo->data_inicio;
        $fim    = $periodo->data_fim;

        $bancas = Banca::with([
            'avaliacoes.notas.baremaItem',
            'indicadores',
            'trabalhoAcademico.linhaPesquisa',
            'trabalhoAcademico.discenteVinculo.programaCurso',
            'trabalhoAcademico.documentos.analises.ollama',
            'membros.docenteProgramaVinculo.instituicao',
        ])
            ->where('status', 'finalizada')
            ->when($periodo->programa_id, fn ($q) => $q->whereHas(
                'trabalhoAcademico',
                fn ($t) => $t->where('programa_id', $periodo->programa_id)
            ))
            ->whereNotNull('data_hora')
            ->whereDate('data_hora', '>=', $inicio)
            ->whereDate('data_hora', '<=', $fim)
            ->get();

        $agora = Carbon::now();

        DB::transaction(function () use ($periodo, $bancas, $agora, $inicio, $fim) {
            $this->upsert($periodo, 'A01', 'Total de bancas no período', $bancas->count(), null, $agora);
            $this->upsert($periodo, 'A02', 'Bancas de qualificação', $bancas->where('tipo', 'qualificacao')->count(), null, $agora);
            $this->upsert($periodo, 'A03', 'Bancas de defesa', $bancas->where('tipo', 'defesa')->count(), null, $agora);

            // Nota média geral
            $notasFinais = $bancas->flatMap(fn ($b) => $b->avaliacoes)->pluck('nota_final')->filter();
            $this->upsert($periodo, 'A04', 'Nota média geral das bancas', $notasFinais->isNotEmpty() ? round($notasFinais->avg(), 2) : null, null, $agora);

            // Aprovação
            $total   = $bancas->count();
            $aprovadas = $bancas->whereIn('resultado', ['aprovado', 'aprovado_modificacoes'])->count();
            $this->upsert($periodo, 'A05', 'Percentual de trabalhos aprovados (%)', $total > 0 ? round($aprovadas / $total * 100, 1) : null, null, $agora);

            // ORCID
            $indicadoresBanca = BancaIndicador::whereIn('banca_id', $bancas->pluck('id'))->get();
            $comOrcidCompleto = $indicadoresBanca->where('b1', true)->count();
            $this->upsert($periodo, 'A06', 'Bancas com ORCID completo (B1) (%)', $total > 0 ? round($comOrcidCompleto / $total * 100, 1) : null, null, $agora);

            // Externo
            $comExterno = $indicadoresBanca->where('b2', true)->count();
            $this->upsert($periodo, 'A07', 'Bancas com avaliador externo (B2) (%)', $total > 0 ? round($comExterno / $total * 100, 1) : null, null, $agora);

            // Diversidade institucional
            $comDiversidade = $indicadoresBanca->where('b3', true)->count();
            $this->upsert($periodo, 'A08', 'Bancas com diversidade institucional (B3) (%)', $total > 0 ? round($comDiversidade / $total * 100, 1) : null, null, $agora);

            // Índice médio ORCID
            $indices = $indicadoresBanca->pluck('indice_banca')->filter();
            $this->upsert($periodo, 'A09', 'Índice médio de composição das bancas (B1–B8)', $indices->isNotEmpty() ? round($indices->avg(), 3) : null, null, $agora);

            // Conformidade documental
            $analises = DocumentoAnalise::whereHas('trabalhoDocumento.trabalhoAcademico', function ($q) use ($inicio, $fim) {
                $q->whereHas('bancas', fn ($b) =>
                    $b->where('status', 'finalizada')
                      ->whereDate('data_hora', '>=', $inicio)
                      ->whereDate('data_hora', '<=', $fim)
                );
            })->get();

            $totalDocs   = $analises->count();
            $conformes   = $analises->where('status_geral', 'conforme')->count();
            $percentualConf = $totalDocs > 0 ? round($analises->avg('percentual_conformidade'), 1) : null;
            $this->upsert($periodo, 'A10', 'Percentual médio de conformidade documental (%)', $percentualConf, null, $agora);
            $this->upsert($periodo, 'A11', 'Documentos 100% conformes (%)', $totalDocs > 0 ? round($conformes / $totalDocs * 100, 1) : null, null, $agora);

            // Aderência temática (IA/Ollama) de cada trabalho à linha de pesquisa declarada
            $aderenciaPorBanca = $bancas->mapWithKeys(fn ($b) => [$b->id => $this->obterAderenciaTrabalho($b)]);

            // Por linha de pesquisa — quantidade, aprovação, nota média e aderência temática
            $porLinha = $bancas->groupBy(fn ($b) => $b->trabalhoAcademico?->linhaPesquisa?->nome ?? 'Sem linha');
            $detalhesLinha = $porLinha->map(function ($grupo) use ($aderenciaPorBanca) {
                $notas = $grupo->flatMap(fn ($b) => $b->avaliacoes)->pluck('nota_final')->filter();
                $aderencias = $grupo->map(fn ($b) => $aderenciaPorBanca[$b->id])->filter();

                return [
                    'total'                  => $grupo->count(),
                    'aprovadas'              => $grupo->whereIn('resultado', ['aprovado', 'aprovado_modificacoes'])->count(),
                    'nota_media'             => $notas->isNotEmpty() ? round($notas->avg(), 2) : null,
                    'aderencia_aparente_pct' => $aderencias->isNotEmpty()
                        ? round($aderencias->filter(fn ($v) => $v === 'aparente')->count() / $aderencias->count() * 100, 1)
                        : null,
                ];
            })->toArray();
            $this->upsert($periodo, 'A12', 'Bancas por linha de pesquisa: aprovação, nota média e aderência (IA)', null, json_encode($detalhesLinha, JSON_UNESCAPED_UNICODE), $agora);

            // A17 — Aderência temática confirmada por IA à linha de pesquisa (agregado do PPG)
            $todasAderencias = $aderenciaPorBanca->filter();
            $a17 = $todasAderencias->isNotEmpty()
                ? round($todasAderencias->filter(fn ($v) => $v === 'aparente')->count() / $todasAderencias->count() * 100, 1)
                : null;
            $this->upsert($periodo, 'A17', 'Trabalhos com aderência temática confirmada por IA à linha de pesquisa (%)', $a17, null, $agora);

            // A18 — Produtos resultantes: trabalhos com produção bibliográfica indexada com DOI
            $trabalhoIds = $bancas->pluck('trabalhoAcademico.id')->filter()->unique();
            $indicadoresProdutos = TrabalhoIndicador::whereIn('trabalho_academico_id', $trabalhoIds)->get();
            $comDoi = $indicadoresProdutos->filter(fn ($i) => $i->total_com_doi > 0)->count();
            $this->upsert(
                $periodo,
                'A18',
                'Trabalhos com produção bibliográfica indexada com DOI (%)',
                $trabalhoIds->isNotEmpty() ? round($comDoi / $trabalhoIds->count() * 100, 1) : null,
                null,
                $agora
            );

            // Eficiência do percurso — A13 a A16
            $bancasComIngresso = $bancas->filter(
                fn ($b) => $b->data_hora && $b->trabalhoAcademico?->discenteVinculo?->data_ingresso
            );

            $qualificacoes = $bancasComIngresso->where('tipo', 'qualificacao');
            $defesas       = $bancasComIngresso->where('tipo', 'defesa');

            $mesesAteQual = $qualificacoes->map(
                fn ($b) => $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora)
            );
            $mesesAteDefesa = $defesas->map(
                fn ($b) => $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora)
            );

            $totalQual   = $qualificacoes->count();
            $totalDefesa = $defesas->count();

            $a13 = $this->percentualNoPrazo($qualificacoes, 'qualificacao');
            $a14 = $this->percentualNoPrazo($defesas, 'defesa');

            $a15 = $mesesAteQual->isNotEmpty()   ? round($mesesAteQual->avg(), 1)   : null;
            $a16 = $mesesAteDefesa->isNotEmpty()  ? round($mesesAteDefesa->avg(), 1) : null;

            $this->upsert($periodo, 'A13', 'Qualificações realizadas no prazo do curso (%)', $a13, null, $agora);
            $this->upsert($periodo, 'A14', 'Defesas realizadas no prazo do curso (%)',      $a14, null, $agora);
            $this->upsert($periodo, 'A15', 'Tempo médio até a qualificação (meses)',       $a15, null, $agora);
            $this->upsert($periodo, 'A16', 'Tempo médio até a defesa (meses)',             $a16, null, $agora);

            $periodo->update(['status' => 'concluido']);
        });
    }

    private function obterAderenciaTrabalho(Banca $banca): ?string
    {
        $documentos = $banca->trabalhoAcademico?->documentos ?? collect();

        return $documentos
            ->flatMap(fn ($doc) => $doc->analises)
            ->map(fn ($analise) => $analise->ollama)
            ->filter(fn ($ollama) => $ollama && $ollama->status === 'concluida')
            ->sortByDesc('gerado_em')
            ->first()
            ?->resposta['aderencia_ao_programa'] ?? null;
    }

    /**
     * Percentual das bancas da etapa realizadas dentro do prazo do curso do discente
     * (fallback 18/24 meses para vínculos sem curso). Espera bancas com
     * trabalhoAcademico.discenteVinculo(.programaCurso) carregados e data de ingresso.
     */
    public function percentualNoPrazo(Collection $bancas, string $etapa): ?float
    {
        $bancas = $bancas->where('tipo', $etapa)
            ->filter(fn ($b) => $b->data_hora && $b->trabalhoAcademico?->discenteVinculo?->data_ingresso);

        if ($bancas->isEmpty()) {
            return null;
        }

        [$campo, $padrao] = $etapa === 'qualificacao' ? ['prazo_qualificacao_meses', 18] : ['prazo_defesa_meses', 24];

        $noPrazo = $bancas->filter(function ($b) use ($campo, $padrao) {
            $vinculo = $b->trabalhoAcademico->discenteVinculo;

            return $vinculo->data_ingresso->diffInMonths($b->data_hora) <= ($vinculo->programaCurso?->{$campo} ?? $padrao);
        });

        return round($noPrazo->count() / $bancas->count() * 100, 1);
    }

    private function upsert(
        AutoavaliacaoPeriodo $periodo,
        string $codigo,
        string $nome,
        ?float $valorNumerico,
        ?string $valorTexto,
        Carbon $calculadoEm
    ): void {
        AutoavaliacaoIndicador::updateOrCreate(
            ['autoavaliacao_periodo_id' => $periodo->id, 'codigo' => $codigo],
            [
                'nome'            => $nome,
                'valor_numerico'  => $valorNumerico,
                'valor_texto'     => $valorTexto,
                'calculado_em'    => $calculadoEm,
            ]
        );
    }
}
