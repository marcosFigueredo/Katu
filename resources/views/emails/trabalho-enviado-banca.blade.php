<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Trabalho disponível para avaliação</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto;">

    @php
        $trabalho = $documento->trabalhoAcademico;
        $discente = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $tipoLabel = match($documento->tipo_documento) {
            'qualificacao' => 'Exame de Qualificação',
            'dissertacao'  => 'Dissertação',
            'tese'         => 'Tese',
            default        => $documento->tipo_documento,
        };
    @endphp

    <div style="background:#00303A; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">PPGMSB — Trabalho disponível para avaliação</h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">

        <p>Prezado(a) <strong>{{ $nomeDestinatario }}</strong>,</p>

        <p>
            O(A) discente <strong>{{ $discente?->name ?? '—' }}</strong> enviou o documento
            <strong>{{ $tipoLabel }}</strong> para avaliação da banca.
        </p>

        @if ($trabalho?->titulo)
            <p>
                <strong>Título:</strong><br>
                {{ $trabalho->titulo }}
            </p>
        @endif

        @if ($trabalho?->resumo)
            <p>
                <strong>Resumo:</strong><br>
                <span style="color:#374151;">{{ $trabalho->resumo }}</span>
            </p>
        @endif

        @if ($trabalho?->palavras_chave)
            <p>
                <strong>Palavras-chave:</strong>
                {{ $trabalho->palavras_chave }}
            </p>
        @endif

        <p>
            <strong>Versão:</strong> v{{ str_pad($documento->versao, 2, '0', STR_PAD_LEFT) }}<br>
            <strong>Enviado em:</strong> {{ $documento->enviado_em?->format('d/m/Y \à\s H:i') }}
        </p>

        <p style="margin-top:24px;">
            <a href="{{ $urlDownload }}"
               style="background:#00303A; color:#fff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block; font-weight:bold;">
                Baixar PDF do trabalho
            </a>
        </p>

        <p style="color:#6b7280; font-size:13px; margin-top:16px;">
            Este link é válido por 7 dias. Após esse prazo, solicite novo envio ao discente.
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="color:#6b7280; font-size:12px; margin:0;">
            PPGMSB — Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas<br>
            Universidade do Estado da Bahia (UNEB)
        </p>
    </div>

</body>
</html>
