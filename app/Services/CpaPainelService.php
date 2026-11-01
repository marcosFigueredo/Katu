<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\DimensaoQualidade;
use App\Models\PeriodoQualidade;
use App\Models\User;

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

        return [
            'panorama' => $this->panorama($programa, $trabalhos),
            'evolucao' => $this->evolucao($trabalhos),
            'recortes' => $this->recortes($trabalhos),
            'pontos' => [],
            'confiabilidade' => [],
            'projetos' => [],
            'conflito' => 0,
        ];
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
