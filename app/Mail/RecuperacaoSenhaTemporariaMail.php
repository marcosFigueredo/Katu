<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecuperacaoSenhaTemporariaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $usuario,
        public string $senhaTemporaria,
        public string $urlLogin
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperação de acesso - Sistema de Avaliação de Bancas PPGMSB'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recuperacao-senha-temporaria'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}