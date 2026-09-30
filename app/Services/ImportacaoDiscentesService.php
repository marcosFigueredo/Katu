<?php

namespace App\Services;

use App\Models\DiscenteProgramaVinculo;
use App\Models\LinhaPesquisa;
use App\Models\Programa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportacaoDiscentesService
{
    public const OBRIGATORIAS = ['nome', 'email', 'matricula', 'curso', 'data_ingresso', 'lattes'];
    public const OPCIONAIS = ['linha_pesquisa', 'telefone'];
    public const MAX_LINHAS = 1000;

    public function analisar(string $conteudo, Programa $programa): array
    {
        $conteudo = $this->paraUtf8($conteudo);
        $registros = array_values(array_filter(
            preg_split('/\r\n|\n|\r/', $conteudo),
            fn ($l) => trim(str_replace([';', ','], '', $l)) !== ''
        ));

        if (count($registros) < 2) {
            return ['erro_arquivo' => 'O arquivo não tem linhas de discentes.', 'linhas' => []];
        }

        $separador = substr_count($registros[0], ';') >= substr_count($registros[0], ',') ? ';' : ',';
        $cabecalho = array_map(fn ($c) => Str::of($c)->ascii()->lower()->trim()->replace(' ', '_')->value(),
            str_getcsv($registros[0], $separador));
        $faltando = array_diff(self::OBRIGATORIAS, $cabecalho);
        if ($faltando) {
            return ['erro_arquivo' => 'Colunas obrigatórias ausentes: ' . implode(', ', $faltando) . '.', 'linhas' => []];
        }
        if (count($registros) - 1 > self::MAX_LINHAS) {
            return ['erro_arquivo' => 'O arquivo excede ' . self::MAX_LINHAS . ' linhas de discentes.', 'linhas' => []];
        }

        $cursos = $programa->cursos()->where('ativo', true)->get()->keyBy(fn ($c) => $c->identificador);
        $linhasPesquisa = LinhaPesquisa::where('ativo', true)
            ->whereHas('areaConcentracao', fn ($q) => $q->where('programa_id', $programa->id))
            ->get()
            ->keyBy(fn ($l) => $this->chave($l->nome));
        $emailsExistentes = User::pluck('email')->map(fn ($e) => mb_strtolower($e))->flip();
        $matriculasExistentes = DiscenteProgramaVinculo::where('programa_id', $programa->id)
            ->whereNotNull('matricula')->pluck('matricula')->flip();

        $vistosEmail = [];
        $vistosMatricula = [];
        $linhas = [];

        foreach (array_slice($registros, 1) as $i => $registro) {
            $valores = array_map('trim', str_getcsv($registro, $separador));
            $bruto = [];
            foreach ($cabecalho as $pos => $coluna) {
                $bruto[$coluna] = $valores[$pos] ?? '';
            }

            $dados = [
                'nome' => $bruto['nome'],
                'email' => mb_strtolower($bruto['email']),
                'matricula' => $bruto['matricula'],
                'curso' => mb_strtolower($bruto['curso']),
                'data_ingresso_original' => $bruto['data_ingresso'],
                'lattes_url' => $bruto['lattes'],
                'linha_pesquisa' => $bruto['linha_pesquisa'] ?? '',
                'telefone' => ($bruto['telefone'] ?? '') ?: null,
            ];
            $erros = [];

            if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 255) {
                $erros[] = 'Nome obrigatório (até 255 caracteres).';
            }
            if (! filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'E-mail inválido.';
            }
            if ($dados['matricula'] === '') {
                $erros[] = 'Matrícula obrigatória.';
            } elseif (isset($matriculasExistentes[$dados['matricula']]) || isset($vistosMatricula[$dados['matricula']])) {
                $erros[] = 'Matrícula repetida no programa ou no arquivo.';
            }
            $curso = $cursos->get($dados['curso']);
            if (! $curso) {
                $erros[] = 'Curso inexistente ou inativo: use ' . $cursos->keys()->implode(', ') . '.';
            }
            $data = $this->data($dados['data_ingresso_original']);
            if (! $data) {
                $erros[] = 'Data de ingresso inválida (use dd/mm/aaaa, não futura).';
            }
            if ($dados['lattes_url'] === '') {
                $erros[] = 'O Lattes é obrigatório.';
            } elseif (! preg_match('/^https?:\/\/lattes\.cnpq\.br\/[0-9]+$/', $dados['lattes_url'])) {
                $erros[] = 'Lattes inválido (ex.: http://lattes.cnpq.br/1234567890123456).';
            }
            $linhaPesquisa = null;
            if ($dados['linha_pesquisa'] !== '') {
                $linhaPesquisa = $linhasPesquisa->get($this->chave($dados['linha_pesquisa']));
                if (! $linhaPesquisa) {
                    $erros[] = 'Linha de pesquisa não encontrada no programa.';
                }
            }
            if ($dados['telefone'] && mb_strlen($dados['telefone']) > 30) {
                $erros[] = 'Telefone com mais de 30 caracteres.';
            }

            $existente = isset($emailsExistentes[$dados['email']]) || isset($vistosEmail[$dados['email']]);
            $vistosEmail[$dados['email']] = true;
            if ($dados['matricula'] !== '') {
                $vistosMatricula[$dados['matricula']] = true;
            }

            $dados['programa_curso_id'] = $curso?->id;
            $dados['nivel'] = $curso?->nivel;
            $dados['data_ingresso'] = $data?->format('Y-m-d');
            $dados['linha_pesquisa_id'] = $linhaPesquisa?->id;

            $linhas[] = [
                'numero' => $i + 2,
                'dados' => $dados,
                'status' => $existente ? 'existente' : ($erros ? 'erro' : 'valida'),
                'erros' => $existente ? ['E-mail já cadastrado ou repetido no arquivo.'] : $erros,
            ];
        }

        return ['erro_arquivo' => null, 'linhas' => $linhas];
    }

    private function paraUtf8(string $conteudo): string
    {
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }

        return mb_check_encoding($conteudo, 'UTF-8') ? $conteudo : mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
    }

    private function chave(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->value();
    }

    private function data(string $valor): ?Carbon
    {
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }
        $data = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();

        return $data->isFuture() ? null : $data;
    }
}
