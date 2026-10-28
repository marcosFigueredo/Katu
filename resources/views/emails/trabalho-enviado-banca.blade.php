<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trabalho disponível para avaliação</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    @php
        $discente  = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        $trabalho  = $banca->trabalhoAcademico;
        $programa  = $trabalho?->programa;
        $tipoLabel = $banca->tipo === 'qualificacao' ? 'Qualificação' : 'Defesa';
    @endphp

    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px;">
        <p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280;">
            {{ $programa?->sigla ?? 'PPGMSB' }} — {{ $programa?->nome ?? 'Programa de Pós-Graduação em Modelagem em Saúde da Bahia' }}
        </p>

        <h1 style="margin: 0 0 16px 0; font-size: 20px; color: #00303A;">
            Trabalho disponível para avaliação
        </h1>

        <p style="margin: 0 0 16px 0; line-height: 1.6;">
            Olá, <strong>{{ $avaliador->name }}</strong>.
        </p>

        <p style="margin: 0 0 16px 0; line-height: 1.6;">
            A Secretaria homologou a banca e disponibilizou a versão <strong>v{{ $documento->versao }}</strong> do trabalho para a
            <strong>Banca de {{ $tipoLabel }}</strong> na qual você atua como avaliador(a).
        </p>

        <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
            <p style="margin: 0 0 6px 0; font-size: 13px; color: #6b7280;">Discente</p>
            <p style="margin: 0 0 12px 0; font-weight: bold;">{{ $discente?->name ?? '—' }}</p>

            @if ($trabalho?->titulo)
                <p style="margin: 0 0 6px 0; font-size: 13px; color: #6b7280;">Título do trabalho</p>
                <p style="margin: 0 0 12px 0; font-weight: bold;">{{ $trabalho->titulo }}</p>
            @endif

            @if ($banca->data_hora)
                <p style="margin: 0 0 6px 0; font-size: 13px; color: #6b7280;">Data da banca</p>
                <p style="margin: 0; font-weight: bold;">{{ $banca->data_hora->format('d/m/Y \à\s H:i') }}</p>
            @endif
        </div>

        @if ($submissao)
            @php
                $conformidade = $submissao->conformidade ?? [];
                $confOk = array_key_exists('ok', $conformidade)
                    ? (bool) $conformidade['ok']
                    : ($submissao->status === 'ok');
                $refs = $submissao->relationLoaded('referencias') ? $submissao->referencias : $submissao->referencias()->get();
                $contagem = $refs->countBy('status');
                $totalRefs = (int) ($submissao->refs_total ?? $refs->count());
                $pctRecentes = $submissao->refs_recentes_pct;
            @endphp
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <p style="margin: 0 0 8px 0; font-weight: bold; color: #00303A;">
                    Verificação automática do template: {{ $confOk ? 'Conforme' : 'Com divergências' }}
                </p>

                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 12px 0;">
                <p style="margin: 0 0 6px 0; font-weight: bold; color: #00303A;">
                    Resumo da verificação das referências
                </p>
                <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5;">
                    Total de referências: <strong>{{ $totalRefs }}</strong>
                    @if ($pctRecentes !== null)
                        | Últimos 2 anos: <strong>{{ number_format((float) $pctRecentes, 1, ',', '.') }}%</strong>
                        ({{ $submissao->refs_atualizadas ? 'meta ≥ 70% atingida' : 'abaixo de 70%' }})
                    @endif
                </p>
                <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5;">
                    Localizadas: <strong>{{ $contagem['localizada'] ?? 0 }}</strong> |
                    Não localizadas: <strong>{{ $contagem['nao_localizada'] ?? 0 }}</strong> |
                    Produção do orientador: <strong>{{ (int) $submissao->refs_orientador }}</strong>
                </p>
                <p style="margin: 0; font-size: 13px; color: #b45309; font-weight: bold;">
                    Atenção: Referências não localizadas devem ser verificadas.
                </p>
            </div>
        @endif

        <p style="margin: 0 0 20px 0;">
            <a href="{{ $downloadUrl }}"
               style="display: inline-block; background: #00303A; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold;">
                Baixar o trabalho (PDF)
            </a>
        </p>

        <p style="margin: 0 0 8px 0; font-size: 12px; color: #6b7280; line-height: 1.5;">
            O link acima é válido por 7 dias e requer que você esteja autenticado(a) no sistema.
        </p>

        <p style="margin: 0; font-size: 12px; color: #6b7280; line-height: 1.5;">
            Caso o botão não funcione, copie e cole este endereço no navegador:<br>
            <span style="word-break: break-all;">{{ $downloadUrl }}</span>
        </p>
    </div>
</body>
</html>
