<?php

namespace App\Http\Controllers;

use App\Mail\AtaFolhaBancaMail;
use App\Mail\BancaFinalizadaDiscenteMail;
use App\Mail\BancaProntaParaFinalizarMail;
use App\Models\AutoavaliacaoOrientador;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\BancaAvaliacaoNota;
use App\Models\Barema;
use App\Models\Orientacao;
use App\Services\BaremasDaBancaService;
use App\Services\FolhaAprovacaoLatexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AvaliacaoController extends Controller
{
    // -------------------------------------------------------------------------
    // Dashboard do docente — lista bancas onde é avaliador e onde é orientador
    // -------------------------------------------------------------------------
    public function index()
    {
        $user = auth()->user();
        $this->autorizarDocente($user);

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        $bancasAvaliador  = collect();
        $bancasOrientador = collect();

        if ($vinculo) {
            $membrosIds = \App\Models\BancaMembro::where('docente_programa_vinculo_id', $vinculo->id)
                ->where('ativo', true)
                ->pluck('banca_id');

            $bancasAvaliador = Banca::whereIn('id', $membrosIds)
                ->whereNotIn('status', ['cancelada'])
                ->with([
                    'trabalhoAcademico.discenteVinculo.discenteProfile.user',
                    'membros' => fn ($q) => $q->where('ativo', true)
                        ->with(['avaliacoes.barema', 'docenteProgramaVinculo']),
                ])
                ->orderByDesc('data_hora')
                ->get()
                ->each(function ($banca) use ($vinculo) {
                    $banca->meuMembro = $banca->membros
                        ->firstWhere('docente_programa_vinculo_id', $vinculo->id);
                });

            $trabalhoIds = Orientacao::where('docente_programa_vinculo_id', $vinculo->id)
                ->where('ativo', true)
                ->pluck('trabalho_academico_id');

            $bancasOrientador = Banca::whereIn('trabalho_academico_id', $trabalhoIds)
                ->whereNotIn('status', ['cancelada'])
                ->with([
                    'trabalhoAcademico.discenteVinculo.discenteProfile.user',
                    'membros' => fn ($q) => $q->where('ativo', true)
                        ->with(['avaliacoes.barema', 'docenteProgramaVinculo.docenteProfile.user']),
                ])
                ->orderByDesc('data_hora')
                ->get()
                ->each(function ($banca) use ($vinculo) {
                    $baremas       = $this->baremasParaBanca($banca);
                    $avaliadores   = $banca->membros->where('papel', 'avaliador');
                    $totalEnviadas = $avaliadores->flatMap->avaliacoes->where('status', 'enviada')->count();
                    $totalEsperado = $avaliadores->count() * $baremas->count();

                    $banca->totalBaremas  = $baremas->count();
                    $banca->totalEnviadas = $totalEnviadas;
                    $banca->totalEsperado = $totalEsperado;
                    $banca->podeFinalizar = $banca->status === 'em_avaliacao'
                        && $totalEsperado > 0
                        && $totalEnviadas >= $totalEsperado;
                });
        }

        return view('dashboards.docente', compact('user', 'vinculo', 'bancasAvaliador', 'bancasOrientador'));
    }

    // -------------------------------------------------------------------------
    // Detalhe de uma banca — status por avaliador + ação de finalizar
    // -------------------------------------------------------------------------
    public function banca(Banca $banca)
    {
        $user = auth()->user();
        $this->autorizarDocente($user);

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'documentoHomologacao.submissaoLatex.referencias',
            'membros' => fn ($q) => $q->where('ativo', true)
                ->with(['avaliacoes.barema', 'docenteProgramaVinculo.docenteProfile.user']),
        ]);

        $ehMembro = $vinculo
            && $banca->membros->firstWhere('docente_programa_vinculo_id', $vinculo->id) !== null;

        $ehOrientador = $vinculo && $banca->trabalhoAcademico->orientacoes
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->where('ativo', true)
            ->isNotEmpty();

        if (! $ehMembro && ! $ehOrientador) {
            abort(403);
        }

        $baremas  = $this->baremasParaBanca($banca);
        $meuMembro = $ehMembro
            ? $banca->membros->firstWhere('docente_programa_vinculo_id', $vinculo->id)
            : null;

        $minhasAvaliacoes = collect();
        if ($meuMembro) {
            $minhasAvaliacoes = BancaAvaliacao::where('banca_membro_id', $meuMembro->id)
                ->whereIn('barema_id', $baremas->pluck('id'))
                ->get()
                ->keyBy('barema_id');
        }

        $avaliadores   = $banca->membros->where('papel', 'avaliador');
        $totalEnviadas = $avaliadores->flatMap->avaliacoes->where('status', 'enviada')->count();
        $totalEsperado = $avaliadores->count() * $baremas->count();
        $podeFinalizar = $ehOrientador
            && $banca->status === 'em_avaliacao'
            && $totalEsperado > 0
            && $totalEnviadas >= $totalEsperado;

        // Autoavaliação do orientador (só defesa): o orientador vê apenas se concluiu.
        $baremaAutoavaliacao = $ehOrientador && $banca->tipo === 'defesa'
            ? app(BaremasDaBancaService::class)->baremaAtivo($banca, 'autoavaliacao_orientador')
            : null;
        $autoavaliacao = $ehOrientador && $banca->tipo === 'defesa'
            ? AutoavaliacaoOrientador::where('banca_id', $banca->id)->where('docente_programa_vinculo_id', $vinculo->id)->first()
            : null;

        $autoavaliacaoPendente = $ehOrientador && $this->autoavaliacaoPendente($banca, $vinculo->id);

        return view('avaliacoes.banca', compact(
            'banca', 'baremas', 'meuMembro', 'minhasAvaliacoes',
            'ehMembro', 'ehOrientador', 'podeFinalizar',
            'avaliadores', 'totalEnviadas', 'totalEsperado',
            'baremaAutoavaliacao', 'autoavaliacao', 'autoavaliacaoPendente'
        ));
    }

    // -------------------------------------------------------------------------
    // Formulário de avaliação — preencher itens do barema
    // -------------------------------------------------------------------------
    public function form(Banca $banca, Barema $barema)
    {
        $user = auth()->user();
        $this->autorizarDocente($user);

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        $meuMembro = $banca->membros()
            ->where('docente_programa_vinculo_id', $vinculo?->id)
            ->where('ativo', true)
            ->first();

        if (! $meuMembro) {
            abort(403, 'Você não é membro desta banca.');
        }

        $tiposPermitidos = [$banca->tipo, $banca->tipo . '_confidencial'];
        if (! in_array($barema->tipo, $tiposPermitidos)) {
            abort(403, 'Barema inválido para esta banca.');
        }

        if ($banca->status === 'cancelada') {
            return redirect()->route('avaliacoes.banca', $banca)
                ->withErrors(['status' => 'Esta banca foi cancelada.']);
        }

        if ($banca->homologacao_status !== 'homologada') {
            return redirect()->route('avaliacoes.banca', $banca)
                ->withErrors(['status' => 'A banca ainda não foi homologada pela Secretaria.']);
        }

        $banca->load('trabalhoAcademico.discenteVinculo.discenteProfile.user');
        $barema->load(['secoes' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')
            ->with(['itens' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')])]);

        $avaliacao = BancaAvaliacao::firstOrCreate(
            ['banca_membro_id' => $meuMembro->id, 'barema_id' => $barema->id],
            ['banca_id' => $banca->id, 'status' => 'rascunho'],
        );

        $notasExistentes = $avaliacao->notas()->get()->keyBy('barema_item_id');
        $somenteLeitura  = $banca->status === 'finalizada' || $avaliacao->status === 'enviada';

        return view('avaliacoes.form', compact(
            'banca', 'barema', 'avaliacao', 'notasExistentes', 'somenteLeitura'
        ));
    }

    // -------------------------------------------------------------------------
    // Salva rascunho ou envia a avaliação
    // -------------------------------------------------------------------------
    public function salvar(Request $request, Banca $banca, Barema $barema)
    {
        $user = auth()->user();
        $this->autorizarDocente($user);

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        $meuMembro = $banca->membros()
            ->where('docente_programa_vinculo_id', $vinculo?->id)
            ->where('ativo', true)
            ->first();

        if (! $meuMembro) {
            abort(403);
        }

        if ($banca->homologacao_status !== 'homologada') {
            return redirect()->route('avaliacoes.banca', $banca)
                ->withErrors(['status' => 'A banca ainda não foi homologada pela Secretaria.']);
        }

        if (in_array($banca->status, ['finalizada', 'cancelada'])) {
            abort(403, 'Banca encerrada.');
        }

        $avaliacao = BancaAvaliacao::firstOrCreate(
            ['banca_membro_id' => $meuMembro->id, 'barema_id' => $barema->id],
            ['banca_id' => $banca->id, 'status' => 'rascunho'],
        );

        $todosItens = $barema->secoes()->where('ativo', true)
            ->with(['itens' => fn ($q) => $q->where('ativo', true)])
            ->get()
            ->flatMap->itens;

        $rules = [
            'acao'               => ['required', 'in:rascunho,enviar'],
            'observacoes_gerais' => ['nullable', 'string', 'max:3000'],
        ];
        foreach ($todosItens as $item) {
            $rules["notas.{$item->id}"]       = ['nullable', 'numeric', 'min:0', "max:{$item->nota_maxima}"];
            $rules["observacoes.{$item->id}"]  = ['nullable', 'string', 'max:1000'];
        }

        $validated = $request->validate($rules);

        foreach ($todosItens as $item) {
            BancaAvaliacaoNota::updateOrCreate(
                ['banca_avaliacao_id' => $avaliacao->id, 'barema_item_id' => $item->id],
                [
                    'nota'       => $validated['notas'][$item->id] ?? null,
                    'observacao' => $validated['observacoes'][$item->id] ?? null,
                ]
            );
        }

        $avaliacao->update(['observacoes_gerais' => $validated['observacoes_gerais'] ?? null]);

        if ($validated['acao'] === 'enviar') {
            $faltam = $todosItens->filter(fn ($i) => $i->obrigatorio)
                ->filter(fn ($i) => ($validated['notas'][$i->id] ?? null) === null);

            if ($faltam->isNotEmpty()) {
                return back()
                    ->withErrors(['notas' => "Preencha as notas de todos os itens obrigatórios ({$faltam->count()} pendente(s))."])
                    ->withInput();
            }

            $avaliacao->load('notas.baremaItem');
            $notaFinal = $avaliacao->notaCalculada();

            $avaliacao->update([
                'status'     => 'enviada',
                'nota_final' => $notaFinal,
                'enviada_em' => now(),
            ]);

            if ($banca->status === 'cadastrada') {
                $banca->update(['status' => 'em_avaliacao']);
            }

            // Notify orientador when all avaliadores have submitted all baremas
            $baremas       = $this->baremasParaBanca($banca);
            $avaliadores   = $banca->avaliadores()->get();
            $totalEsperado = $avaliadores->count() * $baremas->count();

            if ($totalEsperado > 0) {
                $totalEnviadas = BancaAvaliacao::whereIn('banca_membro_id', $avaliadores->pluck('id'))
                    ->where('status', 'enviada')
                    ->whereIn('barema_id', $baremas->pluck('id'))
                    ->count();

                if ($totalEnviadas >= $totalEsperado) {
                    $banca->load('trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user');
                    $orientacaoAtiva = $banca->trabalhoAcademico->orientacoes
                        ->where('tipo', 'orientador')
                        ->where('ativo', true)
                        ->first();
                    $orientadorUser = $orientacaoAtiva?->docenteProgramaVinculo?->docenteProfile?->user;
                    if ($orientadorUser?->email) {
                        Mail::to($orientadorUser->email)
                            ->send(new BancaProntaParaFinalizarMail($banca, $orientadorUser));
                    }
                }
            }

            return redirect()
                ->route('avaliacoes.banca', $banca)
                ->with('success', 'Avaliação enviada com sucesso.');
        }

        return back()->with('success', 'Rascunho salvo.');
    }

    // -------------------------------------------------------------------------
    // Orientador finaliza a banca — registra resultado e notifica discente
    // -------------------------------------------------------------------------
    public function finalizar(Request $request, Banca $banca)
    {
        $user = auth()->user();
        $this->autorizarDocente($user);

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        $banca->load([
            'trabalhoAcademico.orientacoes',
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
        ]);

        $ehOrientador = $vinculo && $banca->trabalhoAcademico->orientacoes
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->where('ativo', true)
            ->isNotEmpty();

        if (! $ehOrientador) {
            abort(403, 'Apenas o orientador pode finalizar a banca.');
        }

        if ($banca->status !== 'em_avaliacao') {
            return back()->withErrors(['finalizar' => 'A banca precisa estar em avaliação para ser finalizada.']);
        }

        $request->validate([
            'resultado' => ['required', 'in:aprovado,aprovado_modificacoes,reprovado'],
        ], [
            'resultado.required' => 'Selecione o resultado da banca.',
            'resultado.in'       => 'Resultado inválido.',
        ]);

        $baremas     = $this->baremasParaBanca($banca);
        $avaliadores = $banca->avaliadores()->get();

        $totalEnviadas = BancaAvaliacao::whereIn('banca_membro_id', $avaliadores->pluck('id'))
            ->where('status', 'enviada')
            ->whereIn('barema_id', $baremas->pluck('id'))
            ->count();
        $totalEsperado = $avaliadores->count() * $baremas->count();

        if ($totalEnviadas < $totalEsperado) {
            $faltam = $totalEsperado - $totalEnviadas;
            return back()->withErrors([
                'finalizar' => "Ainda há {$faltam} avaliação(ões) pendente(s). Todos os avaliadores devem enviar antes de finalizar.",
            ]);
        }

        $faltantes = app(BaremasDaBancaService::class)->faltantes($banca);
        if ($faltantes !== []) {
            return back()->withErrors(['finalizar' => implode(' ', $faltantes)]);
        }

        if ($this->autoavaliacaoPendente($banca, $vinculo->id)) {
            return back()->withErrors(['finalizar' => 'Conclua sua autoavaliação para finalizar a banca e liberar a ata e a folha de aprovação.']);
        }

        // Calcula nota média consolidada (média das notas_final de todos avaliadores)
        $notasFinais = BancaAvaliacao::whereIn('banca_membro_id', $avaliadores->pluck('id'))
            ->where('status', 'enviada')
            ->whereNotNull('nota_final')
            ->pluck('nota_final');

        $notaMedia = $notasFinais->isNotEmpty()
            ? round($notasFinais->avg(), 2)
            : null;

        $banca->update([
            'status'         => 'finalizada',
            'resultado'      => $request->resultado,
            'nota_media'     => $notaMedia,
            'ata_enviada_em' => now(),
        ]);

        // O presidente envia a ata e a folha de aprovação aos membros da banca.
        foreach ($avaliadores->load('docenteProgramaVinculo.docenteProfile.user') as $membro) {
            $usuarioMembro = $membro->docenteProgramaVinculo?->docenteProfile?->user;
            if ($usuarioMembro?->email) {
                Mail::to($usuarioMembro->email)->send(new AtaFolhaBancaMail($banca, $usuarioMembro));
            }
        }

        // Atualiza status do trabalho acadêmico
        if (in_array($request->resultado, ['aprovado', 'aprovado_modificacoes'])) {
            $novoStatus = str_starts_with($banca->tipo, 'defesa') ? 'defendido' : 'qualificado';
            $banca->trabalhoAcademico->update(['status' => $novoStatus]);
        }

        // Notifica o discente
        $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        if ($discente?->email) {
            Mail::to($discente->email)
                ->send(new BancaFinalizadaDiscenteMail($banca, $discente));
        }

        return redirect()
            ->route('avaliacoes.banca', $banca)
            ->with('success', 'Banca finalizada. A ata e a folha de aprovação foram enviadas aos membros e o discente foi notificado por e-mail.');
    }

    // -------------------------------------------------------------------------
    // Ata da banca — documento oficial para assinatura (HTML imprimível / gov.br)
    // -------------------------------------------------------------------------
    public function ata(Banca $banca)
    {
        $this->autorizarAta($banca);

        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa.instituicao',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')
                ->with('docenteProgramaVinculo.docenteProfile.user'),
        ]);

        return view('avaliacoes.ata', compact('banca'));
    }

    public function folhaAprovacao(Banca $banca, FolhaAprovacaoLatexService $service)
    {
        $this->autorizarAta($banca);

        if ($banca->status !== 'finalizada' && $banca->homologacao_status !== 'homologada') {
            return back()->withErrors(['folha_aprovacao' => 'A folha de aprovação só pode ser gerada após a homologação ou finalização da banca.']);
        }

        $path = $service->gerarPdf($banca);
        $filename = 'folha-aprovacao-banca-' . $banca->id . '.pdf';

        return response()->download(Storage::disk('local')->path($path), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // -------------------------------------------------------------------------
    // Relatório individual — avaliador vê seu barema preenchido (HTML imprimível)
    // -------------------------------------------------------------------------
    public function relatorioIndividual(Banca $banca, Barema $barema)
    {
        $user = auth()->user();

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        // Docente vê o próprio relatório; secretaria pode ver qualquer um
        if ($user->categoria === 'docente') {
            $meuMembro = $banca->membros()
                ->where('docente_programa_vinculo_id', $vinculo?->id)
                ->where('ativo', true)
                ->first();

            if (! $meuMembro) {
                abort(403);
            }

            $avaliacao = BancaAvaliacao::where('banca_membro_id', $meuMembro->id)
                ->where('barema_id', $barema->id)
                ->firstOrFail();

        } elseif ($user->categoria === 'sec') {
            $this->garantirProgramaDaSecretaria($banca->trabalhoAcademico?->programa_id);
            // Secretaria pode passar o membro via query string
            $membroId  = request('membro');
            $avaliacao = BancaAvaliacao::where('banca_id', $banca->id)
                ->where('barema_id', $barema->id)
                ->when($membroId, fn ($q) => $q->where('banca_membro_id', $membroId))
                ->firstOrFail();
        } else {
            abort(403);
        }

        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa',
        ]);
        $barema->load(['secoes' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')
            ->with(['itens' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')])]);

        $avaliacao->load(['notas.baremaItem', 'membro.docenteProgramaVinculo.docenteProfile.user']);

        $notasIndexadas = $avaliacao->notas->keyBy('barema_item_id');

        return view('avaliacoes.relatorio-individual', compact(
            'banca', 'barema', 'avaliacao', 'notasIndexadas'
        ));
    }

    // -------------------------------------------------------------------------
    // Relatório consolidado — secretaria vê todos os avaliadores e notas
    // -------------------------------------------------------------------------
    public function relatorioConsolidado(Banca $banca)
    {
        if (! in_array(auth()->user()->categoria, ['sec', 'docente'])) {
            abort(403);
        }

        if (auth()->user()->categoria === 'sec') {
            $this->garantirProgramaDaSecretaria($banca->trabalhoAcademico?->programa_id);
        }

        // Orientador também pode ver
        if (auth()->user()->categoria === 'docente') {
            $user = auth()->user()->load('docenteProfile.vinculosPrograma');
            $vinculo = $user->docenteProfile?->vinculosPrograma?->first();
            $ehOrientador = $vinculo && $banca->trabalhoAcademico->orientacoes()
                ->where('docente_programa_vinculo_id', $vinculo->id)
                ->where('ativo', true)
                ->exists();
            if (! $ehOrientador) {
                abort(403);
            }
        }

        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa',
            'membros' => fn ($q) => $q->where('ativo', true)
                ->with([
                    'docenteProgramaVinculo.docenteProfile.user',
                    'avaliacoes.barema',
                    'avaliacoes.notas.baremaItem',
                ]),
        ]);

        $baremas = $this->baremasParaBanca($banca);
        $baremas->load(['secoes' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')
            ->with(['itens' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')])]);

        $avaliadores = $banca->membros->where('papel', 'avaliador');

        return view('avaliacoes.relatorio-consolidado', compact(
            'banca', 'baremas', 'avaliadores'
        ));
    }

    // -------------------------------------------------------------------------
    private function baremasParaBanca(Banca $banca): \Illuminate\Support\Collection
    {
        $programaId  = $banca->trabalhoAcademico->programa_id;
        $nivel       = $banca->trabalhoAcademico->nivel ?? 'mestrado';
        $tiposBarema = [$banca->tipo, $banca->tipo . '_confidencial'];

        return Barema::where('programa_id', $programaId)
            ->whereIn('tipo', $tiposBarema)
            ->whereIn('nivel', [$nivel, 'ambos'])
            ->where('ativo', true)
            ->orderBy('tipo')
            ->get();
    }

    private function autorizarDocente($user): void
    {
        if ($user->categoria !== 'docente') {
            abort(403);
        }
    }

    private function autorizarAta(Banca $banca): void
    {
        $user = auth()->user();

        if ($user->categoria === 'sec') {
            $this->garantirProgramaDaSecretaria($banca->trabalhoAcademico?->programa_id);

            return;
        }

        if ($user->categoria !== 'docente') {
            abort(403);
        }

        $user->load('docenteProfile.vinculosPrograma');
        $vinculo = $user->docenteProfile?->vinculosPrograma?->first();

        $ehOrientador = $vinculo && $banca->trabalhoAcademico->orientacoes()
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->where('ativo', true)
            ->exists();

        if ($ehOrientador) {
            abort_if($this->autoavaliacaoPendente($banca, $vinculo->id), 403, 'Conclua sua autoavaliação para finalizar a banca e liberar a ata e a folha de aprovação.');

            return;
        }

        // Membros da banca recebem a ata e a folha quando o presidente finaliza.
        $ehAvaliador = $vinculo && $banca->ata_enviada_em !== null && $banca->avaliadores()
            ->where('docente_programa_vinculo_id', $vinculo->id)
            ->exists();

        abort_unless($ehAvaliador, 403, 'Apenas o presidente da banca, a Secretaria e, depois do envio, os membros da banca acessam este documento.');
    }

    /** Na defesa, a ata e a folha só são liberadas depois da autoavaliação do orientador. */
    private function autoavaliacaoPendente(Banca $banca, int $vinculoId): bool
    {
        return $banca->tipo === 'defesa'
            && $banca->status !== 'finalizada'
            && ! AutoavaliacaoOrientador::where('banca_id', $banca->id)->where('docente_programa_vinculo_id', $vinculoId)->exists();
    }
}
