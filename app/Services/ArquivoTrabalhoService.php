<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class ArquivoTrabalhoService
{
    public function gerarNomePdf(User $usuario, string $tipoDocumento, int $versao): string
    {
        $ultimoNome = $this->extrairUltimoNome($usuario->name);

        $dataHora = now()->format('Ymd_His');

        $tipo = Str::slug($tipoDocumento, '_');

        $versaoFormatada = 'v' . str_pad((string) $versao, 2, '0', STR_PAD_LEFT);

        $chave = Str::lower(Str::random(6));

        return "{$ultimoNome}_{$dataHora}_{$tipo}_{$versaoFormatada}_{$chave}.pdf";
    }

    public function gerarPathPdf(int $trabalhoAcademicoId, string $nomeArquivo): string
    {
        return "trabalhos/{$trabalhoAcademicoId}/{$nomeArquivo}";
    }

    private function extrairUltimoNome(string $nomeCompleto): string
    {
        $nomeCompleto = trim($nomeCompleto);

        if ($nomeCompleto === '') {
            return 'usuario';
        }

        $partes = preg_split('/\s+/', $nomeCompleto);

        $ultimoNome = end($partes) ?: 'usuario';

        return Str::slug($ultimoNome, '_') ?: 'usuario';
    }
}
