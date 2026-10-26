<?php

namespace App\Services\Latex;

class ResultadoCompilacao
{
    /**
     * @param  list<string>  $avisos  linhas "Class ... Warning"
     * @param  string|null  $diretorioSaida  diretório temporário com os artefatos (pdf, bbl, log);
     *                                       QUEM CHAMA é responsável por apagá-lo após copiar o que precisa.
     */
    public function __construct(
        public readonly bool $sucesso,
        public readonly ?string $pdfPath,
        public readonly ?string $bblPath,
        public readonly string $logResumido,
        public readonly array $avisos = [],
        public readonly ?string $diretorioSaida = null,
    ) {}
}
