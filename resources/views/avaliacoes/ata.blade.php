<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ata — Banca {{ $banca->trabalhoAcademico?->programa?->sigla }}</title>
    <style>
        @page { size: A4 portrait; margin: 18mm 20mm 18mm 20mm; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10.5pt;
            color: #000;
            background: #fff;
            line-height: 1.45;
        }

        .page { max-width: 170mm; margin: 0 auto; padding: 10px 0; }

        /* ── Cabeçalho ───────────────────────────────── */
        .cabecalho {
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #00303A;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .cabecalho img { height: 48px; width: auto; flex-shrink: 0; }
        .cabecalho-texto { flex: 1; text-align: center; }
        .cabecalho-texto p { font-size: 8pt; line-height: 1.35; font-weight: 600; text-transform: uppercase; color: #00303A; }
        .cabecalho-texto p.inst { font-size: 7.5pt; font-weight: 400; text-transform: none; color: #333; }

        /* ── Título da Ata ───────────────────────────── */
        .titulo-ata {
            text-align: center;
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0 8px;
            line-height: 1.4;
        }

        /* ── Corpo do texto ──────────────────────────── */
        .corpo p {
            text-align: justify;
            text-indent: 1.25cm;
            margin-bottom: 7px;
            font-size: 10pt;
            line-height: 1.5;
        }

        /* ── Resultado ───────────────────────────────── */
        .resultado-bloco {
            margin: 8px 0 8px 1.25cm;
            font-size: 10pt;
        }
        .resultado-opcao {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
            font-weight: 600;
        }
        .checkbox {
            width: 13px; height: 13px;
            border: 1.5px solid #000;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 10pt;
            font-weight: bold;
        }

        /* ── Assinaturas ─────────────────────────────── */
        .assinaturas { margin-top: 14px; }
        .assinaturas-titulo {
            text-align: center;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #00303A;
            border-top: 1.5px solid #00303A;
            padding-top: 6px;
            margin-bottom: 10px;
        }
        .assinaturas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 14px;
        }
        .assinaturas-grid.um-por-linha {
            grid-template-columns: 1fr;
        }

        .sig-box {
            border: 1px dashed #555;
            border-radius: 3px;
            padding: 6px 8px;
            min-height: 68px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .sig-box .sig-espaco {
            flex: 1;
            min-height: 22px;
            /* Espaço para assinatura digital gov.br */
        }
        .sig-box .sig-linha { border-top: 1px solid #000; margin-bottom: 2px; }
        .sig-box .sig-nome { font-size: 8pt; font-weight: bold; line-height: 1.3; }
        .sig-box .sig-role { font-size: 7.5pt; color: #333; line-height: 1.3; }
        .sig-box .sig-inst { font-size: 7pt; color: #555; line-height: 1.2; font-style: italic; }
        .sig-box .sig-cpf  { font-size: 7pt; color: #777; margin-top: 2px; }

        .sig-discente {
            grid-column: 1 / -1;
            max-width: 50%;
            justify-self: center;
        }

        /* ── Rodapé gov.br ───────────────────────────── */
        .rodape-govbr {
            margin-top: 12px;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 7pt;
            color: #555;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .govbr-qr {
            width: 44px; height: 44px;
            border: 1px solid #aaa;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6pt;
            color: #999;
            text-align: center;
        }

        /* ── Botão imprimir ──────────────────────────── */
        .no-print {
            background: #f3f4f6;
            padding: 10px 16px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .no-print button {
            background: #00303A; color: #fff; border: none;
            padding: 8px 20px; border-radius: 6px;
            cursor: pointer; font-size: 13px;
        }
        .no-print a { color: #6b7280; text-decoration: none; }

        @media print {
            .no-print { display: none !important; }
            .page { max-width: 100%; padding: 0; }
            body { font-size: 10.5pt; }
        }
    </style>
</head>
<body>

@php
    $programa = $banca->trabalhoAcademico?->programa;
    $instituicao = $programa?->instituicao;
    $siglaPrograma = $programa?->sigla ?: 'Programa';
    $siglaInstituicao = $instituicao?->sigla ?: 'Instituição';
    $nomePrograma = $programa?->nome ?? '';
    $areaPrograma = \Illuminate\Support\Str::contains($nomePrograma, ' em ')
        ? \Illuminate\Support\Str::after($nomePrograma, ' em ')
        : $nomePrograma;
    $departamento = $instituicao?->departamento;
    $trabalho    = $banca->trabalhoAcademico;
    $discente    = $trabalho?->discenteVinculo?->discenteProfile?->user;
    $programa    = $trabalho?->programa;
    $orientacoes = $trabalho?->orientacoes ?? collect();
    $orientador  = $orientacoes->firstWhere('tipo', 'orientador');
    $coorientadores = $orientacoes->where('tipo', 'coorientador')->where('ativo', true);
    $avaliadores = $banca->membros->where('papel', 'avaliador');

    $nivel     = $trabalho?->nivel ?? 'mestrado';
    $ehDefesa  = $banca->tipo === 'defesa';
    $tipoSessao = $ehDefesa ? 'DEFESA' : 'QUALIFICAÇÃO';
    $tipoTrabalho = $nivel === 'doutorado' ? 'tese' : 'dissertação';
    $titulo     = $ehDefesa ? 'Doutor' : 'Mestre';

    // Nomes
    $nomeDiscente   = $discente?->name ?? '___________________________';
    $nomeOrientador = $orientador?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '___________________________';
    $tituloOrientador = $orientador?->docenteProgramaVinculo?->docenteProfile?->titulacao ?? 'Dr(a).';

    // Data por extenso
    $mesesPT = [
        1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
        7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro',
    ];
    $unidades = ['','um','dois','três','quatro','cinco','seis','sete','oito','nove','dez',
                 'onze','doze','treze','quatorze','quinze','dezesseis','dezessete','dezoito','dezenove'];
    $dezenas  = ['','','vinte','trinta','quarenta','cinquenta','sessenta','setenta','oitenta','noventa'];

    $numPT = function (int $n) use ($unidades, $dezenas): string {
        if ($n <= 19) return $unidades[$n];
        $d = intdiv($n, 10); $u = $n % 10;
        return $u === 0 ? $dezenas[$d] : $dezenas[$d] . ' e ' . $unidades[$u];
    };

    if ($banca->data_hora) {
        $dia      = $banca->data_hora->day;
        $diaExt   = $numPT($dia);
        $mesNum   = $banca->data_hora->month;
        $mesExt   = $mesesPT[$mesNum];
        $anoNum   = $banca->data_hora->year;
        $anoResto = $anoNum - 2000;
        $anoExt   = 'dois mil e ' . $numPT($anoResto);
        $hora     = $banca->data_hora->format('H:i');
        $dataTexto = "Aos {$dia} ({$diaExt}) dias do mês de {$mesExt} do ano de {$anoExt}, às {$hora}h";
    } else {
        $dataTexto = "Aos _____ (_____________) dias do mês de ______________ do ano de dois mil e _____, às _____h";
    }

    $local = $banca->local ?: '___________________________';

    // Resultado pré-marcado
    $res = $banca->resultado ?? null;

    // Titulação dos membros
    $titulacaoNome = function ($membro) {
        $dp    = $membro?->docenteProgramaVinculo?->docenteProfile;
        $titulo = $dp?->titulacao ?? 'Dr(a).';
        $nome  = $dp?->user?->name ?? '—';
        return "Prof(a). {$titulo} {$nome}";
    };

    $instituicaoNome = function ($membro) {
        $dp = $membro?->docenteProgramaVinculo;
        if ($dp?->tipo_vinculo === 'interno') return $siglaInstituicao;
        $inst = $dp?->docenteProfile?->instituicao_origem ?: ($dp?->docenteProfile?->instituicao_sigla ?: 'Externo');
        return $inst;
    };
@endphp

{{-- Barra de ações (some na impressão) --}}
<div class="no-print">
    <button onclick="window.print()">&#128424; Imprimir / Gerar PDF</button>
    <a href="{{ route('avaliacoes.banca', $banca) }}">← Voltar à banca</a>
    <span style="color:#6b7280; font-size:12px; margin-left:auto;">
        Para assinar digitalmente: gere o PDF e envie ao
        <strong>assinador.iti.br</strong> (Gov.br)
    </span>
</div>

<div class="page">

    {{-- Cabeçalho --}}
    <div class="cabecalho">
        @if ($programa?->logoUrl())
            <img src="{{ $programa->logoUrl() }}" alt="{{ $siglaPrograma }}">
        @endif
        <div class="cabecalho-texto">
            <p>{{ $instituicao?->nome }}</p>
            @if ($departamento)
                <p>{{ $departamento }}</p>
            @endif
            <p>{{ $nomePrograma }}</p>
            @if ($instituicao?->cidade)
                <p class="inst">{{ $instituicao->cidade }}{{ $instituicao->estado ? ' — ' . $instituicao->estado : '' }}</p>
            @endif
        </div>
        @if ($instituicao)
            <img src="{{ $instituicao->logoUrl() }}" alt="{{ $siglaInstituicao }}">
        @endif
    </div>

    {{-- Título --}}
    <div class="titulo-ata">
        Ata da Sessão Pública de {{ $tipoSessao }} de {{ $tipoTrabalho }}<br>
        para obtenção do título de {{ $titulo }} em {{ $areaPrograma }}
    </div>

    {{-- Corpo --}}
    <div class="corpo">

        <p>
            {{ $dataTexto }},
            no(a) {{ $local }},
            reuniu-se a Comissão Examinadora da {{ strtolower($tipoSessao) }} em epígrafe, indicada pelo
            Colegiado de Pós-Graduação{{ $departamento ? ' do ' . $departamento : '' }}, composta pelos:
            <strong>Presidente e Orientador {{ $titulacaoNome($orientador) }}</strong>@if($coorientadores->isNotEmpty()),
            {{ $coorientadores->map(fn($c) => 'coorientador(a) ' . $titulacaoNome($c))->implode(', ') }}@endif
            @if($avaliadores->isNotEmpty()),
            e pelos membros avaliadores {{ $avaliadores->map(fn($m) => $titulacaoNome($m) . ' (' . $instituicaoNome($m) . ')')->implode(', ') }}@endif.
        </p>

        <p>
            A comissão se reuniu para analisar o trabalho do(a) discente(a)
            <strong>{{ $nomeDiscente }}</strong>, apresentado sob o título
            <em>{{ $trabalho?->titulo ?: '_______________________________________________' }}</em>.
            O(A) Presidente declarou abertos os trabalhos; a seguir, o(a) candidato(a) dissertou
            sobre o seu trabalho e foi arguido(a) pela Comissão Examinadora. Terminada a exposição
            e a arguição, a Comissão reuniu-se e deliberou pelo seguinte resultado:
        </p>

    </div>

    {{-- Resultado --}}
    <div class="resultado-bloco">
        <div class="resultado-opcao">
            <span class="checkbox">{{ $res === 'aprovado' ? 'X' : '' }}</span>
            APROVADO
        </div>
        <div class="resultado-opcao">
            <span class="checkbox">{{ $res === 'aprovado_modificacoes' ? 'X' : '' }}</span>
            APROVADO CONDICIONALMENTE
        </div>
        <div class="resultado-opcao">
            <span class="checkbox">{{ $res === 'reprovado' ? 'X' : '' }}</span>
            REPROVADO
            <span style="font-weight:400; font-size:9pt;">(anexar parecer circunstanciado elaborado pela Comissão Examinadora)</span>
        </div>
    </div>

    {{-- Instrução --}}
    <div class="corpo">
        @if ($nivel === 'mestrado')
        <p>
            Para fazer jus ao título de Mestre, a versão final da {{ $tipoTrabalho }}, considerada
            aprovada ou aprovada condicionalmente, deverá ser entregue ao Colegiado do {{ $siglaPrograma }} no
            prazo de 60 (sessenta) dias a partir da data da defesa. De acordo com o previsto no
            Regimento do {{ $siglaPrograma }} e na Resolução CONSU 1679/2024, o(a) aluno(a) aprovado(a) deve
            encaminhar à secretaria 1 versão impressa, 1 versão digital em inglês e a versão digital
            em português, além de efetuar o depósito no repositório Saber Aberto. Após a entrega
            do exemplar definitivo e a sua conferência pela Secretaria do {{ $siglaPrograma }}, o resultado será
            homologado e encaminhado ao setor de diplomas, conferindo título de validade nacional
            aos aprovados.
        </p>
        @else
        <p>
            Para fazer jus ao título de Doutor, a versão final da {{ $tipoTrabalho }}, considerada
            aprovada ou aprovada condicionalmente, deverá ser entregue ao Colegiado do {{ $siglaPrograma }} no
            prazo de 60 (sessenta) dias a partir da data da defesa. De acordo com o previsto no
            Regimento do {{ $siglaPrograma }} e na Resolução CONSU 1679/2024, o(a) aluno(a) aprovado(a) deve
            encaminhar à secretaria 1 versão impressa, 1 versão digital em inglês e a versão digital
            em português, além de efetuar o depósito no repositório Saber Aberto. Após a entrega
            do exemplar definitivo e a sua conferência pela Secretaria do {{ $siglaPrograma }}, o resultado será
            homologado e encaminhado ao setor de diplomas, conferindo título de validade nacional
            aos aprovados.
        </p>
        @endif

        <p>
            Nada mais havendo a tratar, o(a) Senhor(a) Presidente declara encerrada a sessão, sendo
            a ata lavrada por mim, que segue assinada pelos Senhores Membros da Comissão Examinadora
            e pelo Coordenador da Comissão de Pós-Graduação, com ciência do(a) aluno(a).
        </p>

        <p style="text-align:center; text-indent:0;">
            Salvador, {{ $banca->data_hora ? $banca->data_hora->format('d/m/Y') : '___/___/______' }}
        </p>
    </div>

    {{-- Assinaturas --}}
    <div class="assinaturas">
        <div class="assinaturas-titulo">Assinaturas</div>

        @php
            /* Monta lista de signatários */
            $signatarios = collect();

            // Presidente (orientador)
            $signatarios->push([
                'nome'  => $nomeOrientador,
                'role'  => 'Presidente da Comissão Examinadora / Orientador(a)',
                'inst'  => $siglaInstituicao . ' — ' . $siglaPrograma,
            ]);

            // Avaliadores
            foreach ($avaliadores as $membro) {
                $dp = $membro->docenteProgramaVinculo?->docenteProfile;
                $signatarios->push([
                    'nome'  => $dp?->user?->name ?? '—',
                    'role'  => 'Membro Avaliador(a)',
                    'inst'  => $dp?->instituicao_origem ?: ($dp?->instituicao_sigla ?: $siglaInstituicao),
                ]);
            }

            // Discente (ciência)
            $signatarios->push([
                'nome'  => $nomeDiscente,
                'role'  => 'Discente — Ciente',
                'inst'  => $siglaPrograma . ' / ' . $siglaInstituicao,
                'discente' => true,
            ]);

            $totalSig = $signatarios->count();
        @endphp

        <div class="assinaturas-grid {{ $totalSig <= 3 ? 'um-por-linha' : '' }}">
            @foreach ($signatarios as $sig)
                <div class="sig-box {{ ($sig['discente'] ?? false) ? 'sig-discente' : '' }}">
                    <div class="sig-espaco"></div>
                    <div class="sig-linha"></div>
                    <div class="sig-nome">{{ $sig['nome'] }}</div>
                    <div class="sig-role">{{ $sig['role'] }}</div>
                    <div class="sig-inst">{{ $sig['inst'] }}</div>
                    <div class="sig-cpf">CPF: ___.___.___ – __</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Rodapé gov.br --}}
    <div class="rodape-govbr">
        <div class="govbr-qr">QR<br>Code<br>Gov.br</div>
        <div>
            <strong>Assinatura Digital — Gov.br</strong><br>
            Este documento deve ser assinado digitalmente por todos os membros da banca através do
            <strong>Assinador Gov.br</strong> (assinador.iti.br) ou pelo fluxo definido pela {{ $siglaInstituicao }}.<br>
            <span style="color:#aaa;">Código de controle: {{ strtoupper(substr(md5($banca->id . $banca->created_at), 0, 16)) }}</span>
        </div>
    </div>

</div>
</body>
</html>
