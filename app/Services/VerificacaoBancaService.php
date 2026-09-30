<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\DocenteProgramaVinculo;
use App\Models\ProgramaCurso;
use App\Models\TrabalhoAcademico;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VerificacaoBancaService
{
    public function regraPara(TrabalhoAcademico $trabalho, string $etapa): array
    {
        $trabalho->loadMissing('discenteVinculo.programaCurso.regrasBanca');
        $regra = $trabalho->discenteVinculo?->programaCurso?->regraPara($etapa);

        if ($regra) {
            return $regra->only(['min_avaliadores', 'max_avaliadores', 'min_internos', 'min_externos_instituicao']);
        }

        $nivel = $trabalho->discenteVinculo?->programaCurso?->nivel ?? $trabalho->nivel ?? 'mestrado';
        [$min, $max, $internos, $externos] = (ProgramaCurso::PADROES[$nivel] ?? ProgramaCurso::PADROES['mestrado'])['regra'];

        return [
            'min_avaliadores' => $min,
            'max_avaliadores' => $max,
            'min_internos' => $internos,
            'min_externos_instituicao' => $externos,
        ];
    }

    public function externoInstituicao(DocenteProgramaVinculo $vinculo, ?string $siglaInstituicao): bool
    {
        $normalizar = fn (?string $s) => mb_strtoupper(preg_replace('/\s+/', '', (string) $s));
        $sigla = $normalizar($vinculo->docenteProfile?->instituicao_sigla);

        return $sigla !== '' && $sigla !== $normalizar($siglaInstituicao);
    }

    public function verificar(TrabalhoAcademico $trabalho, string $etapa, Collection $avaliadorVinculoIds): Collection
    {
        $trabalho->loadMissing('programa.instituicao', 'discenteVinculo.discenteProfile');
        $regra = $this->regraPara($trabalho, $etapa);
        $sigla = $trabalho->programa?->instituicao?->sigla;

        $avaliadores = DocenteProgramaVinculo::with('docenteProfile')
            ->whereIn('id', $avaliadorVinculoIds->map(fn ($id) => (int) $id)->unique())
            ->get();
        $total = $avaliadores->count();
        $internos = $avaliadores->where('tipo_vinculo', 'interno')->count();
        $externos = $avaliadores->filter(fn ($v) => $this->externoInstituicao($v, $sigla))->count();
        $orcid = filled($trabalho->discenteVinculo?->discenteProfile?->orcid_url);
        $rotuloInstituicao = $sigla ?: 'instituição do programa';

        return collect([
            [
                'chave' => 'orcid',
                'descricao' => 'ORCID do discente',
                'exigido' => 'informado',
                'encontrado' => $orcid ? 'informado' : 'ausente',
                'ok' => $orcid,
                'mensagem' => 'O discente precisa informar o ORCID no seu cadastro.',
            ],
            [
                'chave' => 'quantidade',
                'descricao' => 'Número de avaliadores',
                'exigido' => $regra['min_avaliadores'] === $regra['max_avaliadores']
                    ? (string) $regra['min_avaliadores']
                    : "{$regra['min_avaliadores']} a {$regra['max_avaliadores']}",
                'encontrado' => (string) $total,
                'ok' => $total >= $regra['min_avaliadores'] && $total <= $regra['max_avaliadores'],
                'mensagem' => "A banca deve ter entre {$regra['min_avaliadores']} e {$regra['max_avaliadores']} avaliadores.",
            ],
            [
                'chave' => 'internos',
                'descricao' => 'Avaliadores internos ao programa',
                'exigido' => "≥ {$regra['min_internos']}",
                'encontrado' => (string) $internos,
                'ok' => $internos >= $regra['min_internos'],
                'mensagem' => "A banca deve ter pelo menos {$regra['min_internos']} avaliador(es) interno(s) ao programa.",
            ],
            [
                'chave' => 'externos_instituicao',
                'descricao' => "Avaliadores externos à {$rotuloInstituicao}",
                'exigido' => "≥ {$regra['min_externos_instituicao']}",
                'encontrado' => (string) $externos,
                'ok' => $externos >= $regra['min_externos_instituicao'],
                'mensagem' => "A banca deve ter pelo menos {$regra['min_externos_instituicao']} avaliador(es) externo(s) à {$rotuloInstituicao}.",
            ],
        ]);
    }

    public function verificarBanca(Banca $banca): Collection
    {
        $banca->loadMissing('trabalhoAcademico', 'membros');
        $ids = $banca->membros->where('ativo', true)->where('papel', 'avaliador')->pluck('docente_programa_vinculo_id');

        return $this->verificar($banca->trabalhoAcademico, $banca->tipo, $ids);
    }

    public function exigir(Collection $itens, string $campo): void
    {
        $falhas = $itens->reject(fn ($i) => $i['ok'])->pluck('mensagem');

        if ($falhas->isNotEmpty()) {
            throw ValidationException::withMessages([$campo => $falhas->implode(' ')]);
        }
    }
}
