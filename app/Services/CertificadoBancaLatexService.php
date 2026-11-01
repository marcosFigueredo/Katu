<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Certificado de participação na banca (avaliador ou presidente), com o nome do coordenador e a portaria de designação. */
class CertificadoBancaLatexService
{
    public const PAPEIS = [
        'presidente' => 'presidente da banca (orientador)',
        'avaliador' => 'membro avaliador',
    ];

    /** Gera o PDF em storage/app/private e devolve o caminho no disco local. */
    public function gerarPdf(Banca $banca, User $participante, string $papel): string
    {
        $pdfPath = "bancas/{$banca->id}/certificado-{$participante->id}.pdf";
        $workDir = storage_path("app/latex/certificado-{$banca->id}-{$participante->id}-".uniqid());
        File::ensureDirectoryExists($workDir);

        try {
            File::put($workDir.DIRECTORY_SEPARATOR.'certificado.tex', $this->renderizarTex($banca, $participante, $papel));

            $process = new Process(['pdflatex', '-interaction=nonstopmode', '-halt-on-error', 'certificado.tex'], $workDir);
            $process->setTimeout(60);
            $process->run();

            $compilado = $workDir.DIRECTORY_SEPARATOR.'certificado.pdf';
            if (! $process->isSuccessful() || ! File::exists($compilado)) {
                throw new RuntimeException(trim($process->getErrorOutput()."\n".$process->getOutput()) ?: 'O LaTeX não gerou o certificado.');
            }

            Storage::disk('local')->put($pdfPath, File::get($compilado));

            return $pdfPath;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    public function renderizarTex(Banca $banca, User $participante, string $papel): string
    {
        $banca->loadMissing(['trabalhoAcademico.discenteVinculo.discenteProfile.user', 'trabalhoAcademico.programa.instituicao', 'trabalhoAcademico.programa.coordenador']);
        $trabalho = $banca->trabalhoAcademico;
        $programa = $trabalho?->programa;
        $data = $banca->data_hora ?? $banca->updated_at ?? now();
        $logo = public_path('images/logoUneb.png');

        return view('latex.certificado-banca', [
            'logo' => File::exists($logo) ? str_replace('\\', '/', $logo) : null,
            'instituicao' => $this->tex($programa?->instituicao?->nome ?: 'Universidade do Estado da Bahia'),
            'programa' => $this->tex($programa?->nome ?: 'Programa de Pós-Graduação'),
            'sigla' => $this->tex($programa?->sigla ?: ''),
            'participante' => $this->tex($participante->name),
            'papel' => $this->tex(self::PAPEIS[$papel] ?? self::PAPEIS['avaliador']),
            'etapa' => $banca->tipo === 'defesa' ? 'defesa' : 'qualificação',
            'nivel' => $trabalho?->nivel === 'doutorado' ? 'doutorado' : 'mestrado',
            'discente' => $this->tex($trabalho?->discenteVinculo?->discenteProfile?->user?->name ?: '—'),
            'titulo' => $this->tex($trabalho?->titulo ?: 'Título não informado'),
            'data' => $this->tex($data->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y')),
            'local' => $banca->local ? $this->tex(", em {$banca->local}") : '',
            'coordenador' => $this->tex($programa?->coordenador?->name ?: ''),
            'portaria' => $this->tex((string) ($programa?->portaria_coordenador ?? '')),
        ])->render();
    }

    /** Letras gregas, índices e símbolos comuns em títulos de modelagem, que o pdflatex não aceita direto. */
    private const UNICODE = [
        'α' => '\\ensuremath{\\alpha}', 'β' => '\\ensuremath{\\beta}', 'γ' => '\\ensuremath{\\gamma}', 'δ' => '\\ensuremath{\\delta}',
        'ε' => '\\ensuremath{\\varepsilon}', 'ζ' => '\\ensuremath{\\zeta}', 'η' => '\\ensuremath{\\eta}', 'θ' => '\\ensuremath{\\theta}',
        'ι' => '\\ensuremath{\\iota}', 'κ' => '\\ensuremath{\\kappa}', 'λ' => '\\ensuremath{\\lambda}', 'μ' => '\\ensuremath{\\mu}',
        'ν' => '\\ensuremath{\\nu}', 'ξ' => '\\ensuremath{\\xi}', 'π' => '\\ensuremath{\\pi}', 'ρ' => '\\ensuremath{\\rho}',
        'σ' => '\\ensuremath{\\sigma}', 'τ' => '\\ensuremath{\\tau}', 'φ' => '\\ensuremath{\\varphi}', 'χ' => '\\ensuremath{\\chi}',
        'ψ' => '\\ensuremath{\\psi}', 'ω' => '\\ensuremath{\\omega}', 'Γ' => '\\ensuremath{\\Gamma}', 'Δ' => '\\ensuremath{\\Delta}',
        'Θ' => '\\ensuremath{\\Theta}', 'Λ' => '\\ensuremath{\\Lambda}', 'Π' => '\\ensuremath{\\Pi}', 'Σ' => '\\ensuremath{\\Sigma}',
        'Φ' => '\\ensuremath{\\Phi}', 'Ψ' => '\\ensuremath{\\Psi}', 'Ω' => '\\ensuremath{\\Omega}',
        '≤' => '\\ensuremath{\\leq}', '≥' => '\\ensuremath{\\geq}', '≠' => '\\ensuremath{\\neq}', '≈' => '\\ensuremath{\\approx}',
        '±' => '\\ensuremath{\\pm}', '×' => '\\ensuremath{\\times}', '÷' => '\\ensuremath{\\div}', '→' => '\\ensuremath{\\rightarrow}',
        '∞' => '\\ensuremath{\\infty}', '−' => '-',
        '₀' => '\\textsubscript{0}', '₁' => '\\textsubscript{1}', '₂' => '\\textsubscript{2}', '₃' => '\\textsubscript{3}',
        '₄' => '\\textsubscript{4}', '₅' => '\\textsubscript{5}', '₆' => '\\textsubscript{6}', '₇' => '\\textsubscript{7}',
        '₈' => '\\textsubscript{8}', '₉' => '\\textsubscript{9}',
        '⁰' => '\\textsuperscript{0}', '¹' => '\\textsuperscript{1}', '²' => '\\textsuperscript{2}', '³' => '\\textsuperscript{3}',
        '⁴' => '\\textsuperscript{4}', '⁵' => '\\textsuperscript{5}', '⁶' => '\\textsuperscript{6}', '⁷' => '\\textsuperscript{7}',
        '⁸' => '\\textsuperscript{8}', '⁹' => '\\textsuperscript{9}',
    ];

    private function tex(string $value): string
    {
        $value = $this->escapar($value);
        $value = strtr($value, self::UNICODE);

        // O que sobrar fora do latim (com acentos) e da pontuação tipográfica vira o equivalente ASCII, ou "?".
        return preg_replace_callback('/[^\x{0000}-\x{017F}\x{2013}\x{2014}\x{2018}\x{2019}\x{201C}\x{201D}\x{2026}]/u', function ($m) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $m[0]);

            return $ascii !== false && $ascii !== '' && ! str_contains($ascii, '?') ? $this->escapar($ascii) : '?';
        }, $value);
    }

    private function escapar(string $value): string
    {
        return strtr($value, [
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
        ]);
    }
}
