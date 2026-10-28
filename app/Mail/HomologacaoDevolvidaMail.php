<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HomologacaoDevolvidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public Banca $banca;
    public ?User $destinatario;
    public string $observacoes;
    public string $naoConformidade;

    public function __construct(
        Banca $banca,
        ?User $destinatario = null,
        string $observacoes = '',
    ) {
        $this->banca = $banca;
        $this->destinatario = $destinatario;
        $this->observacoes = $observacoes;
        $this->naoConformidade = $observacoes;
    }

    public function envelope(): Envelope
    {
        $etapa = $this->banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';

        return new Envelope(
            subject: "Pedido de homologação devolvido para ajustes — Banca de {$etapa}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.homologacao-devolvida',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
