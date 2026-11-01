<?php

namespace App\Services;

use App\Mail\BancaAgendadaOrientacaoMail;
use App\Mail\HomologacaoDevolvidaMail;
use App\Mail\TrabalhoEnviadoBancaMail;
use App\Models\Banca;
use App\Models\CriterioCpa;
use App\Models\HomologacaoRespostaCpa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class HomologacaoBancaService
{
    public function __construct(
        private readonly VerificacaoBancaService $verificacao,
        private readonly CriterioCpaService $criteriosCpa,
        private readonly BaremasDaBancaService $baremas,
        private readonly AtividadesFormativasService $atividades,
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {
    }

    /**
     * Retorna a coleção de critérios CPA ativos para a banca, acompanhados da resposta
     * da tentativa atual (quando já salva).
     *
     * @return Collection<int, array{criterio: CriterioCpa, resposta: ?HomologacaoRespostaCpa}>
     */
    public function respostasAtuais(Banca $banca): Collection
    {
        $banca->loadMissing('trabalhoAcademico.programa');
        $programa = $banca->trabalhoAcademico?->programa;

        if (! $programa) {
            return collect();
        }

        $perguntas = $this->criteriosCpa->perguntasPara($banca);

        $tentativa = max(1, (int) $banca->homologacao_tentativa);

        $respostasSalvas = HomologacaoRespostaCpa::query()
            ->where('banca_id', $banca->id)
            ->where('tentativa', $tentativa)
            ->get()
            ->keyBy('criterio_cpa_id');

        return $perguntas->map(fn (CriterioCpa $criterio) => [
            'criterio' => $criterio,
            'resposta' => $respostasSalvas->get($criterio->id),
        ]);
    }

    /**
     * Salva as respostas Sim/Não da Secretaria para os critérios da CPA na tentativa atual.
     *
     * @param  array<int|string, mixed>  $respostas  Mapa [criterio_cpa_id => bool|'1'|'0'|'sim'|'nao']
     */
    public function responder(Banca $banca, array $respostas, User $responsavel): void
    {
        $this->exigirAguardando($banca);

        $atuais = $this->respostasAtuais($banca);
        $tentativa = max(1, (int) $banca->homologacao_tentativa);
        $agora = now();

        DB::transaction(function () use ($banca, $atuais, $respostas, $responsavel, $tentativa, $agora) {
            foreach ($atuais as $item) {
                /** @var CriterioCpa $criterio */
                $criterio = $item['criterio'];
                if (! array_key_exists($criterio->id, $respostas) && ! array_key_exists((string) $criterio->id, $respostas)) {
                    continue;
                }

                $valorBruto = $respostas[$criterio->id] ?? $respostas[(string) $criterio->id];
                if ($valorBruto === null || $valorBruto === '') {
                    continue;
                }

                $booleano = in_array($valorBruto, [true, 1, '1', 'sim', 'true'], true);

                HomologacaoRespostaCpa::updateOrCreate(
                    [
                        'banca_id' => $banca->id,
                        'criterio_cpa_id' => $criterio->id,
                        'tentativa' => $tentativa,
                    ],
                    [
                        'pergunta' => $criterio->pergunta,
                        'resposta' => $booleano,
                        'respondido_por' => $responsavel->id,
                        'respondido_em' => $agora,
                    ]
                );
            }
        });
    }

    /**
     * Verifica automaticamente a conformidade do template (LaTeX ou documento submetido).
     *
     * @return array{descricao: string, exigido: string, encontrado: string, ok: bool}
     */
    public function verificarTemplate(Banca $banca): array
    {
        $banca->loadMissing(['trabalhoAcademico.programa', 'documentoHomologacao.submissaoLatex']);

        $documento = $banca->documentoHomologacao;
        $programaUsaLatex = (bool) $banca->trabalhoAcademico?->programa?->temTemplateLatex();

        if (! $documento) {
            return [
                'descricao' => 'Uso do template do programa',
                'exigido' => 'Documento no template',
                'encontrado' => 'Documento não anexado ao pedido',
                'ok' => false,
            ];
        }

        $submissao = $documento->submissaoLatex;

        if ($submissao) {
            $conformidade = $submissao->conformidade ?? [];
            $conformidadeOk = array_key_exists('ok', $conformidade)
                ? (bool) $conformidade['ok']
                : ($submissao->status === 'ok');
            $ok = $submissao->status === 'ok' && $conformidadeOk;

            return [
                'descricao' => 'Uso do template do programa',
                'exigido' => 'Conforme',
                'encontrado' => $ok ? 'Template LaTeX conforme' : 'Divergências no template LaTeX',
                'ok' => $ok,
            ];
        }

        if ($programaUsaLatex) {
            return [
                'descricao' => 'Uso do template do programa',
                'exigido' => 'Submissão no template LaTeX',
                'encontrado' => 'Submissão LaTeX ausente',
                'ok' => false,
            ];
        }

        return [
            'descricao' => 'Uso do template do programa',
            'exigido' => 'PDF enviado para homologação',
            'encontrado' => 'PDF validado',
            'ok' => true,
        ];
    }

    /**
     * Analisa se a banca cumpre todas as condições para homologação:
     * 1. Composição da banca (mínimo 1 interno e 1 externo + regras do curso + ORCID do discente)
     * 2. Uso do template do programa (check automático)
     * 3. Conformidades dos critérios da CPA (100% Sim)
     *
     * @return array{
     *     pode: bool,
     *     composicao: Collection,
     *     composicao_ok: bool,
     *     template: array{descricao: string, exigido: string, encontrado: string, ok: bool},
     *     template_ok: bool,
     *     cpa_respostas: Collection,
     *     cpa_ok: bool,
     *     pendencias: array<int, string>
     * }
     */
    public function podeHomologar(Banca $banca): array
    {
        $composicao = $this->verificacao->verificarBanca($banca);
        $composicaoOk = ! $composicao->contains(fn (array $item) => ! $item['ok']);

        $template = $this->verificarTemplate($banca);
        $templateOk = $template['ok'];

        $cpaRespostas = $this->respostasAtuais($banca);
        $cpaOk = $cpaRespostas->every(
            fn (array $item) => $item['resposta'] !== null && $item['resposta']->resposta === true
        );

        $baremasFaltantes = $this->baremas->faltantes($banca);
        $baremasOk = $baremasFaltantes === [];
        $baremas = [
            'descricao' => 'Baremas cadastrados',
            'exigido' => 'Barema ativo para cada formulário da etapa',
            'encontrado' => $baremasOk ? 'Todos cadastrados' : implode(' ', $baremasFaltantes),
            'ok' => $baremasOk,
        ];

        $pendencias = [];
        $trabalho = $banca->trabalhoAcademico;

        // Atividades formativas: todo lançamento conferido e cada atividade exigida confirmada na quantidade.
        $semConferencia = $trabalho->atividadesRealizadas()
            ->whereIn('atividade_formativa_id', $this->atividades->exigidas($trabalho, $banca->tipo)->pluck('id'))
            ->whereNull('conferencia')->count();
        $incompletas = $this->atividades->faltantes($trabalho, $banca->tipo, soConfirmadas: true);
        $atividadesOk = $semConferencia === 0 && $incompletas === [];
        if ($semConferencia > 0) {
            $pendencias[] = "Há {$semConferencia} lançamento(s) de atividade formativa sem conferência.";
        }
        if ($incompletas !== []) {
            $pendencias[] = 'Atividades formativas incompletas: '.implode('; ', $incompletas).'.';
        }

        // Publicações (só na defesa): todas conferidas e o mínimo do curso entre as que contam.
        $publicacoesOk = true;
        if ($banca->tipo === 'defesa') {
            $naoConferidas = $trabalho->publicacoes()->whereNull('conferida_em')->count();
            $minimo = $this->pedidoHomologacao->minimoPublicacoes($trabalho);
            $contam = $trabalho->publicacoes()->where('conta_minimo', true)->count();
            if ($naoConferidas > 0) {
                $pendencias[] = "Há {$naoConferidas} publicação(ões) sem conferência da Secretaria.";
            } elseif ($contam < $minimo) {
                $pendencias[] = "Publicações que contam para o mínimo do curso: {$contam} de {$minimo}.";
            }
            $publicacoesOk = $naoConferidas === 0 && $contam >= $minimo;
        }

        foreach ($composicao as $item) {
            if (! $item['ok']) {
                $pendencias[] = "{$item['descricao']}: exigido {$item['exigido']}, encontrado {$item['encontrado']}.";
            }
        }

        if (! $templateOk) {
            $pendencias[] = "{$template['descricao']}: {$template['encontrado']}.";
        }

        array_push($pendencias, ...$baremasFaltantes);

        if (! $cpaOk) {
            $faltantes = $cpaRespostas->filter(fn (array $i) => $i['resposta'] === null)->count();
            $negadas = $cpaRespostas->filter(fn (array $i) => $i['resposta'] !== null && ! $i['resposta']->resposta)->count();

            if ($faltantes > 0) {
                $pendencias[] = "Há {$faltantes} critério(s) da CPA sem resposta.";
            }
            if ($negadas > 0) {
                $pendencias[] = "Há {$negadas} critério(s) da CPA respondido(s) com 'Não'.";
            }
        }

        return [
            'pode' => $composicaoOk && $templateOk && $cpaOk && $baremasOk && $atividadesOk && $publicacoesOk,
            'atividades_ok' => $atividadesOk,
            'publicacoes_ok' => $publicacoesOk,
            'baremas' => $baremas,
            'baremas_ok' => $baremasOk,
            'composicao' => $composicao,
            'composicao_ok' => $composicaoOk,
            'template' => $template,
            'template_ok' => $templateOk,
            'cpa_respostas' => $cpaRespostas,
            'cpa_ok' => $cpaOk,
            'pendencias' => $pendencias,
        ];
    }

    /**
     * Homologa a banca, registra o envio na Secretaria, dispara o PDF aos membros da banca
     * e envia ao orientador (e coorientadores) a Folha de Assinaturas (LaTeX) e a Ata de Assinatura.
     */
    public function homologar(Banca $banca, User $responsavel): void
    {
        $this->exigirAguardando($banca);

        $analise = $this->podeHomologar($banca);

        if (! $analise['pode']) {
            throw ValidationException::withMessages([
                'homologacao' => implode(' ', $analise['pendencias']) ?: 'A banca ainda não cumpre os requisitos para homologação.',
            ]);
        }

        $agora = now();

        DB::transaction(function () use ($banca, $responsavel, $agora) {
            $banca->update([
                'homologacao_status' => 'homologada',
                'homologacao_observacoes' => null,
                'libera_envio_trabalho' => true,
                'homologada_em' => $agora,
                'homologada_por' => $responsavel->id,
                'enviada_banca_em' => $agora,
            ]);

            if ($banca->documentoHomologacao) {
                $banca->documentoHomologacao->update([
                    'enviado_em' => $agora,
                ]);
            }
        });

        $this->dispararNotificacoesHomologacao($banca->fresh([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa.instituicao',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros.docenteProgramaVinculo.docenteProfile.user',
            'documentoHomologacao.submissaoLatex.referencias',
        ]));
    }

    /**
     * Devolve a banca ao discente com observações e notifica discente, orientador e coorientadores.
     */
    public function devolver(Banca $banca, User $responsavel, string $observacoes): void
    {
        $this->exigirAguardando($banca);

        $banca->update([
            'homologacao_status' => 'devolvida',
            'homologacao_observacoes' => trim($observacoes),
            'libera_envio_trabalho' => false,
            'devolvida_em' => now(),
            'devolvida_por' => $responsavel->id,
        ]);

        $banca->loadMissing([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $destinatarios = collect();

        $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        if ($discente?->email) {
            $destinatarios->push($discente);
        }

        $orientacoes = $banca->trabalhoAcademico?->orientacoes?->where('ativo', true) ?? collect();
        foreach ($orientacoes as $orientacao) {
            $docenteUser = $orientacao->docenteProgramaVinculo?->docenteProfile?->user;
            if ($docenteUser?->email) {
                $destinatarios->push($docenteUser);
            }
        }

        foreach ($destinatarios->unique('id') as $destinatario) {
            Mail::to($destinatario->email)->send(new HomologacaoDevolvidaMail(
                banca: $banca,
                destinatario: $destinatario,
                observacoes: $banca->homologacao_observacoes,
            ));
        }
    }

    /**
     * Só o pedido aguardando decisão aceita respostas, homologação ou devolução: evita reenviar
     * os e-mails da banca, devolver depois do envio aos avaliadores e mudar respostas já decididas.
     */
    private function exigirAguardando(Banca $banca): void
    {
        if ($banca->status === 'cancelada') {
            throw ValidationException::withMessages([
                'homologacao' => 'Uma banca cancelada não pode ser homologada.',
            ]);
        }

        if ($banca->homologacao_status !== 'aguardando') {
            throw ValidationException::withMessages([
                'homologacao' => 'Este pedido não está aguardando homologação.',
            ]);
        }
    }

    private function dispararNotificacoesHomologacao(Banca $banca): void
    {
        $documento = $banca->documentoHomologacao;
        $submissao = $documento?->submissaoLatex;

        // 1. Envia o PDF para cada membro avaliador ativo da banca
        if ($documento) {
            $downloadUrl = URL::temporarySignedRoute(
                'meu-trabalho.download',
                now()->addDays(7),
                ['documento' => $documento->id]
            );

            $avaliadores = $banca->membros
                ->where('papel', 'avaliador')
                ->where('ativo', true);

            foreach ($avaliadores as $membro) {
                $avaliadorUser = $membro->docenteProgramaVinculo?->docenteProfile?->user;
                if (! $avaliadorUser?->email) {
                    continue;
                }

                Mail::to($avaliadorUser->email)->send(new TrabalhoEnviadoBancaMail(
                    banca: $banca,
                    documento: $documento,
                    avaliador: $avaliadorUser,
                    downloadUrl: $downloadUrl,
                    submissao: $submissao,
                ));
            }
        }

        // 2. Envia para o orientador (e coorientadores ativos) a Folha de Assinaturas (LaTeX) e a Ata de Assinatura
        $orientacoes = $banca->trabalhoAcademico?->orientacoes?->where('ativo', true) ?? collect();
        foreach ($orientacoes as $orientacao) {
            $docenteUser = $orientacao->docenteProgramaVinculo?->docenteProfile?->user;
            if (! $docenteUser?->email) {
                continue;
            }

            Mail::to($docenteUser->email)->send(new BancaAgendadaOrientacaoMail(
                banca: $banca,
                destinatario: $docenteUser,
            ));
        }
    }
}
