<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Banca homologada — Folha de Assinaturas e Ata</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto;">

    @php
        $trabalho = $banca->trabalhoAcademico;
        $discente = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $etapa = $banca->tipo === 'defesa' ? 'Defesa' : 'Qualificação';
        $avaliadores = $banca->membros->where('papel', 'avaliador')->where('ativo', true);
    @endphp

    <div style="background:#00303A; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">Banca de {{ $etapa }} Homologada</h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">

        <p>Prezado(a) <strong>{{ $nomeDestinatario }}</strong> ({{ $papelDestinatario }}),</p>

        <p>
            A banca de <strong>{{ strtolower($etapa) }}</strong> do(a) discente
            <strong>{{ $discente?->name ?? '—' }}</strong> foi homologada pela Secretaria e o PDF do trabalho já foi enviado aos membros avaliadores.
        </p>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:16px 0;">
            <p style="margin:0 0 8px;"><strong>Título do trabalho:</strong> {{ $trabalho?->titulo ?: 'Não informado' }}</p>
            <p style="margin:0 0 8px;">
                <strong>Data e horário:</strong>
                {{ $banca->data_hora ? $banca->data_hora->format('d/m/Y \à\s H:i') : 'A definir' }}
            </p>
            <p style="margin:0 0 8px;">
                <strong>Local:</strong> {{ $banca->local ?: 'A definir' }}
            </p>
            @if ($avaliadores->isNotEmpty())
                <p style="margin:0;">
                    <strong>Membros avaliadores:</strong>
                    {{ $avaliadores->map(fn ($m) => $m->docenteProgramaVinculo?->docenteProfile?->user?->name)->filter()->implode(', ') }}
                </p>
            @endif
        </div>

        <h2 style="font-size:16px; color:#00303A; margin:20px 0 8px;">Documentos para assinatura da banca</h2>
        <p style="font-size:14px; margin:0 0 12px;">
            Seguem anexos e disponíveis nos links abaixo a <strong>Folha de Assinaturas (conforme o template LaTeX do programa)</strong> e a <strong>Ata de Assinatura da Banca</strong>:
        </p>

        <p style="margin:16px 0;">
            <a href="{{ $urlFolhaAssinaturas }}"
               style="background:#00303A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px; display:inline-block; font-weight:bold; margin-right:8px; margin-bottom:8px;">
                Folha de Assinaturas (Template LaTeX)
            </a>
            <a href="{{ $urlAtaAssinatura }}"
               style="background:#0f766e; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px; display:inline-block; font-weight:bold; margin-bottom:8px;">
                Ata de Assinatura da Banca
            </a>
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="color:#6b7280; font-size:12px; margin:0;">
            {{ $trabalho?->programa?->sigla ?? 'PPGMSB' }} — {{ $trabalho?->programa?->nome ?? 'Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas' }}
        </p>
    </div>

</body>
</html>

