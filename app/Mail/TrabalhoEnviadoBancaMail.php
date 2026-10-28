<?php

namespace App\Mail;

use App\Models\Banca;
use App\Models\TrabalhoDocumento;
use App\Models\TrabalhoSubmissaoLatex;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrabalhoEnviadoBancaMail extends Mailable
{
    use Queueable, SerializesModels;

    public Banca $banca;
    public TrabalhoDocumento $documento;
    public User $avaliador;
    public string $downloadUrl;
    public ?TrabalhoSubmissaoLatex $submissao;

    public function __construct(
        Banca $banca,
        TrabalhoDocumento $documento,
        User $avaliador,
        string $downloadUrl,
        ?TrabalhoSubmissaoLatex $submissao = null,
    ) {
        $this->banca = $banca;
        $this->documento = $documento;
        $this->avaliador = $avaliador;
        $this->downloadUrl = $downloadUrl;
        $this->submissao = $submissao;
    }

    public function envelope(): Envelope
    {
        $titulo = $this->banca->trabalhoAcademico?->titulo
            ?? $this->documento->trabalhoAcademico?->titulo
            ?? 'Trabalho acadêmico';

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
