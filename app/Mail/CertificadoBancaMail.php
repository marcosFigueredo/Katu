<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Enviado na finalização da banca a cada avaliador e ao presidente, com o certificado de participação em PDF. */
class CertificadoBancaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Banca $banca,
        public User $participante,
        public string $papel,
        public string $caminhoPdf,
    ) {
    }

    public function envelope(): Envelope
    {
        $etapa = $this->banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';

        return new Envelope(subject: "Certificado de participação — Banca de {$etapa}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.certificado-banca');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->caminhoPdf)
                ->as("certificado-banca-{$this->banca->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
