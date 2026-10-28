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

class BancaAgendadaOrientacaoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Banca $banca;
    public User $destinatario;
    public string $nomeDestinatario;
    public string $papelDestinatario;
    public string $urlFolhaAssinaturas;
    public string $urlAtaAssinatura;
    public string $texFolhaAssinaturas;
    public string $htmlAtaAssinatura;

    public function __construct(
        Banca $banca,
        User $destinatario,
        string $papelDestinatario = 'Orientador(a)',
        ?string $urlFolhaAssinaturas = null,
        ?string $urlAtaAssinatura = null,
        ?string $texFolhaAssinaturas = null,
        ?string $htmlAtaAssinatura = null,
    ) {
        $this->banca = $banca->loadMissing([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa.instituicao',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros.docenteProgramaVinculo.docenteProfile.user',
        ]);
        $this->destinatario = $destinatario;
        $this->nomeDestinatario = $destinatario->name;
        $this->papelDestinatario = $papelDestinatario;
        $this->urlFolhaAssinaturas = $urlFolhaAssinaturas ?? route('avaliacoes.folha-aprovacao', $this->banca);
        $this->urlAtaAssinatura = $urlAtaAssinatura ?? route('avaliacoes.ata', $this->banca);
        $this->texFolhaAssinaturas = $texFolhaAssinaturas ?? app(FolhaAprovacaoLatexService::class)->renderizarTex($this->banca);
        $this->htmlAtaAssinatura = $htmlAtaAssinatura ?? view('avaliacoes.ata', ['banca' => $this->banca])->render();
    }

    public function envelope(): Envelope
    {
        $discente = $this->banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user?->name ?? 'Discente';
        $etapa = $this->banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';

        return new Envelope(
            subject: "Banca de {$etapa} homologada — Folha de Assinaturas e Ata ({$discente})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.banca-agendada-orientacao',
        );
    }

    public function attachments(): array
    {
        $anexos = [];

        if (filled($this->texFolhaAssinaturas)) {
            $anexos[] = Attachment::fromData(
                fn () => $this->texFolhaAssinaturas,
                "folha-assinaturas-banca-{$this->banca->id}.tex"
            )->withMime('application/x-tex');
        }

        if (filled($this->htmlAtaAssinatura)) {
            $anexos[] = Attachment::fromData(
                fn () => $this->htmlAtaAssinatura,
                "ata-assinatura-banca-{$this->banca->id}.html"
            )->withMime('text/html');
        }

        return $anexos;
    }
}
