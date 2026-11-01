<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\DimensaoQualidade;
use App\Models\PeriodoQualidade;
use App\Models\TrabalhoAcademico;

/**
 * Índice de qualidade (versão de teste, calculado na hora).
 * Trabalho: notas da banca (mediana dos avaliadores por pergunta, agrupada pela dimensão congelada) + produção associada;
 * média geométrica ponderada pelos pesos da CPA, para uma dimensão fraca não ser compensada; 5 classes com regras de corte.
 */
class IndiceQualidadeService
{
    public const CLASSES = [
        1 => 'Alta qualidade',
        2 => 'Boa qualidade',
        3 => 'Suficiente',
        4 => 'Pouca qualidade',
        5 => 'Baixa qualidade',
    ];

    /** Piso da média geométrica: evita log de zero sem esconder a nota baixa. */
    private const PISO = 0.5;

    public function __construct(private readonly PedidoHomologacaoService $pedidos)
    {
    }

    /** [codigo => nota 0–10 ou null] a partir das avaliações enviadas pelos avaliadores ativos da banca. */
    public function dimensoesDaBanca(Banca $banca): array
    {
        $codigos = DimensaoQualidade::pluck('codigo', 'id');
        $membros = $banca->avaliadores()->pluck('id');

        $avaliacoes = BancaAvaliacao::where('banca_id', $banca->id)
            ->where('status', 'enviada')
            ->whereIn('banca_membro_id', $membros)
            ->with('notas.baremaItem')
            ->get();

        // Por pergunta: notas normalizadas de cada avaliador, a dimensão congelada e o peso da pergunta.
        $porItem = [];
        foreach ($avaliacoes as $avaliacao) {
            foreach ($avaliacao->notas as $nota) {
                $item = $nota->baremaItem;
                $codigo = $codigos[$nota->dimensao_qualidade_id] ?? null;
                if ($nota->nota === null || ! $item || ! $codigo || (float) $item->nota_maxima <= 0) {
                    continue;
                }
                $porItem[$item->id]['codigo'] = $codigo;
                $porItem[$item->id]['peso'] = (float) $item->peso > 0 ? (float) $item->peso : 1.0;
                $porItem[$item->id]['notas'][] = (float) $nota->nota / (float) $item->nota_maxima * 10;
            }
        }

        $soma = [];
        $pesos = [];
        foreach ($porItem as $dados) {
            $codigo = $dados['codigo'];
            $soma[$codigo] = ($soma[$codigo] ?? 0) + self::mediana($dados['notas']) * $dados['peso'];
            $pesos[$codigo] = ($pesos[$codigo] ?? 0) + $dados['peso'];
        }

        $resultado = [];
        foreach (array_column(DimensaoQualidade::PADRAO, 'codigo') as $codigo) {
            $resultado[$codigo] = isset($soma[$codigo]) ? round($soma[$codigo] / $pesos[$codigo], 2) : null;
        }

        return $resultado;
    }

    /**
     * Produção associada em 0–10. Pontos: publicação conferida, derivada da dissertação, publicada (1) ou aceita (0,75);
     * +0,5 estudante primeiro autor; +0,5 coautoria internacional; extensão +0,5; popularização +0,25.
     * Nota = 10 × pontos ÷ (2 × mínimo do curso), limitada a 10. Sem nenhum registro: null.
     */
    public function pontuacaoProducao(TrabalhoAcademico $trabalho): ?float
    {
        $publicacoes = $trabalho->publicacoes()->get();
        $extensoes = $trabalho->extensoes()->count();
        $popularizacoes = $trabalho->popularizacoes()->count();
        if ($publicacoes->isEmpty() && $extensoes === 0 && $popularizacoes === 0) {
            return null;
        }

        $pontos = 0.0;
        foreach ($publicacoes as $publicacao) {
            if (! $publicacao->conferida_em || ! $publicacao->derivada_dissertacao || ! in_array($publicacao->situacao, ['publicada', 'aceita'], true)) {
                continue;
            }
            $pontos += $publicacao->situacao === 'publicada' ? 1.0 : 0.75;
            $pontos += $publicacao->discente_primeiro_autor ? 0.5 : 0;
            $pontos += $publicacao->coautoria_internacional ? 0.5 : 0;
        }
        $pontos += 0.5 * $extensoes + 0.25 * $popularizacoes;

        $minimo = max(1, $this->pedidos->minimoPublicacoes($trabalho));

        return round(min(10, 10 * $pontos / (2 * $minimo)), 2);
    }

    /**
     * Índice do trabalho: ['dimensoes', 'indice', 'classe', 'periodo', 'regras'].
     * Só defesa finalizada recebe índice e classe.
     */
    public function indiceTrabalho(Banca $defesa): array
    {
        $trabalho = $defesa->trabalhoAcademico;
        $data = $defesa->data_hora ?? $defesa->updated_at ?? now();
        $periodo = $trabalho?->programa ? PeriodoQualidade::doPrograma($trabalho->programa, $data) : null;

        $dimensoes = $this->dimensoesDaBanca($defesa);
        $producao = $trabalho ? $this->pontuacaoProducao($trabalho) : null;
        if ($producao !== null) {
            $dimensoes['producao'] = $dimensoes['producao'] === null ? $producao : round(($dimensoes['producao'] + $producao) / 2, 2);
        }

        $vazio = ['dimensoes' => $dimensoes, 'indice' => null, 'classe' => null, 'periodo' => $periodo, 'regras' => []];
        if ($defesa->tipo !== 'defesa' || $defesa->status !== 'finalizada') {
            return $vazio;
        }

        $indice = self::mediaGeometrica($dimensoes, ($periodo ?? new PeriodoQualidade())->pesos('dimensao'));
        if ($indice === null) {
            return $vazio;
        }

        [$classe, $regras] = $this->classificarTrabalho($defesa, $indice, $dimensoes, $periodo ?? new PeriodoQualidade());

        return ['dimensoes' => $dimensoes, 'indice' => $indice, 'classe' => $classe, 'periodo' => $periodo, 'regras' => $regras];
    }

    /**
     * Índice do programa no período: por fonte e dimensão, a mediana entre os trabalhos; a dimensão do programa combina
     * as fontes com os pesos de "fonte"; o índice é a média geométrica ponderada pelos pesos de "dimensao".
     */
    public function indicePrograma(PeriodoQualidade $periodo): array
    {
        $defesas = Banca::where('tipo', 'defesa')
            ->where('status', 'finalizada')
            ->whereHas('trabalhoAcademico', fn ($q) => $q->where('programa_id', $periodo->programa_id))
            ->whereDate('data_hora', '>=', $periodo->inicio_em)
            ->whereDate('data_hora', '<=', $periodo->fim_em)
            ->with('trabalhoAcademico.programa', 'trabalhoAcademico.publicacoes', 'trabalhoAcademico.discenteVinculo.discenteProfile.user')
            ->orderBy('data_hora')
            ->get();

        $codigos = array_column(DimensaoQualidade::PADRAO, 'codigo');
        $trabalhos = [];
        $porFonte = array_fill_keys(array_keys(PeriodoQualidade::FONTES), array_fill_keys($codigos, []));
        $distribuicao = array_fill_keys(array_keys(self::CLASSES), 0);

        foreach ($defesas as $defesa) {
            $indice = $this->indiceTrabalho($defesa);
            $trabalhos[$defesa->id] = $indice + ['banca' => $defesa];
            if ($indice['classe'] !== null) {
                $distribuicao[$indice['classe']]++;
            }
            foreach ($indice['dimensoes'] as $codigo => $nota) {
                if ($nota !== null) {
                    $porFonte['trabalhos'][$codigo][] = $nota;
                }
            }
        }

        $ids = $defesas->pluck('id');
        foreach ([
            'autoavaliacao_orientador' => $this->dimensoesQuestionario('autoavaliacao_orientador_notas', 'autoavaliacoes_orientador', 'autoavaliacao_orientador_id', $ids),
            'espelho' => $this->dimensoesQuestionario('espelho_discente_notas', 'espelhos_discente', 'espelho_discente_id', $ids),
            'avaliacao_orientando' => $this->dimensoesOrientando($ids),
        ] as $fonte => $porAvaliacao) {
            foreach ($porAvaliacao as $notasPorDimensao) {
                foreach ($notasPorDimensao as $codigo => $nota) {
                    $porFonte[$fonte][$codigo][] = $nota;
                }
            }
        }

        $fontes = [];
        foreach ($porFonte as $fonte => $porDimensao) {
            foreach ($porDimensao as $codigo => $notas) {
                $fontes[$fonte][$codigo] = $notas === [] ? null : round(self::mediana($notas), 2);
            }
        }

        $pesosFonte = $periodo->pesos('fonte');
        $dimensoes = [];
        foreach ($codigos as $codigo) {
            $soma = 0.0;
            $somaPesos = 0.0;
            foreach ($fontes as $fonte => $porDimensao) {
                $peso = (float) ($pesosFonte[$fonte] ?? 0);
                if ($porDimensao[$codigo] !== null && $peso > 0) {
                    $soma += $peso * $porDimensao[$codigo];
                    $somaPesos += $peso;
                }
            }
            $dimensoes[$codigo] = $somaPesos > 0 ? round($soma / $somaPesos, 2) : null;
        }

        $indice = self::mediaGeometrica($dimensoes, $periodo->pesos('dimensao'));

        return [
            'trabalhos' => $trabalhos,
            'fontes' => $fontes,
            'dimensoes' => $dimensoes,
            'indice' => $indice,
            'classe' => $indice === null ? null : $periodo->classe($indice),
            'distribuicao' => $distribuicao,
        ];
    }

    /** Questionário com perguntas de barema (autoavaliação, espelho): [avaliação => [codigo => média normalizada 0–10]]. */
    private function dimensoesQuestionario(string $tabelaNotas, string $tabelaAvaliacoes, string $chave, $bancaIds): array
    {
        $linhas = \Illuminate\Support\Facades\DB::table($tabelaNotas.' as n')
            ->join($tabelaAvaliacoes.' as a', 'a.id', '=', 'n.'.$chave)
            ->join('barema_itens as i', 'i.id', '=', 'n.barema_item_id')
            ->join('dimensoes_qualidade as d', 'd.id', '=', 'n.dimensao_qualidade_id')
            ->whereIn('a.banca_id', $bancaIds)
            ->where('i.nota_maxima', '>', 0)
            ->get(['n.'.$chave.' as avaliacao', 'd.codigo', 'n.nota', 'i.nota_maxima']);

        $agrupado = [];
        foreach ($linhas as $linha) {
            $agrupado[$linha->avaliacao][$linha->codigo][] = (float) $linha->nota / (float) $linha->nota_maxima * 10;
        }

        return array_map(fn ($porDimensao) => array_map(fn ($notas) => array_sum($notas) / count($notas), $porDimensao), $agrupado);
    }

    /** Avaliação do orientando (notas 0–10 por elemento): [avaliação => [codigo => média]]. */
    private function dimensoesOrientando($bancaIds): array
    {
        $linhas = \Illuminate\Support\Facades\DB::table('avaliacao_orientando_notas as n')
            ->join('avaliacoes_orientando as a', 'a.id', '=', 'n.avaliacao_orientando_id')
            ->join('dimensoes_qualidade as d', 'd.id', '=', 'n.dimensao_qualidade_id')
            ->whereIn('a.banca_id', $bancaIds)
            ->get(['n.avaliacao_orientando_id as avaliacao', 'd.codigo', 'n.nota']);

        $agrupado = [];
        foreach ($linhas as $linha) {
            $agrupado[$linha->avaliacao][$linha->codigo][] = (float) $linha->nota;
        }

        return array_map(fn ($porDimensao) => array_map(fn ($notas) => array_sum($notas) / count($notas), $porDimensao), $agrupado);
    }

    /** Faixas do período e regras de corte, nesta ordem: plágio, reprovação, dimensão < 4, Alta exige todas ≥ 7. */
    private function classificarTrabalho(Banca $defesa, float $indice, array $dimensoes, PeriodoQualidade $periodo): array
    {
        if ($defesa->reprovada_por_plagio) {
            return [5, ['Reprovada por plágio']];
        }

        $classe = $periodo->classe($indice);
        $regras = [];
        $notas = array_filter($dimensoes, fn ($n) => $n !== null);

        if ($defesa->resultado === 'reprovado' && $classe < 4) {
            $classe = 4;
            $regras[] = 'Banca reprovou: no máximo Pouca qualidade';
        }
        if ($notas !== [] && min($notas) < 4 && $classe < 3) {
            $classe = 3;
            $regras[] = 'Dimensão abaixo de 4: no máximo Suficiente';
        }
        if ($classe === 1 && min($notas) < 7) {
            $classe = 2;
            $regras[] = 'Alta qualidade exige todas as dimensões a partir de 7';
        }

        return [$classe, $regras];
    }

    /** exp(Σ wᵢ·ln(max(xᵢ, piso)) / Σ wᵢ) nas dimensões com nota; null se nenhuma. */
    public static function mediaGeometrica(array $valores, array $pesos): ?float
    {
        $soma = 0.0;
        $somaPesos = 0.0;
        foreach ($valores as $chave => $valor) {
            $peso = (float) ($pesos[$chave] ?? 0);
            if ($valor === null || $peso <= 0) {
                continue;
            }
            $soma += $peso * log(max((float) $valor, self::PISO));
            $somaPesos += $peso;
        }

        return $somaPesos > 0 ? round(exp($soma / $somaPesos), 2) : null;
    }

    public static function mediana(array $valores): ?float
    {
        if ($valores === []) {
            return null;
        }
        sort($valores);
        $n = count($valores);
        $meio = intdiv($n, 2);

        return $n % 2 ? (float) $valores[$meio] : ($valores[$meio - 1] + $valores[$meio]) / 2;
    }
}
