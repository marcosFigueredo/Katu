<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BancaProntaParaFinalizarMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Banca $banca,
        public User $orientador,
    ) {}

    public function envelope(): Envelope
    {
        $tipoLabel = str_starts_with($this->banca->tipo, 'defesa') ? 'Defesa' : 'Qualificação';
        return new Envelope(
            subject: "Todas as avaliações recebidas — Banca de {$tipoLabel} — PPGMSB",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.banca-pronta-para-finalizar',
        );
    }
}
