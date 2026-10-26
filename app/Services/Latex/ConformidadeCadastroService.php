<?php

namespace App\Services\Latex;

use App\Models\Banca;
use App\Models\Orientacao;
use App\Models\TrabalhoAcademico;
use App\Support\NomePessoa;
use Carbon\Carbon;

/**
 * Compara os elementos vitais extraídos do .tex com o cadastro do app (D3, D10, regra 7).
 * Ausência de elemento obrigatório bloqueia; divergência nunca bloqueia.
 */
class ConformidadeCadastroService
{
    private const ELEMENTOS_PT = [ElementosVitais::TITULO_PT, ElementosVitais::RESUMO_PT, ElementosVitais::PALAVRAS_CHAVE_PT];

    private const ELEMENTOS_EN = [ElementosVitais::TITULO_EN, ElementosVitais::ABSTRACT_EN, ElementosVitais::PALAVRAS_CHAVE_EN];

    private const MESES = [
        'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    ];

    /**
     * @param  array<string, string|list<string>|null>  $elementos  saída de ExtratorElementosLatexService::extrair
     * @return list<array{codigo: string, rotulo: string, status: string, valor_tex: ?string, valor_sistema: ?string, bloqueia: bool, diferencas?: array}>
     */
    public function conferir(array $elementos, TrabalhoAcademico $trabalho, ?Banca $banca, string $tipoDocumento, string $idioma): array
    {
        $coorientadores = $this->nomesOrientacao($trabalho, 'coorientador');
        $itens = [];

        foreach (ElementosVitais::todos() as $codigo) {
            $valor = $this->limpar($elementos[$codigo] ?? null, in_array($codigo, ElementosVitais::LISTAS, true));
            $obrigatorio = $this->obrigatorio($codigo, $tipoDocumento, $idioma, $coorientadores !== []);
            $item = [
                'codigo' => $codigo,
                'rotulo' => ucfirst(ElementosVitais::descricao($codigo)),
                'status' => 'ok',
                'valor_tex' => is_array($valor) ? implode('; ', $valor) : $valor,
                'valor_sistema' => null,
                'bloqueia' => false,
            ];

            $presente = is_array($valor) ? $valor !== [] : $valor !== null;
            if (! $presente) {
                // Coorientador sem coorientação no app: nada a exigir nem a comparar.
                if ($codigo === ElementosVitais::COORIENTADORES && ! $obrigatorio) {
                    $itens[] = $item;

                    continue;
                }
                $item['status'] = $obrigatorio ? 'ausente' : 'aviso';
                $item['bloqueia'] = $obrigatorio;
                $item['valor_sistema'] = $this->valorSistemaAusente($codigo, $trabalho, $banca, $coorientadores);
                $itens[] = $item;

                continue;
            }

            $itens[] = $this->comparar($item, $codigo, $valor, $trabalho, $banca, $coorientadores);
        }

        return $itens;
    }

    /** @param  list<array{bloqueia: bool}>  $itens */
    public function bloqueia(array $itens): bool
    {
        foreach ($itens as $item) {
            if ($item['bloqueia']) {
                return true;
            }
        }

        return false;
    }

    private function obrigatorio(string $codigo, string $tipoDocumento, string $idioma, bool $temCoorientador): bool
    {
        if ($codigo === ElementosVitais::ORIENTADOR || $codigo === ElementosVitais::MEMBROS_BANCA) {
            return true;
        }
        if ($codigo === ElementosVitais::COORIENTADORES) {
            return $temCoorientador;
        }
        if ($idioma === 'en') {
            // D10: em trabalho em inglês os EN são obrigatórios e os PT viram aviso.
            return in_array($codigo, self::ELEMENTOS_EN, true);
        }
        if (in_array($codigo, self::ELEMENTOS_PT, true)) {
            return true;
        }
        // Abstract e keywords EN; título EN nunca bloqueia. Na qualificação, EN é só aviso.
        return $tipoDocumento !== 'qualificacao'
            && in_array($codigo, [ElementosVitais::ABSTRACT_EN, ElementosVitais::PALAVRAS_CHAVE_EN], true);
    }

    private function comparar(array $item, string $codigo, string|array $valor, TrabalhoAcademico $trabalho, ?Banca $banca, array $coorientadores): array
    {
        $marcar = function (?string $sistema, ?bool $igual) use (&$item): void {
            $item['valor_sistema'] = $sistema;
            if ($igual === false) {
                $item['status'] = 'divergente';
            }
        };

        switch ($codigo) {
            case ElementosVitais::TITULO_PT:
                $sistema = $trabalho->titulo;
                $marcar($sistema, $sistema === null || $sistema === '' ? null : NomePessoa::simplificar($valor) === NomePessoa::simplificar($sistema));
                break;
            case ElementosVitais::AUTOR:
                $sistema = $trabalho->discenteVinculo?->discenteProfile?->user?->name;
                $marcar($sistema, $sistema ? NomePessoa::equivalentes($valor, $sistema) : null);
                break;
            case ElementosVitais::ORIENTADOR:
                $sistema = $this->nomesOrientacao($trabalho, 'orientador')[0] ?? null;
                $marcar($sistema, $sistema ? NomePessoa::equivalentes($valor, $sistema) : null);
                break;
            case ElementosVitais::COORIENTADORES:
                return $this->compararListas($item, $valor, $coorientadores);
            case ElementosVitais::MEMBROS_BANCA:
                if ($banca === null) {
                    return $item;
                }
                $nomes = $banca->avaliadores()->with('docenteProgramaVinculo.docenteProfile.user')->get()
                    ->map(fn ($m) => $m->docenteProgramaVinculo?->docenteProfile?->user?->name)
                    ->filter()->values()->all();

                return $this->compararListas($item, $valor, $nomes);
            case ElementosVitais::DATA_DEFESA:
                if ($banca?->data_hora === null) {
                    return $item;
                }
                $sistema = $banca->data_hora->format('d/m/Y');
                $data = $this->interpretarData($valor);
                $item['valor_sistema'] = $sistema;
                if ($data === null) {
                    $item['status'] = 'aviso';
                } elseif ($data !== $sistema) {
                    $item['status'] = 'divergente';
                }
                break;
            case ElementosVitais::LOCAL_DEFESA:
                $sistema = $banca?->local;
                if ($sistema === null || trim($sistema) === '') {
                    return $item;
                }
                $a = NomePessoa::simplificar($valor);
                $b = NomePessoa::simplificar($sistema);
                $marcar($sistema, $a === $b || str_contains($a, $b) || str_contains($b, $a));
                break;
        }

        return $item;
    }

    /** @param  list<string>  $nomesSistema */
    private function compararListas(array $item, array $nomesTex, array $nomesSistema): array
    {
        $item['valor_sistema'] = implode('; ', $nomesSistema);
        $somenteTex = array_values(array_filter($nomesTex, fn ($n) => ! $this->contem($nomesSistema, $n)));
        $somenteSistema = array_values(array_filter($nomesSistema, fn ($n) => ! $this->contem($nomesTex, $n)));
        $item['diferencas'] = ['somente_tex' => $somenteTex, 'somente_sistema' => $somenteSistema];
        if ($somenteTex !== [] || $somenteSistema !== []) {
            $item['status'] = 'divergente';
        }

        return $item;
    }

    private function contem(array $nomes, string $nome): bool
    {
        foreach ($nomes as $outro) {
            if (NomePessoa::equivalentes($outro, $nome)) {
                return true;
            }
        }

        return false;
    }

    private function valorSistemaAusente(string $codigo, TrabalhoAcademico $trabalho, ?Banca $banca, array $coorientadores): ?string
    {
        return match ($codigo) {
            ElementosVitais::ORIENTADOR => $this->nomesOrientacao($trabalho, 'orientador')[0] ?? null,
            ElementosVitais::COORIENTADORES => implode('; ', $coorientadores) ?: null,
            ElementosVitais::TITULO_PT => $trabalho->titulo,
            default => null,
        };
    }

    /** @return list<string> nomes das orientações ativas do tipo, em ordem. */
    private function nomesOrientacao(TrabalhoAcademico $trabalho, string $tipo): array
    {
        return Orientacao::query()
            ->where('trabalho_academico_id', $trabalho->id)
            ->where('tipo', $tipo)->where('ativo', true)
            ->orderBy('ordem')->orderBy('id')
            ->with('docenteProgramaVinculo.docenteProfile.user')
            ->get()
            ->map(fn (Orientacao $o) => $o->docenteProgramaVinculo?->docenteProfile?->user?->name)
            ->filter()->values()->all();
    }

    /** @return string|list<string>|null texto limpo; null quando vazio. */
    private function limpar(mixed $valor, bool $lista): string|array|null
    {
        if ($lista) {
            $itens = is_array($valor) ? $valor : (is_string($valor) ? [$valor] : []);

            return array_values(array_filter(array_map(fn ($v) => NomePessoa::limparLatex((string) $v), $itens), fn ($v) => $v !== ''));
        }
        if (is_array($valor)) {
            $valor = implode('; ', $valor);
        }
        $limpo = is_string($valor) ? NomePessoa::limparLatex($valor) : '';

        return $limpo === '' ? null : $limpo;
    }

    /** Converte "12/03/2026", "2026-03-12" ou "12 de março de 2026" em d/m/Y; null se não entender. */
    private function interpretarData(string $texto): ?string
    {
        if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $texto, $m)) {
            return $this->formatar((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('#(\d{4})-(\d{1,2})-(\d{1,2})#', $texto, $m)) {
            return $this->formatar((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $simples = NomePessoa::simplificar($texto);
        if (preg_match('/(\d{1,2})(?: de)? ([a-z]+)(?: de)? (\d{4})/', $simples, $m) && isset(self::MESES[$m[2]])) {
            return $this->formatar((int) $m[3], self::MESES[$m[2]], (int) $m[1]);
        }

        return null;
    }

    private function formatar(int $ano, int $mes, int $dia): ?string
    {
        return checkdate($mes, $dia, $ano) ? Carbon::create($ano, $mes, $dia)->format('d/m/Y') : null;
    }
}
