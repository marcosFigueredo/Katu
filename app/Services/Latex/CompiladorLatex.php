<?php

namespace App\Services\Latex;

interface CompiladorLatex
{
    /**
     * @param  string  $motor  pdflatex|xelatex|lualatex
     * @param  string|null  $apendiceTex  conteúdo .tex anexado ao fim do documento
     */
    public function compilar(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, ?string $apendiceTex = null): ResultadoCompilacao;
}
