<?php

namespace App\Http\Controllers;

use App\Models\Programa;

/** Área da CPA: qualidade das dissertações e teses do programa. */
class CpaQualidadeController extends Controller
{
    public function index()
    {
        $programa = $this->programaCpa();

        return view('cpa.index', compact('programa'));
    }

    /** Programa em que o usuário logado tem mandato vigente na CPA; senão 403. */
    protected function programaCpa(): Programa
    {
        $programa = auth()->user()->programaCpa();
        abort_unless($programa, 403);

        return $programa;
    }
}
