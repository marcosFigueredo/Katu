<?php

namespace App\Services;

use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\Banca;
use App\Models\BancaIndicador;
use App\Models\DocumentoAnalise;
use Carbon\Carbon;
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
            'trabalhoAcademico.discenteVinculo',
            'membros.docenteProgramaVinculo.instituicao',
        ])
            ->where('status', 'finalizada')
            ->whereNotNull('data_hora')
            ->whereDate('data_hora', '>=', $inicio)
            ->whereDate('data_hora', '<=', $fim)
            ->get();

        $agora = Carbon::now();

        DB::transaction(function () use ($periodo, $bancas, $agora) {
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

            // Por linha de pesquisa
            $porLinha = $bancas->groupBy(fn ($b) => $b->trabalhoAcademico?->linhaPesquisa?->nome ?? 'Sem linha');
            $detalhesLinha = $porLinha->map(fn ($grupo) => [
                'total'    => $grupo->count(),
                'aprovadas'=> $grupo->whereIn('resultado', ['aprovado', 'aprovado_modificacoes'])->count(),
            ])->toArray();
            $this->upsert($periodo, 'A12', 'Bancas por linha de pesquisa', null, json_encode($detalhesLinha, JSON_UNESCAPED_UNICODE), $agora);

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

            $a13 = $totalQual > 0
                ? round($mesesAteQual->filter(fn ($m) => $m <= 18)->count() / $totalQual * 100, 1)
                : null;

            $a14 = $totalDefesa > 0
                ? round($mesesAteDefesa->filter(fn ($m) => $m <= 24)->count() / $totalDefesa * 100, 1)
                : null;

            $a15 = $mesesAteQual->isNotEmpty()   ? round($mesesAteQual->avg(), 1)   : null;
            $a16 = $mesesAteDefesa->isNotEmpty()  ? round($mesesAteDefesa->avg(), 1) : null;

            $this->upsert($periodo, 'A13', 'Qualificações realizadas em ≤ 18 meses (%)', $a13, null, $agora);
            $this->upsert($periodo, 'A14', 'Defesas realizadas em ≤ 24 meses (%)',        $a14, null, $agora);
            $this->upsert($periodo, 'A15', 'Tempo médio até a qualificação (meses)',       $a15, null, $agora);
            $this->upsert($periodo, 'A16', 'Tempo médio até a defesa (meses)',             $a16, null, $agora);

            $periodo->update(['status' => 'concluido']);
        });
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
