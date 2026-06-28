<?php

namespace App\Mail;

use App\Models\Banca;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BancaCadastradaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Banca $banca,
        public string $urlSistema,
    ) {}

    public function envelope(): Envelope
    {
        $tipo = match ($this->banca->tipo) {
            'qualificacao' => 'qualificação',
            'defesa' => 'defesa',
            default => $this->banca->tipo,
        };

        return new Envelope(
            subject: "Banca de {$tipo} cadastrada - PPGMSB",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.banca-cadastrada',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
