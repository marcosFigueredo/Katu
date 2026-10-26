<?php

namespace App\Services;

use App\Mail\BancaCadastradaMail;
use App\Models\Banca;
use Illuminate\Support\Facades\Mail;

class BancaNotificacaoService
{
    public function enviarCadastro(Banca $banca): void
    {
        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $emails = collect([
            $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->email,
        ])
            ->merge($banca->trabalhoAcademico?->orientacoes?->map(
                fn ($orientacao) => $orientacao->docenteProgramaVinculo?->docenteProfile?->user?->email
            ) ?? collect())
            ->merge($banca->membros?->map(
                fn ($membro) => $membro->docenteProgramaVinculo?->docenteProfile?->user?->email
            ) ?? collect())
            ->filter()
            ->unique()
            ->values();

        foreach ($emails as $email) {
            Mail::to($email)->send(new BancaCadastradaMail(
                $banca,
                route('dashboard'),
            ));
        }
    }
}
