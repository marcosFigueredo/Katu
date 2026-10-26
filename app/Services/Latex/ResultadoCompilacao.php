<?php

namespace App\Services\Latex;

class ResultadoCompilacao
{
    /** @param list<string> $avisos linhas "Class ... Warning" */
    public function __construct(
        public readonly bool $sucesso,
        public readonly ?string $pdfPath,
        public readonly ?string $bblPath,
        public readonly string $logResumido,
        public readonly array $avisos = [],
    ) {}
}
