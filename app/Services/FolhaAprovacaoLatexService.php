<?php

namespace App\Services;

use App\Models\Banca;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class FolhaAprovacaoLatexService
{
    public function gerarPdf(Banca $banca): string
    {
        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros' => fn ($query) => $query->where('ativo', true)
                ->orderBy('ordem')
                ->with('docenteProgramaVinculo.docenteProfile.user'),
        ]);

        $basePath = "bancas/{$banca->id}/folha-aprovacao";
        $texPath = "{$basePath}.tex";
        $pdfPath = "{$basePath}.pdf";
        $workDir = storage_path("app/latex/banca-{$banca->id}-" . uniqid());

        File::ensureDirectoryExists($workDir);

        try {
            $tex = $this->renderizarTex($banca);
            Storage::disk('local')->put($texPath, $tex);

            $inputPath = $workDir . DIRECTORY_SEPARATOR . 'folha-aprovacao.tex';
            File::put($inputPath, $tex);

            $process = new Process([
                'pdflatex',
                '-interaction=nonstopmode',
                '-halt-on-error',
                'folha-aprovacao.tex',
            ], $workDir);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput() . "\n" . $process->getOutput()));
            }

            $compiledPdf = $workDir . DIRECTORY_SEPARATOR . 'folha-aprovacao.pdf';
            if (! File::exists($compiledPdf)) {
                throw new RuntimeException('O LaTeX não gerou o arquivo PDF esperado.');
            }

            Storage::disk('local')->put($pdfPath, File::get($compiledPdf));

            return $pdfPath;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    public function renderizarTex(Banca $banca): string
    {
        $trabalho = $banca->trabalhoAcademico;
        $discente = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $orientador = $trabalho?->orientacoes
            ->where('tipo', 'orientador')
            ->where('ativo', true)
            ->first();
        $orientadorProfile = $orientador?->docenteProgramaVinculo?->docenteProfile;
        $avaliadores = $banca->membros->where('papel', 'avaliador')->values();

        $nivel = $trabalho?->nivel === 'doutorado' ? 'tese' : 'dissertação';
        $titulo = $trabalho?->titulo ?: 'Titulo do trabalho nao informado';
        $natureza = ucfirst($nivel) . ' submetida a Universidade do Estado da Bahia, no Programa de Pos-Graduacao em Modelagem e Simulacao de Biossistemas, como requisito parcial para a obtencao do titulo de ' .
            ($trabalho?->nivel === 'doutorado' ? 'Doutor' : 'Mestre') . ' em Modelagem e Simulacao de Biossistemas.';

        $membros = [];
        foreach ($avaliadores as $membro) {
            $profile = $membro->docenteProgramaVinculo?->docenteProfile;
            $membros[] = [
                'nome' => $profile?->user?->name ?: 'Membro nao informado',
                'filiacao' => $profile?->instituicao_origem ?: ($profile?->instituicao_sigla ?: 'Instituicao nao informada'),
                'tipo' => $membro->docenteProgramaVinculo?->tipo_vinculo === 'interno' ? '1' : '0',
            ];
        }

        $numeroMembros = max(3, min(6, count($membros) + 1));
        while (count($membros) < 5) {
            $membros[] = ['nome' => '', 'filiacao' => '', 'tipo' => '0'];
        }

        return view('latex.folha-aprovacao', [
            'universidade' => $this->tex('Universidade do Estado da Bahia'),
            'departamento' => $this->tex('Departamento de Ciencias Exatas e da Terra --- Campus II'),
            'programa' => $this->tex($trabalho?->programa?->nome ?: 'Programa de Pos-Graduacao em Modelagem e Simulacao de Biossistemas'),
            'autor' => $this->tex($discente?->name ?: 'Discente nao informado'),
            'titulo' => $this->tex($titulo),
            'subtitulo' => '',
            'natureza' => $this->tex($natureza),
            'local' => $this->tex($banca->local ?: 'Local nao informado'),
            'data' => $this->tex($banca->data_hora?->format('d/m/Y') ?: now()->format('d/m/Y')),
            'orientadorNome' => $this->tex($orientadorProfile?->user?->name ?: 'Orientador nao informado'),
            'orientadorFiliacao' => $this->tex($orientadorProfile?->instituicao_origem ?: ($orientadorProfile?->instituicao_sigla ?: 'Universidade do Estado da Bahia')),
            'numeroMembros' => $numeroMembros,
            'membros' => array_map(fn ($membro) => [
                'nome' => $this->tex($membro['nome']),
                'filiacao' => $this->tex($membro['filiacao']),
                'tipo' => $membro['tipo'],
            ], $membros),
        ])->render();
    }

    private function tex(string $value): string
    {
        $replacements = [
            '\\' => '\\textbackslash{}',
            '&' => '\\&',
            '%' => '\\%',
            '$' => '\\$',
            '#' => '\\#',
            '_' => '\\_',
            '{' => '\\{',
            '}' => '\\}',
            '~' => '\\textasciitilde{}',
            '^' => '\\textasciicircum{}',
        ];

        return strtr($value, $replacements);
    }
}
