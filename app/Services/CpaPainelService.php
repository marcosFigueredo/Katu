<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\DimensaoQualidade;
use App\Models\Orientacao;
use App\Models\PeriodoQualidade;
use App\Models\ProjetoPesquisa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Painel da CPA: só dados gerais e calculados do quadriênio — nunca um docente ou um trabalho em si.
 * Mediana e quartis em tudo; trabalhos reprovados por plágio já ficam de fora no IndiceQualidadeService.
 */
class CpaPainelService
{
    public const NIVEIS = ['mestrado' => 'Mestrado', 'doutorado' => 'Doutorado'];

    public function __construct(private readonly IndiceQualidadeService $indices)
    {
    }

    /** $filtros: ['linhas' => int[], 'nivel' => ?string]. */
    public function montar(PeriodoQualidade $periodo, array $filtros, User $membro): array
    {
        $linhas = array_map('intval', array_filter((array) ($filtros['linhas'] ?? [])));
        $nivel = $filtros['nivel'] ?? null;
        $filtro = fn (Banca $b) => ($linhas === [] || in_array((int) $b->trabalhoAcademico?->linha_pesquisa_id, $linhas, true))
            && (! $nivel || $b->trabalhoAcademico?->nivel === $nivel);

        $programa = $this->indices->indicePrograma($periodo, $filtro);
        $trabalhos = array_values(array_filter($programa['trabalhos'], fn ($t) => $t['indice'] !== null));

        $notas = $this->notasDaBanca(array_map(fn ($t) => $t['banca']->id, $trabalhos));

        return [
            'panorama' => $this->panorama($programa, $trabalhos),
            'evolucao' => $this->evolucao($trabalhos),
            'recortes' => $this->recortes($trabalhos),
            'pontos' => $this->pontos($notas),
            'confiabilidade' => $this->confiabilidade($notas, $trabalhos),
            'projetos' => $this->projetos($periodo),
            'conflito' => $this->conflito($trabalhos, $membro),
        ];
    }

    /** Notas normalizadas (0–10) das avaliações enviadas pelos avaliadores ativos, padrão e sigilosas. */
    private function notasDaBanca(array $bancaIds): array
    {
        if ($bancaIds === []) {
            return [];
        }

        return DB::table('banca_avaliacao_notas as n')
            ->join('banca_avaliacoes as a', 'a.id', '=', 'n.banca_avaliacao_id')
            ->join('banca_membros as m', 'm.id', '=', 'a.banca_membro_id')
            ->join('barema_itens as i', 'i.id', '=', 'n.barema_item_id')
            ->join('baremas as b', 'b.id', '=', 'a.barema_id')
            ->join('dimensoes_qualidade as d', 'd.id', '=', 'n.dimensao_qualidade_id')
            ->whereIn('a.banca_id', $bancaIds)
            ->where('a.status', 'enviada')
            ->where('m.ativo', true)
            ->where('m.papel', 'avaliador')
            ->whereNotNull('n.nota')
            ->where('i.nota_maxima', '>', 0)
            ->orderBy('n.id')
            ->get(['a.banca_id', 'a.banca_membro_id', 'n.barema_item_id', 'i.nome as pergunta', 'd.codigo', 'd.nome as dimensao', 'b.tipo', 'n.nota', 'i.nota_maxima'])
            ->map(fn ($l) => (array) $l + ['valor' => (float) $l->nota / (float) $l->nota_maxima * 10])
            ->all();
    }

    /** As 5 perguntas com maior e as 5 com menor mediana no conjunto (mínimo de 2 notas por pergunta). */
    private function pontos(array $notas): array
    {
        $porPergunta = [];
        foreach ($notas as $n) {
            $porPergunta[$n['barema_item_id']]['pergunta'] = $n['pergunta'];
            $porPergunta[$n['barema_item_id']]['dimensao'] = $n['dimensao'];
            $porPergunta[$n['barema_item_id']]['valores'][] = $n['valor'];
        }

        $linhas = [];
        foreach ($porPergunta as $p) {
            if (count($p['valores']) >= 2) {
                $linhas[] = ['pergunta' => $p['pergunta'], 'dimensao' => $p['dimensao'], 'mediana' => self::quartis($p['valores'])['mediana'], 'n' => count($p['valores'])];
            }
        }
        usort($linhas, fn ($a, $b) => $b['mediana'] <=> $a['mediana']);

        return ['fortes' => array_slice($linhas, 0, 5), 'fracos' => array_slice(array_reverse($linhas), 0, 5)];
    }

    /** Concordância entre avaliadores, aberto × sigiloso e espelho/orientador × banca (medianas, sem identificar ninguém). */
    private function confiabilidade(array $notas, array $trabalhos): array
    {
        $porBancaItem = [];
        $porTipo = [];
        foreach ($notas as $n) {
            $porBancaItem[$n['banca_id'].'-'.$n['barema_item_id']][] = $n['valor'];
            $porTipo[$n['codigo']][str_ends_with($n['tipo'], '_confidencial') ? 'confidencial' : 'aberto'][] = $n['valor'];
        }
        $amplitudes = [];
        foreach ($porBancaItem as $valores) {
            if (count($valores) >= 2) {
                $amplitudes[] = max($valores) - min($valores);
            }
        }

        $abertoConfidencial = [];
        foreach ($porTipo as $codigo => $tipos) {
            $abertoConfidencial[$codigo] = [
                'aberto' => isset($tipos['aberto']) ? self::quartis($tipos['aberto'])['mediana'] : null,
                'confidencial' => isset($tipos['confidencial']) ? self::quartis($tipos['confidencial'])['mediana'] : null,
            ];
        }

        $questionarios = $this->indices->questionariosPorBanca(array_map(fn ($t) => $t['banca']->id, $trabalhos));
        $diferencas = ['espelho' => [], 'autoavaliacao_orientador' => []];
        foreach ($trabalhos as $t) {
            foreach ($diferencas as $fonte => $_) {
                foreach ($questionarios[$fonte][$t['banca']->id] ?? [] as $codigo => $nota) {
                    if (($t['dimensoes'][$codigo] ?? null) !== null) {
                        $diferencas[$fonte][$codigo][] = $nota - $t['dimensoes'][$codigo];
                    }
                }
            }
        }
        $mediana = fn (array $porCodigo) => array_map(fn ($valores) => self::quartis($valores)['mediana'], $porCodigo);

        return [
            'concordancia' => [
                'mediana_amplitude' => self::quartis($amplitudes)['mediana'] ?? null,
                'percentual_ate_2' => $amplitudes === [] ? null : round(100 * count(array_filter($amplitudes, fn ($a) => $a <= 2)) / count($amplitudes), 1),
                'n' => count($amplitudes),
            ],
            'aberto_confidencial' => $abertoConfidencial,
            'espelho_banca' => $mediana($diferencas['espelho']),
            'orientador_banca' => $mediana($diferencas['autoavaliacao_orientador']),
        ];
    }

    /** Projetos de pesquisa do programa e os objetivos indicados pelos estudantes — sem nome de estudante nem de docente. */
    private function projetos(PeriodoQualidade $periodo): array
    {
        return ProjetoPesquisa::whereHas('docenteProgramaVinculo', fn ($q) => $q->where('programa_id', $periodo->programa_id))
            ->with(['vinculos' => fn ($q) => $q->whereNotNull('objetivo')->orderBy('objetivo_informado_em')])
            ->orderBy('titulo')
            ->get()
            ->map(fn (ProjetoPesquisa $p) => [
                'titulo' => $p->titulo,
                'descricao' => $p->descricao,
                'situacao' => ProjetoPesquisa::SITUACOES[$p->situacao] ?? $p->situacao,
                'objetivos' => $p->vinculos->map(fn ($v) => ['objetivo' => $v->objetivo, 'informado_em' => $v->objetivo_informado_em, 'vigente' => $v->ativo])->all(),
            ])
            ->all();
    }

    /** Trabalhos do recorte em que o membro participou: orientação (vigente ou passada) ou membro de alguma banca do trabalho. */
    private function conflito(array $trabalhos, User $membro): int
    {
        $vinculos = $membro->docenteProfile?->vinculosPrograma()->pluck('id') ?? collect();
        if ($vinculos->isEmpty() || $trabalhos === []) {
            return 0;
        }
        $trabalhoIds = array_map(fn ($t) => $t['banca']->trabalho_academico_id, $trabalhos);

        $porOrientacao = Orientacao::whereIn('trabalho_academico_id', $trabalhoIds)->whereIn('docente_programa_vinculo_id', $vinculos)->pluck('trabalho_academico_id');
        $porBanca = BancaMembro::whereIn('docente_programa_vinculo_id', $vinculos)
            ->join('bancas', 'bancas.id', '=', 'banca_membros.banca_id')
            ->whereIn('bancas.trabalho_academico_id', $trabalhoIds)
            ->pluck('bancas.trabalho_academico_id');

        return $porOrientacao->merge($porBanca)->unique()->count();
    }

    private function panorama(array $programa, array $trabalhos): array
    {
        $resumo = [];
        foreach (array_column(DimensaoQualidade::PADRAO, 'codigo') as $codigo) {
            $resumo[$codigo] = self::quartis(array_values(array_filter(array_map(fn ($t) => $t['dimensoes'][$codigo] ?? null, $trabalhos), fn ($n) => $n !== null)));
        }

        return [
            'n' => count($trabalhos),
            'indice' => $programa['indice'],
            'classe' => $programa['classe'],
            'intervalo' => $programa['intervalo'],
            'fronteira' => $programa['fronteira'],
            'distribuicao' => $programa['distribuicao'],
            'dimensoes' => $programa['dimensoes'],
            'fontes' => $programa['fontes'],
            'resumo' => $resumo,
        ];
    }

    /** [ano => ['n', 'indice' (mediana), 'dimensoes' => [codigo => mediana]]]. */
    private function evolucao(array $trabalhos): array
    {
        $porAno = [];
        foreach ($trabalhos as $t) {
            $porAno[(int) $t['banca']->data_hora->format('Y')][] = $t;
        }
        ksort($porAno);

        $resultado = [];
        foreach ($porAno as $ano => $doAno) {
            $dimensoes = [];
            foreach (array_column(DimensaoQualidade::PADRAO, 'codigo') as $codigo) {
                $valores = array_values(array_filter(array_map(fn ($t) => $t['dimensoes'][$codigo] ?? null, $doAno), fn ($n) => $n !== null));
                $dimensoes[$codigo] = self::quartis($valores)['mediana'] ?? null;
            }
            $resultado[$ano] = [
                'n' => count($doAno),
                'indice' => self::quartis(array_column($doAno, 'indice'))['mediana'] ?? null,
                'dimensoes' => $dimensoes,
            ];
        }

        return $resultado;
    }

    /** [tipo => [rótulo => ['n', 'mediana', 'q1', 'q3', 'distribuicao']]] para linha, área, nível e bolsa. */
    private function recortes(array $trabalhos): array
    {
        $grupos = ['linha' => [], 'area' => [], 'nivel' => [], 'bolsa' => []];
        foreach ($trabalhos as $t) {
            $trabalho = $t['banca']->trabalhoAcademico;
            $linha = $trabalho?->linhaPesquisa;
            $grupos['linha'][$linha?->nome ?? 'Sem linha'][] = $t;
            $grupos['area'][$linha?->areaConcentracao?->nome ?? 'Sem área'][] = $t;
            $grupos['nivel'][self::NIVEIS[$trabalho?->nivel] ?? 'Mestrado'][] = $t;
            $grupos['bolsa'][$trabalho?->discenteVinculo?->bolsas->isNotEmpty() ? 'Bolsista' : 'Não bolsista'][] = $t;
        }

        $resultado = [];
        foreach ($grupos as $tipo => $porRotulo) {
            ksort($porRotulo);
            foreach ($porRotulo as $rotulo => $doGrupo) {
                $q = self::quartis(array_column($doGrupo, 'indice'));
                $distribuicao = array_fill_keys(array_keys(IndiceQualidadeService::CLASSES), 0);
                foreach ($doGrupo as $t) {
                    if ($t['classe'] !== null) {
                        $distribuicao[$t['classe']]++;
                    }
                }
                $resultado[$tipo][$rotulo] = ['n' => count($doGrupo), 'mediana' => $q['mediana'] ?? null, 'q1' => $q['q1'] ?? null, 'q3' => $q['q3'] ?? null, 'distribuicao' => $distribuicao];
            }
        }

        return $resultado;
    }

    /** Mínimo, quartis (interpolação linear, tipo 7), máximo e n; null sem valores. */
    public static function quartis(array $valores): ?array
    {
        if ($valores === []) {
            return null;
        }
        $valores = array_map('floatval', $valores);
        sort($valores);
        $n = count($valores);
        $quantil = function (float $p) use ($valores, $n): float {
            $h = ($n - 1) * $p;
            $baixo = (int) floor($h);

            return round($valores[$baixo] + ($h - $baixo) * (($valores[min($baixo + 1, $n - 1)]) - $valores[$baixo]), 2);
        };

        return ['min' => $valores[0], 'q1' => $quantil(0.25), 'mediana' => $quantil(0.5), 'q3' => $quantil(0.75), 'max' => $valores[$n - 1], 'n' => $n];
    }
}
