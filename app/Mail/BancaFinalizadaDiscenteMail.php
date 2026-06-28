<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BancaFinalizadaDiscenteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Banca $banca,
        public User $discente,
    ) {}

    public function envelope(): Envelope
    {
        $tipoLabel = $this->banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';

        return new Envelope(
            subject: "Resultado da sua Banca de {$tipoLabel} — PPGMSB",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.banca-finalizada-discente',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
