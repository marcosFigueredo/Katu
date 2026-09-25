<?php

namespace App\Mail;

use App\Models\ConviteMembroExterno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConviteMembroExternoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ConviteMembroExterno $convite,
        public string $urlConvite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Convite para cadastro como membro externo — PPGMSB',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.convite-membro-externo');
    }

    public function attachments(): array
    {
        return [];
    }
}
