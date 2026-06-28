<?php

namespace App\Mail;

use App\Models\TrabalhoDocumento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrabalhoEnviadoBancaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TrabalhoDocumento $documento,
        public string $urlDownload,
        public string $nomeDestinatario,
    ) {}

    public function envelope(): Envelope
    {
        $titulo = $this->documento->trabalhoAcademico?->titulo ?? 'Trabalho acadêmico';

        return new Envelope(
            subject: "Trabalho disponível para avaliação — PPGMSB: {$titulo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trabalho-enviado-banca',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
