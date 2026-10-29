<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\User;
use App\Services\FolhaAprovacaoLatexService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/** Enviado pelo presidente ao finalizar: link da ata e a folha de aprovação em PDF a cada membro. */
class AtaFolhaBancaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Banca $banca,
        public User $membro,
    ) {
    }

    public function envelope(): Envelope
    {
        $etapa = $this->banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';

        return new Envelope(subject: "Ata e folha de aprovação — Banca de {$etapa}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ata-folha-banca');
    }

    public function attachments(): array
    {
        // Gerada só no envio: a folha é compilada em LaTeX.
        return [
            Attachment::fromData(
                fn () => Storage::disk('local')->get(app(FolhaAprovacaoLatexService::class)->gerarPdf($this->banca)),
                "folha-aprovacao-banca-{$this->banca->id}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
