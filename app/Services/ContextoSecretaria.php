<?php

namespace App\Services;

use App\Models\Programa;
use App\Models\ProgramaUsuario;
use App\Models\User;

class ContextoSecretaria
{
    public function programa(User $user): ?Programa
    {
        if ($user->categoria !== 'sec') {
            return null;
        }

        return ProgramaUsuario::query()
            ->where('user_id', $user->id)
            ->where('categoria_no_programa', 'secretaria')
            ->where('ativo', true)
            ->with('programa')
            ->first()
            ?->programa;
    }

    public function exigirPrograma(User $user): Programa
    {
        $programa = $this->programa($user);
        abort_unless($programa, 403, 'Cadastre o programa da Secretaria antes de continuar.');

        return $programa;
    }

    public function garantirPrograma(User $user, ?int $programaId): void
    {
        abort_unless($programaId !== null && $this->programa($user)?->id === $programaId, 403);
    }

    public function vincular(User $user, Programa $programa): void
    {
        $atual = $this->programa($user);
        abort_if($atual && ! $atual->is($programa), 403, 'Esta Secretaria já administra outro programa.');

        ProgramaUsuario::updateOrCreate(
            ['programa_id' => $programa->id, 'user_id' => $user->id, 'categoria_no_programa' => 'secretaria'],
            ['ativo' => true],
        );
    }
}
