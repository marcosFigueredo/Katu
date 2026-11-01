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

    /** Reamostras do bootstrap e percentis do intervalo de 95%. */
    private const REAMOSTRAS = 500;

    /** [codigo => nota 0–10 ou null] a partir das avaliações enviadas pelos avaliadores ativos da banca. */
    public function dimensoesDaBanca(Banca $banca): array
    {
        return $this->dimensoesDe($this->notasPorAvaliador($banca));
    }

    /** Lista, por avaliador, de [item_id => ['codigo', 'peso', 'nota' normalizada 0–10]]. */
    public function notasPorAvaliador(Banca $banca): array
    {
        $codigos = DimensaoQualidade::pluck('codigo', 'id');
        $membros = $banca->avaliadores()->pluck('id');

        $avaliacoes = BancaAvaliacao::where('banca_id', $banca->id)
            ->where('status', 'enviada')
            ->whereIn('banca_membro_id', $membros)
            ->with(['notas' => fn ($q) => $q->orderBy('id'), 'notas.baremaItem'])
            ->orderBy('banca_membro_id')
            ->orderBy('id')
            ->get();

        $porAvaliador = [];
        foreach ($avaliacoes as $avaliacao) {
            foreach ($avaliacao->notas as $nota) {
                $item = $nota->baremaItem;
                $codigo = $codigos[$nota->dimensao_qualidade_id] ?? null;
                if ($nota->nota === null || ! $item || ! $codigo || (float) $item->nota_maxima <= 0) {
                    continue;
                }
                $porAvaliador[$avaliacao->banca_membro_id][$item->id] = [
                    'codigo' => $codigo,
                    'peso' => (float) $item->peso > 0 ? (float) $item->peso : 1.0,
                    'nota' => (float) $nota->nota / (float) $item->nota_maxima * 10,
                ];
            }
        }

        return array_values($porAvaliador);
    }

    /** Mediana dos avaliadores por pergunta; na dimensão, média das perguntas ponderada pelo peso de cada uma. */
    private function dimensoesDe(array $porAvaliador): array
    {
        $porItem = [];
        foreach ($porAvaliador as $itens) {
            foreach ($itens as $itemId => $dados) {
                $porItem[$itemId]['codigo'] = $dados['codigo'];
                $porItem[$itemId]['peso'] = $dados['peso'];
                $porItem[$itemId]['notas'][] = $dados['nota'];
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

    /** A dimensão "producao" combina a parte da banca (se houver) com a pontuação de produção. */
    private static function comProducao(array $dimensoes, ?float $producao): array
    {
        if ($producao !== null) {
            $dimensoes['producao'] = $dimensoes['producao'] === null ? $producao : round(($dimensoes['producao'] + $producao) / 2, 2);
        }

        return $dimensoes;
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
     * Índice do trabalho: ['dimensoes', 'indice', 'classe', 'periodo', 'regras'] e, com $comIntervalo (só para a CPA),
     * 'intervalo' [lo, hi] por bootstrap dos avaliadores e 'fronteira' (classe vizinha que o intervalo alcança).
     * Só defesa finalizada recebe índice e classe.
     */
    public function indiceTrabalho(Banca $defesa, bool $comIntervalo = false, ?PeriodoQualidade $periodo = null): array
    {
        $trabalho = $defesa->trabalhoAcademico;
        $data = $defesa->data_hora ?? $defesa->updated_at ?? now();
        // Na visão do programa, o período escolhido pela CPA vale para todos os trabalhos (faixas e pesos).
        $periodo ??= $trabalho?->programa ? PeriodoQualidade::doPrograma($trabalho->programa, $data) : null;
        $faixas = $periodo ?? new PeriodoQualidade();
        $pesos = $faixas->pesos('dimensao');

        $porAvaliador = $this->notasPorAvaliador($defesa);
        $producao = $trabalho ? $this->pontuacaoProducao($trabalho) : null;
        $dimensoes = self::comProducao($this->dimensoesDe($porAvaliador), $producao);

        $vazio = ['dimensoes' => $dimensoes, 'indice' => null, 'classe' => null, 'periodo' => $periodo, 'regras' => [], 'excluido' => false]
            + ($comIntervalo ? ['intervalo' => null, 'fronteira' => null] : []);
        if ($defesa->tipo !== 'defesa' || $defesa->status !== 'finalizada') {
            return $vazio;
        }
        // Plágio = jubilamento: o trabalho é como se não existisse e não entra em nenhuma métrica.
        if ($defesa->reprovada_por_plagio) {
            return ['excluido' => true] + $vazio;
        }

        $indice = self::mediaGeometrica($dimensoes, $pesos);
        if ($indice === null) {
            return $vazio;
        }

        [$classe, $regras] = $this->classificarTrabalho($defesa, $indice, $dimensoes, $faixas);
        $resultado = ['dimensoes' => $dimensoes, 'indice' => $indice, 'classe' => $classe, 'periodo' => $periodo, 'regras' => $regras,
            'excluido' => false, 'avaliadores' => count($porAvaliador)];

        if ($comIntervalo) {
            $intervalo = self::bootstrap($porAvaliador, 'trabalho-'.$defesa->id,
                fn (array $amostra) => self::mediaGeometrica(self::comProducao($this->dimensoesDe($amostra), $producao), $pesos));
            $resultado['intervalo'] = $intervalo;
            // Com regra de corte a classe não vem das faixas: o aviso de fronteira confundiria.
            $resultado['fronteira'] = $regras === [] ? self::fronteira($intervalo, $indice, $faixas) : null;
        }

        return $resultado;
    }

    /**
     * Índice do programa no período: por fonte e dimensão, a mediana entre as defesas; a dimensão do programa combina
     * as fontes com os pesos de "fonte"; o índice é a média geométrica ponderada pelos pesos de "dimensao".
     * Intervalo de 95% (informativo à CPA) por bootstrap das defesas.
     */
    public function indicePrograma(PeriodoQualidade $periodo): array
    {
        $defesas = Banca::where('tipo', 'defesa')
            ->where('status', 'finalizada')
            ->where('reprovada_por_plagio', false)
            ->whereHas('trabalhoAcademico', fn ($q) => $q->where('programa_id', $periodo->programa_id))
            ->whereDate('data_hora', '>=', $periodo->inicio_em)
            ->whereDate('data_hora', '<=', $periodo->fim_em)
            ->with('trabalhoAcademico.programa', 'trabalhoAcademico.publicacoes', 'trabalhoAcademico.discenteVinculo.discenteProfile.user')
            ->orderBy('data_hora')
            ->orderBy('id')
            ->get();

        $trabalhos = [];
        $distribuicao = array_fill_keys(array_keys(self::CLASSES), 0);
        // Por defesa: as notas por dimensão de cada fonte.
        $porDefesa = [];

        foreach ($defesas as $defesa) {
            $indice = $this->indiceTrabalho($defesa, comIntervalo: true, periodo: $periodo);
            $trabalhos[$defesa->id] = $indice + ['banca' => $defesa];
            if ($indice['classe'] !== null) {
                $distribuicao[$indice['classe']]++;
            }
            $porDefesa[$defesa->id]['trabalhos'] = array_filter($indice['dimensoes'], fn ($n) => $n !== null);
        }

        $ids = $defesas->pluck('id');
        foreach ([
            'autoavaliacao_orientador' => $this->dimensoesQuestionario('autoavaliacao_orientador_notas', 'autoavaliacoes_orientador', 'autoavaliacao_orientador_id', $ids),
            'espelho' => $this->dimensoesQuestionario('espelho_discente_notas', 'espelhos_discente', 'espelho_discente_id', $ids),
            'avaliacao_orientando' => $this->dimensoesOrientando($ids),
        ] as $fonte => $porBanca) {
            foreach ($porBanca as $bancaId => $notasPorDimensao) {
                $porDefesa[$bancaId][$fonte] = $notasPorDimensao;
            }
        }

        $pontual = $this->agregarPrograma(array_values($porDefesa), $periodo);
        $intervalo = $pontual['indice'] === null ? null
            : self::bootstrap(array_values($porDefesa), 'programa-'.$periodo->id, fn (array $amostra) => $this->agregarPrograma($amostra, $periodo)['indice']);

        return [
            'trabalhos' => $trabalhos,
            'fontes' => $pontual['fontes'],
            'dimensoes' => $pontual['dimensoes'],
            'indice' => $pontual['indice'],
            'classe' => $pontual['indice'] === null ? null : $periodo->classe($pontual['indice']),
            'distribuicao' => $distribuicao,
            'intervalo' => $intervalo,
            'fronteira' => $pontual['indice'] === null ? null : self::fronteira($intervalo, $pontual['indice'], $periodo),
        ];
    }

    /** Combina uma lista de defesas (cada uma com [fonte => [codigo => nota]]) em fontes, dimensões e índice do programa. */
    private function agregarPrograma(array $defesas, PeriodoQualidade $periodo): array
    {
        $codigos = array_column(DimensaoQualidade::PADRAO, 'codigo');
        $fontes = [];
        foreach (array_keys(PeriodoQualidade::FONTES) as $fonte) {
            foreach ($codigos as $codigo) {
                $notas = [];
                foreach ($defesas as $defesa) {
                    if (isset($defesa[$fonte][$codigo])) {
                        $notas[] = $defesa[$fonte][$codigo];
                    }
                }
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

        return ['fontes' => $fontes, 'dimensoes' => $dimensoes, 'indice' => self::mediaGeometrica($dimensoes, $periodo->pesos('dimensao'))];
    }

    /**
     * Intervalo de 95% por bootstrap: reamostra $unidades com reposição e aplica $estatistica.
     * Semente fixa ($semente) para o resultado não mudar a cada recarga.
     */
    private static function bootstrap(array $unidades, string $semente, callable $estatistica): ?array
    {
        $n = count($unidades);
        if ($n === 0) {
            return null;
        }

        // Gerador próprio: não mexe no estado aleatório global do PHP (Faker, shuffle etc.).
        $gerador = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32($semente)));
        $valores = [];
        for ($b = 0; $b < self::REAMOSTRAS; $b++) {
            $amostra = [];
            for ($i = 0; $i < $n; $i++) {
                $amostra[] = $unidades[$gerador->getInt(0, $n - 1)];
            }
            $valor = $estatistica($amostra);
            if ($valor !== null) {
                $valores[] = $valor;
            }
        }

        if ($valores === []) {
            return null;
        }
        sort($valores);
        $ultimo = count($valores) - 1;

        return [round($valores[(int) floor(0.025 * $ultimo)], 2), round($valores[(int) ceil(0.975 * $ultimo)], 2)];
    }

    /** Classe vizinha alcançada pelo intervalo (a de cima tem preferência); null se o intervalo fica na faixa do índice. */
    private static function fronteira(?array $intervalo, float $indice, PeriodoQualidade $faixas): ?int
    {
        if ($intervalo === null) {
            return null;
        }
        $classe = $faixas->classe($indice);
        $acima = $faixas->classe($intervalo[1]);
        $abaixo = $faixas->classe($intervalo[0]);

        return $acima < $classe ? $acima : ($abaixo > $classe ? $abaixo : null);
    }

    /** Questionário com perguntas de barema (autoavaliação, espelho): [banca_id => [codigo => média normalizada 0–10]]. */
    private function dimensoesQuestionario(string $tabelaNotas, string $tabelaAvaliacoes, string $chave, $bancaIds): array
    {
        $linhas = \Illuminate\Support\Facades\DB::table($tabelaNotas.' as n')
            ->join($tabelaAvaliacoes.' as a', 'a.id', '=', 'n.'.$chave)
            ->join('barema_itens as i', 'i.id', '=', 'n.barema_item_id')
            ->join('dimensoes_qualidade as d', 'd.id', '=', 'n.dimensao_qualidade_id')
            ->whereIn('a.banca_id', $bancaIds)
            ->where('i.nota_maxima', '>', 0)
            ->get(['a.banca_id', 'd.codigo', 'n.nota', 'i.nota_maxima']);

        $agrupado = [];
        foreach ($linhas as $linha) {
            $agrupado[$linha->banca_id][$linha->codigo][] = (float) $linha->nota / (float) $linha->nota_maxima * 10;
        }

        return array_map(fn ($porDimensao) => array_map(fn ($notas) => array_sum($notas) / count($notas), $porDimensao), $agrupado);
    }

    /** Avaliação do orientando (notas 0–10 por elemento): [banca_id => [codigo => média]]. */
    private function dimensoesOrientando($bancaIds): array
    {
        $linhas = \Illuminate\Support\Facades\DB::table('avaliacao_orientando_notas as n')
            ->join('avaliacoes_orientando as a', 'a.id', '=', 'n.avaliacao_orientando_id')
            ->join('dimensoes_qualidade as d', 'd.id', '=', 'n.dimensao_qualidade_id')
            ->whereIn('a.banca_id', $bancaIds)
            ->get(['a.banca_id', 'd.codigo', 'n.nota']);

        $agrupado = [];
        foreach ($linhas as $linha) {
            $agrupado[$linha->banca_id][$linha->codigo][] = (float) $linha->nota;
        }

        return array_map(fn ($porDimensao) => array_map(fn ($notas) => array_sum($notas) / count($notas), $porDimensao), $agrupado);
    }

    /** Faixas do período e regras de corte, nesta ordem: reprovação, dimensão < 4, Alta exige todas ≥ 7 (plágio sai antes, de tudo). */
    private function classificarTrabalho(Banca $defesa, float $indice, array $dimensoes, PeriodoQualidade $periodo): array
    {
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
