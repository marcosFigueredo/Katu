<?php

namespace App\Http\Controllers;

use App\Models\TrabalhoAcademico;
use App\Services\BibliometriaService;

class BibliometriaController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function show(TrabalhoAcademico $trabalho)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($trabalho->programa_id);

        $trabalho->loadMissing([
            'discenteVinculo.discenteProfile.user',
            'linhaPesquisa',
            'orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $indicador = $trabalho->indicador;

        return view('bibliometria.show', compact('trabalho', 'indicador'));
    }

    public function sincronizar(TrabalhoAcademico $trabalho, BibliometriaService $bibliometriaService)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($trabalho->programa_id);

        $indicador = $bibliometriaService->sincronizar($trabalho);

        return redirect()->route('bibliometria.show', $trabalho)
            ->with('success', 'Indicadores bibliométricos atualizados em ' . $indicador->consultado_em?->format('d/m/Y H:i') . '.');
    }
}
