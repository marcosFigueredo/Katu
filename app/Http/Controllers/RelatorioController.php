<?php

namespace App\Http\Controllers;

use App\Models\AutoavaliacaoPeriodo;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\BancaAvaliacaoNota;
use App\Models\BancaIndicador;
use App\Models\BancaMembro;
use App\Models\DocumentoAnalise;
use App\Models\DocumentoAnaliseItem;
use App\Models\MabacAnalise;
use App\Models\OrcidSnapshot;
use App\Models\OrcidWork;
use App\Models\OrientacaoAvaliacao;
use App\Models\Orientacao;
use App\Models\SwaraSessao;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoIndicador;
use App\Models\TrabalhoImpacto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    private function periodo(Request $request): array
    {
        $inicio = $request->filled('inicio')
            ? Carbon::parse($request->inicio)->startOfDay()
            : Carbon::now()->subYear()->startOfDay();
        $fim = $request->filled('fim')
            ? Carbon::parse($request->fim)->endOfDay()
            : Carbon::now()->endOfDay();
        return [$inicio, $fim];
    }

    private function guardaSec(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->guardaSec();
        return view('relatorios.index');
    }

    // ── Bloco 1: Desempenho das bancas ───────────────────────────────────────

    public function bancas(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        $bancas = Banca::with([
            'trabalhoAcademico.linhaPesquisa',
            'avaliacoes.membro.docenteProgramaVinculo.docenteProfile.user',
        ])
            ->where('status', 'finalizada')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->get();

        // 1. Resultados por tipo
        $resultadosPorTipo = $bancas->groupBy('tipo')->map(fn ($g) =>
            $g->groupBy('resultado')->map->count()
        );

        // 2. Nota média por avaliador
        $notasPorAvaliador = $bancas->flatMap->avaliacoes
            ->filter(fn ($a) => $a->nota_final !== null)
            ->groupBy(fn ($a) => $a->membro?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? 'Desconhecido')
            ->map(fn ($notas) => [
                'media'  => round($notas->avg('nota_final'), 2),
                'total'  => $notas->count(),
                'min'    => round($notas->min('nota_final'), 2),
                'max'    => round($notas->max('nota_final'), 2),
            ])
            ->sortByDesc('media');

        // 3. Variância intra-banca (desvio padrão entre avaliadores por banca)
        $variancias = $bancas->map(function ($banca) {
            $notas = $banca->avaliacoes->pluck('nota_final')->filter()->values();
            if ($notas->count() < 2) return null;
            $media = $notas->avg();
            $variancia = $notas->reduce(fn ($c, $v) => $c + pow($v - $media, 2), 0) / $notas->count();
            return round(sqrt($variancia), 2);
        })->filter()->values();

        $distribuicaoVariancia = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($variancias as $v) {
            if ($v < 0.5) $distribuicaoVariancia[0]++;
            elseif ($v < 1.0) $distribuicaoVariancia[1]++;
            elseif ($v < 1.5) $distribuicaoVariancia[2]++;
            else $distribuicaoVariancia[3]++;
        }

        // 4. Desempenho por item de barema (aproveitamento %)
        $notasPorItem = BancaAvaliacaoNota::with('baremaItem')
            ->whereHas('avaliacao.banca', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->whereNotNull('nota')
            ->get()
            ->groupBy('barema_item_id')
            ->map(fn ($notas) => [
                'nome'         => $notas->first()->baremaItem?->nome ?? '—',
                'aproveitamento'=> $notas->first()->baremaItem?->nota_maxima > 0
                    ? round($notas->avg('nota') / $notas->first()->baremaItem->nota_maxima * 100, 1)
                    : null,
                'total'        => $notas->count(),
            ])
            ->filter(fn ($i) => $i['aproveitamento'] !== null)
            ->sortBy('aproveitamento')
            ->values();

        // 5. Por seção do barema
        $notasPorSecao = BancaAvaliacaoNota::with('baremaItem.secao')
            ->whereHas('avaliacao.banca', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->whereNotNull('nota')
            ->get()
            ->groupBy(fn ($n) => $n->baremaItem?->secao?->nome ?? 'Sem seção')
            ->map(fn ($notas) => [
                'aproveitamento' => $notas->first()->baremaItem?->nota_maxima > 0
                    ? round($notas->avg('nota') / $notas->first()->baremaItem->nota_maxima * 100, 1)
                    : null,
            ])
            ->filter(fn ($s) => $s['aproveitamento'] !== null);

        // 6. Série temporal (por mês)
        $serieTemporal = $bancas->filter(fn ($b) => $b->nota_media !== null)
            ->groupBy(fn ($b) => $b->data_hora->format('Y-m'))
            ->map(fn ($g) => round($g->avg('nota_media'), 2))
            ->sortKeys();

        // 7. Por linha de pesquisa
        $porLinha = $bancas->groupBy(fn ($b) => $b->trabalhoAcademico?->linhaPesquisa?->nome ?? 'Sem linha')
            ->map(fn ($g) => [
                'total'  => $g->count(),
                'media'  => round($g->avg('nota_media'), 2),
                'aprovadas' => $g->whereIn('resultado', ['aprovado', 'aprovado_modificacoes'])->count(),
            ])
            ->sortByDesc('media');

        return view('relatorios.bancas', compact(
            'inicio', 'fim', 'bancas',
            'resultadosPorTipo', 'notasPorAvaliador', 'distribuicaoVariancia',
            'notasPorItem', 'notasPorSecao', 'serieTemporal', 'porLinha'
        ));
    }

    // ── Bloco 2: Composição das bancas (B1–B8) ───────────────────────────────

    public function composicao(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        $indicadores = BancaIndicador::whereHas('banca', fn ($q) =>
            $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
        )->with('banca.trabalhoAcademico.linhaPesquisa')->get();

        $total = $indicadores->count();

        // 8. % de atendimento de cada B
        $atendimento = [];
        foreach (['b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7', 'b8'] as $b) {
            $atendimento[$b] = $total > 0 ? round($indicadores->where($b, true)->count() / $total * 100, 1) : 0;
        }

        // 9. Evolução indice_banca por mês
        $evolucaoIndice = BancaIndicador::whereHas('banca', fn ($q) =>
            $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
        )
            ->with('banca')
            ->get()
            ->groupBy(fn ($i) => $i->banca->data_hora->format('Y-m'))
            ->map(fn ($g) => round($g->avg('indice_banca'), 3))
            ->sortKeys();

        // 10. Correlação indice_banca × nota_media
        $correlacaoData = BancaIndicador::whereHas('banca', fn ($q) =>
            $q->where('status', 'finalizada')
              ->whereNotNull('nota_media')
              ->whereBetween('data_hora', [$inicio, $fim])
        )->with('banca')->get()
            ->map(fn ($i) => [
                'x' => round($i->indice_banca, 3),
                'y' => round($i->banca->nota_media, 2),
            ])
            ->values();

        // Por linha de pesquisa
        $indicadoresPorLinha = $indicadores
            ->groupBy(fn ($i) => $i->banca->trabalhoAcademico?->linhaPesquisa?->nome ?? 'Sem linha')
            ->map(fn ($g) => [
                'total'  => $g->count(),
                'indice' => round($g->avg('indice_banca'), 3),
                'b1' => round($g->where('b1', true)->count() / max($g->count(), 1) * 100, 0),
                'b2' => round($g->where('b2', true)->count() / max($g->count(), 1) * 100, 0),
                'b3' => round($g->where('b3', true)->count() / max($g->count(), 1) * 100, 0),
            ]);

        return view('relatorios.composicao', compact(
            'inicio', 'fim', 'total',
            'atendimento', 'evolucaoIndice', 'correlacaoData', 'indicadoresPorLinha'
        ));
    }

    // ── Bloco 3: Bibliometria dos docentes (orientador vs avaliador) ──────────

    public function docentes(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        // IDs de docente que atuaram como ORIENTADOR no período
        $idsOrientadores = Orientacao::where('tipo', 'orientador')
            ->whereHas('trabalhoAcademico.bancas', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->pluck('docente_programa_vinculo_id')
            ->unique();

        // IDs de docente que atuaram como AVALIADOR no período
        $idsAvaliadores = BancaMembro::where('papel', 'avaliador')
            ->where('ativo', true)
            ->whereHas('banca', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->pluck('docente_programa_vinculo_id')
            ->unique();

        $dados = function ($ids, string $papel) {
            $snapshots = OrcidSnapshot::whereHas('docenteProfile.vinculosPrograma', fn ($q) =>
                $q->whereIn('id', $ids)
            )
                ->with('docenteProfile.user', 'docenteProfile.vinculosPrograma')
                ->get();

            // 11. Ranking por bibliometria
            $ranking = $snapshots->sortByDesc('indice_h')->map(fn ($s) => [
                'nome'          => $s->docenteProfile?->user?->name ?? '—',
                'h_index'       => $s->indice_h,
                'total_citacoes'=> $s->total_citacoes,
                'i10_index'     => $s->i10_index,
                'total_obras'   => $s->total_obras,
                'total_doi'     => $s->total_obras_com_doi,
                'status'        => $s->status_consulta,
            ])->values();

            // 12. Publicações por ano
            $docenteIds = $snapshots->pluck('docente_profile_id')->filter();
            $publicacoesPorAno = OrcidWork::whereIn('docente_profile_id', $docenteIds)
                ->whereNotNull('ano')
                ->groupBy('ano')
                ->select('ano', DB::raw('count(*) as total'))
                ->orderBy('ano')
                ->get()
                ->pluck('total', 'ano');

            // 13. Tipos de obra
            $tiposObra = OrcidWork::whereIn('docente_profile_id', $docenteIds)
                ->whereNotNull('tipo')
                ->groupBy('tipo')
                ->select('tipo', DB::raw('count(*) as total'))
                ->orderByDesc('total')
                ->get()
                ->pluck('total', 'tipo');

            // 14. DOI coverage
            $comOrcid   = $snapshots->where('status_consulta', 'sucesso')->count();
            $semOrcid   = $snapshots->where('status_consulta', 'erro')->count();
            $semSnapshot = \count($ids) - $snapshots->count();
            $cobertura  = ['com_orcid' => $comOrcid, 'com_erro' => $semOrcid, 'sem_snapshot' => $semSnapshot];

            $mediaDoi = $snapshots->filter(fn ($s) => $s->total_obras > 0)
                ->map(fn ($s) => round($s->total_obras_com_doi / $s->total_obras * 100, 1))
                ->avg();

            return compact('ranking', 'publicacoesPorAno', 'tiposObra', 'cobertura', 'mediaDoi');
        };

        $dadosOrientadores = $dados($idsOrientadores, 'orientador');
        $dadosAvaliadores  = $dados($idsAvaliadores, 'avaliador');

        return view('relatorios.docentes', compact(
            'inicio', 'fim',
            'dadosOrientadores', 'dadosAvaliadores'
        ));
    }

    // ── Bloco 4: Percurso do discente ────────────────────────────────────────

    public function percurso(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        $bancasComIngresso = Banca::with('trabalhoAcademico.discenteVinculo.linhaPesquisa')
            ->where('status', 'finalizada')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->whereHas('trabalhoAcademico.discenteVinculo', fn ($q) => $q->whereNotNull('data_ingresso'))
            ->get()
            ->filter(fn ($b) => $b->trabalhoAcademico?->discenteVinculo?->data_ingresso !== null);

        // 16. Histograma de tempo até qualificação e defesa
        $mesesQual   = $bancasComIngresso->where('tipo', 'qualificacao')
            ->map(fn ($b) => $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora))
            ->sort()->values();

        $mesesDefesa = $bancasComIngresso->where('tipo', 'defesa')
            ->map(fn ($b) => $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora))
            ->sort()->values();

        $histQual   = $this->histograma($mesesQual,   range(0, 36, 3));
        $histDefesa = $this->histograma($mesesDefesa, range(0, 48, 3));

        $eficienciaQual   = $mesesQual->count()   > 0 ? round($mesesQual->filter(fn ($m) => $m <= 18)->count()   / $mesesQual->count()   * 100, 1) : null;
        $eficienciaDefesa = $mesesDefesa->count() > 0 ? round($mesesDefesa->filter(fn ($m) => $m <= 24)->count() / $mesesDefesa->count() * 100, 1) : null;

        // 17. Funil de progresso (todos os trabalhos, não filtrado por período)
        $funil = TrabalhoAcademico::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // 18. Cohort analysis por ano de ingresso
        $cohort = DB::table('discente_programa_vinculos')
            ->select(
                DB::raw('YEAR(data_ingresso) as ano_ingresso'),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when EXISTS(select 1 from trabalhos_academicos ta join bancas b on b.trabalho_academico_id = ta.id where ta.discente_programa_vinculo_id = discente_programa_vinculos.id and b.tipo = "qualificacao" and b.status = "finalizada") then 1 else 0 end) as qualificados'),
                DB::raw('sum(case when EXISTS(select 1 from trabalhos_academicos ta join bancas b on b.trabalho_academico_id = ta.id where ta.discente_programa_vinculo_id = discente_programa_vinculos.id and b.tipo = "defesa" and b.status = "finalizada") then 1 else 0 end) as defendidos')
            )
            ->whereNotNull('data_ingresso')
            ->groupBy('ano_ingresso')
            ->orderBy('ano_ingresso')
            ->get();

        // 19. Trabalhos por linha de pesquisa
        $porLinha = TrabalhoAcademico::with('linhaPesquisa', 'discenteVinculo')
            ->get()
            ->groupBy(fn ($t) => $t->linhaPesquisa?->nome ?? 'Sem linha')
            ->map(fn ($g) => [
                'total'      => $g->count(),
                'mestrado'   => $g->where('nivel', 'mestrado')->count(),
                'doutorado'  => $g->where('nivel', 'doutorado')->count(),
                'concluidos' => $g->where('status', 'concluido')->count(),
            ])
            ->sortByDesc('total');

        return view('relatorios.percurso', compact(
            'inicio', 'fim',
            'mesesQual', 'mesesDefesa', 'histQual', 'histDefesa',
            'eficienciaQual', 'eficienciaDefesa',
            'funil', 'cohort', 'porLinha'
        ));
    }

    // ── Bloco 5: IEO ─────────────────────────────────────────────────────────

    public function orientacao(Request $request)
    {
        $this->guardaSec();

        $avaliacoes = OrientacaoAvaliacao::with([
            'docenteProgramaVinculo.docenteProfile.user',
            'banca.trabalhoAcademico.linhaPesquisa',
        ])
            ->whereNotNull('ieo_final')
            ->get();

        // 20. Ranking por orientador (min 3)
        $porOrientador = $avaliacoes->groupBy('docente_programa_vinculo_id')
            ->map(fn ($g) => [
                'nome'       => $g->first()->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—',
                'total'      => $g->count(),
                'ieo_medio'  => round($g->avg('ieo_final'), 1),
                'cat1' => round($g->avg('nota_cat1'), 1),
                'cat2' => round($g->avg('nota_cat2'), 1),
                'cat3' => round($g->avg('nota_cat3'), 1),
                'cat4' => round($g->avg('nota_cat4'), 1),
                'cat5' => round($g->avg('nota_cat5'), 1),
                'cat6' => round($g->avg('nota_cat6'), 1),
            ])
            ->filter(fn ($r) => $r['total'] >= 3)
            ->sortByDesc('ieo_medio')
            ->values();

        // 21. Médias das categorias no nível do programa
        $categoriaPrograma = [
            'Competência científica'        => round($avaliacoes->avg('nota_cat1'), 1),
            'Disponibilidade'               => round($avaliacoes->avg('nota_cat2'), 1),
            'Contribuição ao trabalho'      => round($avaliacoes->avg('nota_cat3'), 1),
            'Comunicação'                   => round($avaliacoes->avg('nota_cat4'), 1),
            'Suporte ao desenvolvimento'    => round($avaliacoes->avg('nota_cat5'), 1),
            'Ética e responsabilidade'      => round($avaliacoes->avg('nota_cat6'), 1),
        ];

        // 22. Evolução por orientador (série temporal simplificada)
        $evolucao = $avaliacoes->sortBy(fn ($a) => $a->banca->data_hora)
            ->groupBy('docente_programa_vinculo_id')
            ->filter(fn ($g) => $g->count() >= 3)
            ->map(fn ($g) => $g->map(fn ($a) => [
                'data' => $a->banca->data_hora?->format('Y-m'),
                'ieo'  => $a->ieo_final,
            ])->values());

        $distribuicao = [
            'Excelente' => 0, 'Muito bom' => 0,
            'Bom' => 0, 'Regular' => 0, 'Crítico' => 0,
        ];
        foreach ($avaliacoes as $av) {
            $distribuicao[$av->classificacaoIeo()] = ($distribuicao[$av->classificacaoIeo()] ?? 0) + 1;
        }

        $ieoGeral = round($avaliacoes->avg('ieo_final'), 1);

        return view('relatorios.orientacao', compact(
            'porOrientador', 'categoriaPrograma', 'evolucao', 'distribuicao', 'ieoGeral', 'avaliacoes'
        ));
    }

    // ── Bloco 6: Impacto da dissertação ─────────────────────────────────────

    public function impacto(Request $request)
    {
        $this->guardaSec();

        $impactos = TrabalhoImpacto::with('trabalhoAcademico.linhaPesquisa')->get();

        $dimensoes = [
            'cientifico'    => 'Científico',
            'metodologico'  => 'Metodológico',
            'tecnologico'   => 'Tecnológico',
            'social'        => 'Social',
            'ambiental'     => 'Ambiental',
            'formativo'     => 'Formativo',
            'institucional' => 'Institucional',
        ];

        // 23. Contagem por dimensão
        $contagemDimensao = collect($dimensoes)->map(fn ($label, $chave) =>
            $impactos->where("impacto_{$chave}_aplicavel", true)->count()
        )->sortDesc();

        // 24. Por linha de pesquisa
        $porLinha = $impactos
            ->groupBy(fn ($i) => $i->trabalhoAcademico?->linhaPesquisa?->nome ?? 'Sem linha')
            ->map(fn ($g) =>
                collect($dimensoes)->map(fn ($label, $chave) =>
                    $g->where("impacto_{$chave}_aplicavel", true)->count()
                )
            );

        $totalTrabalhos = $impactos->count();

        return view('relatorios.impacto', compact(
            'impactos', 'dimensoes', 'contagemDimensao', 'porLinha', 'totalTrabalhos'
        ));
    }

    // ── Bloco 7: Bibliometria dos discentes ──────────────────────────────────

    public function discentes(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        $indicadores = TrabalhoIndicador::with([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.linhaPesquisa',
            'trabalhoAcademico.bancas',
        ])->get();

        // 25. Tabela de bibliometria dos discentes
        $tabelaDiscentes = $indicadores->map(fn ($i) => [
            'nome'              => $i->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? '—',
            'linha'             => $i->trabalhoAcademico?->linhaPesquisa?->nome ?? '—',
            'publicacoes'       => $i->total_publicacoes_discente ?? 0,
            'com_doi'           => $i->total_com_doi ?? 0,
            'citacoes'          => $i->total_citacoes_crossref ?? 0,
            'coautoria_or'      => $i->coautoria_orientador ?? false,
            'coautoria_int'     => $i->coautoria_internacional ?? false,
            'bases_indexadas'   => $i->presenca_bases_indexadas ?? false,
            'nota_banca'        => $i->trabalhoAcademico?->bancas->where('status', 'finalizada')->avg('nota_media'),
        ])->sortByDesc('publicacoes');

        // 26. Dados para scatter: publicações × nota_banca
        $scatter = $tabelaDiscentes
            ->filter(fn ($r) => $r['nota_banca'] !== null && $r['publicacoes'] > 0)
            ->map(fn ($r) => ['x' => $r['publicacoes'], 'y' => round($r['nota_banca'], 2)])
            ->values();

        $resumo = [
            'com_publicacoes'        => $indicadores->filter(fn ($i) => ($i->total_publicacoes_discente ?? 0) > 0)->count(),
            'com_doi'                => $indicadores->filter(fn ($i) => ($i->total_com_doi ?? 0) > 0)->count(),
            'com_coautoria_or'       => $indicadores->where('coautoria_orientador', true)->count(),
            'com_coautoria_int'      => $indicadores->where('coautoria_internacional', true)->count(),
            'em_bases'               => $indicadores->where('presenca_bases_indexadas', true)->count(),
            'total'                  => $indicadores->count(),
        ];

        return view('relatorios.discentes', compact('tabelaDiscentes', 'scatter', 'resumo'));
    }

    // ── Bloco 8: Conformidade documental ─────────────────────────────────────

    public function documentos(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        $analises = DocumentoAnalise::with(['trabalhoDocumento.trabalhoAcademico.discenteVinculo.discenteProfile.user'])
            ->whereBetween('analisado_em', [$inicio, $fim])
            ->get();

        // 27. Taxa de conformidade
        $statusGeral = $analises->groupBy('status_geral')->map->count();

        $mediaConformidade = round($analises->avg('percentual_conformidade'), 1);

        // Itens com mais pendências
        $itensPendentes = DocumentoAnaliseItem::whereIn('status', ['pendente', 'atencao'])
            ->whereHas('analise', fn ($q) => $q->whereBetween('analisado_em', [$inicio, $fim]))
            ->select('nome_item', 'status', DB::raw('count(*) as total'))
            ->groupBy('nome_item', 'status')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        // 28. LaTeX e PDFs
        $latexTotal       = $analises->count();
        $latexUsando      = $analises->where('classe_latex_detectada', true)->count();
        $pdfNaoPesquisavel= $analises->where('pdf_pesquisavel', false)->count();
        $pdfProtegido     = $analises->where('pdf_protegido', true)->count();

        $motoresTex = $analises->whereNotNull('motor_tex')
            ->groupBy('motor_tex')
            ->map->count()
            ->sortDesc();

        return view('relatorios.documentos', compact(
            'inicio', 'fim', 'analises',
            'statusGeral', 'mediaConformidade', 'itensPendentes',
            'latexTotal', 'latexUsando', 'pdfNaoPesquisavel', 'pdfProtegido', 'motoresTex'
        ));
    }

    // ── Bloco 9: Autoavaliação (A01–A16) ────────────────────────────────────

    public function autoavaliacaoRelatorio(Request $request)
    {
        $this->guardaSec();

        $periodos = AutoavaliacaoPeriodo::with('indicadores')
            ->where('status', 'concluido')
            ->orderByDesc('data_inicio')
            ->get();

        // 29. Indicadores do período mais recente
        $periodoAtual    = $periodos->first();
        $indicadoresAtuais = $periodoAtual?->indicadores->keyBy('codigo') ?? collect();

        // 30. Comparação entre períodos (série histórica de indicadores numéricos)
        $historico = $periodos->mapWithKeys(fn ($p) => [
            $p->nome => $p->indicadores->filter(fn ($i) => $i->valor_numerico !== null)->keyBy('codigo'),
        ]);

        // 31. A12 expandido por linha (do período atual)
        $a12 = null;
        if ($periodoAtual && ($ind = $indicadoresAtuais->get('A12'))) {
            $a12 = json_decode($ind->valor_texto ?? '{}', true);
        }

        return view('relatorios.autoavaliacao', compact('periodos', 'periodoAtual', 'indicadoresAtuais', 'historico', 'a12'));
    }

    // ── Bloco 10: MABAC ──────────────────────────────────────────────────────

    public function mabacRelatorio(Request $request)
    {
        $this->guardaSec();

        $analises = MabacAnalise::with([
            'resultados.banca.trabalhoAcademico.linhaPesquisa',
            'resultados.banca.trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'barema',
        ])->orderByDesc('created_at')->get();

        // 32. Rankings por análise
        $rankings = $analises->map(fn ($a) => [
            'analise'    => $a,
            'resultados' => $a->resultados->sortBy('ranking')->values(),
        ]);

        // 33. Distribuição de escores entre análises
        $distribuicao = $analises->map(fn ($a) => [
            'nome'   => $a->nome,
            'scores' => $a->resultados->pluck('escore_final')->filter()->values(),
        ]);

        return view('relatorios.mabac', compact('analises', 'rankings', 'distribuicao'));
    }

    // ── Bloco 11: SWARA ──────────────────────────────────────────────────────

    public function swaraRelatorio(Request $request)
    {
        $this->guardaSec();

        $sessoes = SwaraSessao::with([
            'barema',
            'julgamentos.baremaItem.secao',
        ])->orderByDesc('created_at')->get();

        // 34. Pesos w_j por barema/sessão
        $pesoPorSessao = $sessoes->map(fn ($s) => [
            'sessao'    => $s,
            'itens'     => $s->julgamentos->sortBy('ordem')->map(fn ($j) => [
                'item'  => $j->baremaItem?->nome ?? '—',
                'secao' => $j->baremaItem?->secao?->nome ?? '—',
                'ordem' => $j->ordem,
                's_j'   => $j->s_j,
                'k_j'   => $j->k_j,
                'q_j'   => $j->q_j,
                'w_j'   => $j->w_j,
            ])->values(),
        ]);

        return view('relatorios.swara', compact('sessoes', 'pesoPorSessao'));
    }

    // ── Bloco 12: Corpo docente ───────────────────────────────────────────────

    public function corpoDocente(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        // 35. Participação como avaliador
        $participacao = BancaMembro::where('papel', 'avaliador')
            ->where('ativo', true)
            ->whereHas('banca', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->with('docenteProgramaVinculo.docenteProfile.user', 'banca')
            ->get()
            ->groupBy(fn ($m) => $m->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '—')
            ->map(fn ($g, $nome) => [
                'nome'  => $nome,
                'total' => $g->count(),
                'qualif'=> $g->filter(fn ($m) => $m->banca?->tipo === 'qualificacao')->count(),
                'defesa'=> $g->filter(fn ($m) => $m->banca?->tipo === 'defesa')->count(),
            ])
            ->sortByDesc('total')
            ->values();

        // 36. Diversidade institucional
        $diversidadeInst = BancaMembro::where('papel', 'avaliador')
            ->where('ativo', true)
            ->whereHas('banca', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->with('docenteProgramaVinculo.docenteProfile')
            ->get()
            ->groupBy(fn ($m) => strtoupper($m->docenteProgramaVinculo?->docenteProfile?->instituicao_sigla ?? 'N/I'))
            ->map->count()
            ->sortDesc();

        // 37. Diversidade de área de formação
        $diversidadeArea = BancaMembro::where('papel', 'avaliador')
            ->where('ativo', true)
            ->whereHas('banca', fn ($q) =>
                $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
            )
            ->with('docenteProgramaVinculo.docenteProfile')
            ->get()
            ->groupBy(fn ($m) => $m->docenteProgramaVinculo?->docenteProfile?->area_formacao ?? 'Não informada')
            ->map->count()
            ->sortDesc();

        return view('relatorios.corpo-docente', compact(
            'inicio', 'fim', 'participacao', 'diversidadeInst', 'diversidadeArea'
        ));
    }

    // ── Bloco 13: Dashboard executivo + alertas ──────────────────────────────

    public function dashboard(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);

        // 38. KPIs principais
        $totalBancas     = Banca::where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])->count();
        $taxaAprovacao   = Banca::where('status', 'finalizada')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->whereIn('resultado', ['aprovado', 'aprovado_modificacoes'])
            ->count();
        $taxaAprovacaoPct = $totalBancas > 0 ? round($taxaAprovacao / $totalBancas * 100, 1) : null;
        $notaMedia       = round(Banca::where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])->avg('nota_media'), 2);
        $indiceComposicao = round(BancaIndicador::whereHas('banca', fn ($q) =>
            $q->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
        )->avg('indice_banca'), 3);
        $conformidade    = round(DocumentoAnalise::whereBetween('analisado_em', [$inicio, $fim])->avg('percentual_conformidade'), 1);
        $ieoMedio        = round(OrientacaoAvaliacao::whereNotNull('ieo_final')->avg('ieo_final'), 1);

        // Tempo médio ao percurso
        $tempoMedioDefesa = BancaMembro::distinct()
            ->whereHas('banca', fn ($q) =>
                $q->where('tipo', 'defesa')->where('status', 'finalizada')->whereBetween('data_hora', [$inicio, $fim])
                  ->whereHas('trabalhoAcademico.discenteVinculo', fn ($q2) => $q2->whereNotNull('data_ingresso'))
            )
            ->get(); // Calculated separately in percurso, simplified here

        $mesesDefesaMedia = Banca::with('trabalhoAcademico.discenteVinculo')
            ->where('tipo', 'defesa')
            ->where('status', 'finalizada')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->get()
            ->filter(fn ($b) => $b->trabalhoAcademico?->discenteVinculo?->data_ingresso)
            ->map(fn ($b) => $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora))
            ->avg();

        $kpis = compact(
            'totalBancas', 'taxaAprovacaoPct', 'notaMedia',
            'indiceComposicao', 'conformidade', 'ieoMedio', 'mesesDefesaMedia'
        );

        // 40. Alertas operacionais
        $alertas = $this->calcularAlertas($inicio, $fim);

        return view('relatorios.dashboard', compact('inicio', 'fim', 'kpis', 'alertas'));
    }

    // ── Bloco 39: Exportação CSV ─────────────────────────────────────────────

    public function exportar(Request $request)
    {
        $this->guardaSec();
        [$inicio, $fim] = $this->periodo($request);
        $tipo = $request->get('tipo', 'bancas');

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"katu_{$tipo}_{$inicio->format('Y-m-d')}.csv\"",
        ];

        $callback = match ($tipo) {
            'bancas'     => fn () => $this->exportarBancas($inicio, $fim),
            'docentes'   => fn () => $this->exportarDocentes(),
            'percurso'   => fn () => $this->exportarPercurso($inicio, $fim),
            'indicadores'=> fn () => $this->exportarIndicadores(),
            default      => fn () => $this->exportarBancas($inicio, $fim),
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    private function histograma($valores, array $bins): array
    {
        $hist = [];
        $valoresArr = $valores->toArray();
        for ($i = 0; $i < \count($bins) - 1; $i++) {
            $label = "{$bins[$i]}–{$bins[$i+1]}m";
            $hist[$label] = \count(array_filter($valoresArr, fn ($v) => $v >= $bins[$i] && $v < $bins[$i + 1]));
        }
        return $hist;
    }

    private function calcularAlertas(Carbon $inicio, Carbon $fim): array
    {
        $alertas = [];

        // Bancas sem indicadores calculados
        $semIndicadores = Banca::where('status', 'finalizada')
            ->doesntHave('indicadores')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->count();
        if ($semIndicadores > 0) {
            $alertas[] = [
                'tipo'    => 'warning',
                'icone'   => 'bi-exclamation-triangle-fill',
                'mensagem'=> "{$semIndicadores} banca(s) finalizada(s) sem indicadores B1–B8 calculados.",
                'link'    => route('bancas.index'),
            ];
        }

        // Discentes com prazo vencido
        $hoje = Carbon::today();
        $atrasados = TrabalhoAcademico::whereNotNull('data_prevista_defesa')
            ->where('data_prevista_defesa', '<', $hoje)
            ->where('status', '!=', 'concluido')
            ->count();
        if ($atrasados > 0) {
            $alertas[] = [
                'tipo'    => 'danger',
                'icone'   => 'bi-calendar-x-fill',
                'mensagem'=> "{$atrasados} trabalho(s) com data prevista de defesa vencida.",
                'link'    => route('orientacoes.index'),
            ];
        }

        // Docentes com ORCID com erro
        $orcidErro = OrcidSnapshot::where('status_consulta', 'erro')->count();
        if ($orcidErro > 0) {
            $alertas[] = [
                'tipo'    => 'warning',
                'icone'   => 'bi-person-x-fill',
                'mensagem'=> "{$orcidErro} docente(s) com erro na sincronização ORCID/OpenAlex.",
                'link'    => route('usuarios.index'),
            ];
        }

        // Documentos com pendências não resolvidas
        $docsPendentes = DocumentoAnalise::where('status_geral', 'com_pendencias')
            ->whereBetween('analisado_em', [$inicio, $fim])
            ->count();
        if ($docsPendentes > 0) {
            $alertas[] = [
                'tipo'    => 'info',
                'icone'   => 'bi-file-earmark-x-fill',
                'mensagem'=> "{$docsPendentes} documento(s) com pendências detectadas no período.",
                'link'    => '#',
            ];
        }

        return $alertas;
    }

    private function exportarBancas(Carbon $inicio, Carbon $fim): void
    {
        $fh = fopen('php://output', 'w');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($fh, ['Tipo', 'Status', 'Resultado', 'Nota Média', 'Data', 'Linha de Pesquisa']);

        Banca::with('trabalhoAcademico.linhaPesquisa')
            ->where('status', 'finalizada')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->chunk(100, function ($bancas) use ($fh) {
                foreach ($bancas as $b) {
                    fputcsv($fh, [
                        $b->tipo, $b->status, $b->resultado ?? '',
                        $b->nota_media ?? '', $b->data_hora?->format('d/m/Y') ?? '',
                        $b->trabalhoAcademico?->linhaPesquisa?->nome ?? '',
                    ]);
                }
            });

        fclose($fh);
    }

    private function exportarDocentes(): void
    {
        $fh = fopen('php://output', 'w');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fh, ['Nome', 'Índice H', 'Citações', 'i10-index', 'Obras ORCID', 'Obras com DOI', 'Status ORCID']);

        OrcidSnapshot::with('docenteProfile.user')->chunk(100, function ($snaps) use ($fh) {
            foreach ($snaps as $s) {
                fputcsv($fh, [
                    $s->docenteProfile?->user?->name ?? '',
                    $s->indice_h ?? '', $s->total_citacoes ?? '',
                    $s->i10_index ?? '', $s->total_obras ?? '',
                    $s->total_obras_com_doi ?? '', $s->status_consulta,
                ]);
            }
        });

        fclose($fh);
    }

    private function exportarPercurso(Carbon $inicio, Carbon $fim): void
    {
        $fh = fopen('php://output', 'w');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fh, ['Discente', 'Ingresso', 'Tipo Banca', 'Data Banca', 'Meses', 'Resultado']);

        Banca::with('trabalhoAcademico.discenteVinculo.discenteProfile.user')
            ->where('status', 'finalizada')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->chunk(100, function ($bancas) use ($fh) {
                foreach ($bancas as $b) {
                    $vinculo = $b->trabalhoAcademico?->discenteVinculo;
                    $ingresso = $vinculo?->data_ingresso;
                    fputcsv($fh, [
                        $vinculo?->discenteProfile?->user?->name ?? '',
                        $ingresso?->format('d/m/Y') ?? '',
                        $b->tipo,
                        $b->data_hora?->format('d/m/Y') ?? '',
                        $ingresso ? $ingresso->diffInMonths($b->data_hora) : '',
                        $b->resultado ?? '',
                    ]);
                }
            });

        fclose($fh);
    }

    private function exportarIndicadores(): void
    {
        $fh = fopen('php://output', 'w');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fh, ['Período', 'Código', 'Indicador', 'Valor Numérico', 'Calculado em']);

        AutoavaliacaoPeriodo::with('indicadores')->where('status', 'concluido')
            ->chunk(50, function ($periodos) use ($fh) {
                foreach ($periodos as $p) {
                    foreach ($p->indicadores as $ind) {
                        fputcsv($fh, [
                            $p->nome, $ind->codigo, $ind->nome,
                            $ind->valor_numerico ?? '', $ind->calculado_em?->format('d/m/Y H:i') ?? '',
                        ]);
                    }
                }
            });

        fclose($fh);
    }
}
