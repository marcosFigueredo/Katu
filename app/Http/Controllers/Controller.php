<?php

namespace App\Http\Controllers;

use App\Models\Programa;
use App\Services\ContextoSecretaria;

abstract class Controller
{
    /** Programa administrado pela Secretaria logada (403 se não houver). */
    protected function programaDaSecretaria(): Programa
    {
        return app(ContextoSecretaria::class)->exigirPrograma(auth()->user());
    }

    /** 403 quando o recurso não pertence ao programa da Secretaria logada. */
    protected function garantirProgramaDaSecretaria(?int $programaId): void
    {
        app(ContextoSecretaria::class)->garantirPrograma(auth()->user(), $programaId);
    }
}
