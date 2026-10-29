<?php

namespace App\Services;

use App\Models\Banca;

/** Monta os campos de "Participação em bancas de trabalhos de conclusão" do Lattes para uma banca. */
class DadosLattesBancaService
{
    private const VAZIO = '—';

    /** @return list<array{rotulo: string, valor: string}> */
    public function campos(Banca $banca): array
    {
        $banca->loadMissing([
            'trabalhoAcademico.programa.instituicao',
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.discenteVinculo.programaCurso',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'trabalhoAcademico.areasCnpq.pai.pai',
            'trabalhoAcademico.setorCnae',
            'membros' => fn ($q) => $q->where('ativo', true)->where('papel', 'avaliador')
                ->orderBy('ordem')->with('docenteProgramaVinculo.docenteProfile.user'),
        ]);

        $trabalho = $banca->trabalhoAcademico;
        $programa = $trabalho?->programa;
        $curso = $trabalho?->discenteVinculo?->programaCurso;
        $discente = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $doutorado = ($trabalho?->nivel ?? 'mestrado') === 'doutorado';

        $natureza = $banca->tipo === 'defesa'
            ? ($doutorado ? 'Doutorado' : 'Mestrado')
            : ($doutorado ? 'Exame de qualificação de doutorado' : 'Exame de qualificação de mestrado');

        $tipo = match ($curso?->modalidade) {
            'academico' => 'Acadêmico',
            'profissional' => 'Profissional',
            default => null,
        };

        $idioma = $trabalho?->submissoesLatex()->where('status', 'ok')->latest('id')->value('idioma') === 'en'
            ? 'Inglês'
            : 'Português';

        $setor = $trabalho?->setorCnae;

        $campos = [
            'Natureza' => $natureza,
            'Tipo' => $tipo,
            'Título' => $trabalho?->titulo,
            'Ano' => $banca->data_hora?->format('Y'),
            'País' => $programa?->instituicao?->pais ?? 'Brasil',
            'Idioma' => $idioma,
            'Homepage do trabalho' => $programa?->homepage_trabalhos,
            'Nome do candidato' => $discente?->name,
            'Instituição' => $programa?->instituicao?->nome,
            'Curso' => $curso ? "{$curso->nome} — {$programa?->nome}" : null,
            'Participantes da banca' => $this->participantes($banca),
            'Palavras-chave' => $trabalho?->palavras_chave,
            'Áreas' => $trabalho?->areasCnpq->map(fn ($a) => $a->caminho())->implode("\n"),
            'Setor de aplicação' => $setor ? "{$setor->codigo} — {$setor->nome}" : null,
            'Outras informações' => $trabalho?->outras_informacoes,
            'Título em inglês' => $trabalho?->titulo_en,
            'Other information' => $trabalho?->outras_informacoes_en,
            'Visibilidade' => ($trabalho?->visibilidade ?? 'publica') === 'privada' ? 'Privada' : 'Pública',
        ];

        return array_map(
            fn ($rotulo, $valor) => ['rotulo' => $rotulo, 'valor' => $this->valor($valor)],
            array_keys($campos),
            $campos,
        );
    }

    /** Orientador, coorientadores, avaliadores e discente, um por linha, com o papel. */
    private function participantes(Banca $banca): string
    {
        $trabalho = $banca->trabalhoAcademico;
        $nome = fn ($vinculo) => $vinculo?->docenteProfile?->user?->name;
        $linhas = [];

        foreach ($trabalho?->orientacoes->sortBy(fn ($o) => [$o->tipo === 'orientador' ? 0 : 1, $o->ordem]) ?? [] as $orientacao) {
            if ($n = $nome($orientacao->docenteProgramaVinculo)) {
                $linhas[] = $n.($orientacao->tipo === 'orientador' ? ' (orientador, presidente)' : ' (coorientador)');
            }
        }
        foreach ($banca->membros as $membro) {
            if ($n = $nome($membro->docenteProgramaVinculo)) {
                $linhas[] = $n.' (avaliador)';
            }
        }
        if ($discente = $trabalho?->discenteVinculo?->discenteProfile?->user?->name) {
            $linhas[] = $discente.' (discente)';
        }

        return implode("\n", $linhas);
    }

    private function valor(mixed $valor): string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? self::VAZIO : $texto;
    }
}
