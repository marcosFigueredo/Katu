<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso ao avaliador: a banca homologada voltou para revisão porque o orientador foi trocado. */
class BancaRevisaoTrocaOrientadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Banca $banca,
        public User $avaliador,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Banca voltou para revisão — troca de orientador',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.banca-revisao-troca-orientador',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
