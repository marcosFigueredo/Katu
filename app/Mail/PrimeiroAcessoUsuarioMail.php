<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrimeiroAcessoUsuarioMail extends Mailable
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
            subject: 'Acesso ao Sistema de Avaliação de Bancas - PPGMSB'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.primeiro-acesso-usuario'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}