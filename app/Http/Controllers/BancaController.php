<?php

namespace App\Http\Controllers;

use App\Jobs\SincronizarOrcidBancaJob;
use App\Models\Banca;
use App\Models\BancaIndicador;
use App\Models\BancaMembro;
use App\Models\DocenteProgramaVinculo;
use App\Models\TrabalhoAcademico;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\BancaNotificacaoService;
use App\Services\HomologacaoBancaService;
use App\Services\PedidoHomologacaoService;
use App\Services\VerificacaoBancaService;

class BancaController extends Controller
{
    public function __construct(
        private readonly VerificacaoBancaService $verificacao,
        private readonly BancaNotificacaoService $notificacao,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    /** Resolve o trabalho acadêmico do discente autenticado, com as relações usadas pelo formulário de banca. */
    private function trabalhoDoDiscenteAutenticado(): ?TrabalhoAcademico
    {
        $usuario = auth()->user()->load([
            'discenteProfile.vinculosPrograma.programa',
            'discenteProfile.vinculosPrograma.linhaPesquisa',
            'discenteProfile.vinculosPrograma.trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'discenteProfile.vinculosPrograma.trabalhoAcademico.bancas',
        ]);

        return $usuario->discenteProfile?->vinculosPrograma?->first()?->trabalhoAcademico;
    }

    /**
     * Autoriza acesso a uma banca existente (edit/update) e informa se o autor é o discente.
     * Secretaria: acesso completo dentro do próprio programa. Discente: apenas a própria
     * banca, e só enquanto o pedido estiver com ele (proposta ou devolvida); na fila da
     * Secretaria (aguardando) ou homologada, não edita.
     */
    private function autorizarAcessoBanca(Banca $banca): bool
    {
        $user = auth()->user();

        if ($user->categoria === 'sec') {
            $this->garantirProgramaDaSecretaria($banca->trabalhoAcademico?->programa_id);

            return false;
        }

        if ($user->categoria === 'discente') {
            $trabalho = $this->trabalhoDoDiscenteAutenticado();
            abort_unless($trabalho && $trabalho->id === $banca->trabalho_academico_id, 403);
            abort_unless(in_array($banca->homologacao_status, PedidoHomologacaoService::ESTADOS_EDITAVEIS, true), 403);

            return true;
        }

        abort(403);
    }

    public function index()
    {
        $this->autorizarSecretaria();

        // A Secretaria vê as bancas solicitadas pelos discentes; propostas ainda não enviadas ficam com o discente.
        $bancas = Banca::query()
            ->whereHas('trabalhoAcademico', fn ($q) => $q->where('programa_id', $this->programaDaSecretaria()->id))
            ->whereIn('homologacao_status', ['aguardando', 'devolvida', 'homologada'])
            ->where('status', '!=', 'cancelada')
            ->with([
                'trabalhoAcademico.discenteVinculo.discenteProfile.user',
                'trabalhoAcademico.programa',
                'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
                'membros.docenteProgramaVinculo.docenteProfile.user',
                'documentoHomologacao.submissaoLatex',
                'respostasCpa',
                'homologadaPor',
                'devolvidaPor',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $aguardando = $bancas->where('homologacao_status', 'aguardando')->values();
        $devolvidas = $bancas->where('homologacao_status', 'devolvida')->values();
        $homologadas = $bancas->where('homologacao_status', 'homologada')->where('status', '!=', 'finalizada')->values();
        $finalizadas = $bancas->where('status', 'finalizada')->where('homologacao_status', 'homologada')->values();

        $homologacao = app(HomologacaoBancaService::class);
        $analises = $aguardando->mapWithKeys(fn (Banca $banca) => [$banca->id => $homologacao->podeHomologar($banca)]);

        return view('bancas.index', compact('aguardando', 'devolvidas', 'homologadas', 'finalizadas', 'analises'));
    }

    public function create(Request $request)
    {
        $modoDiscente = auth()->user()->categoria === 'discente';

        if ($modoDiscente) {
            $trabalho = $this->trabalhoDoDiscenteAutenticado();
            abort_unless($trabalho, 404);
            $trabalhos = collect([$trabalho]);
        } else {
            $this->autorizarSecretaria();

            $trabalho = null;

            if ($request->filled('trabalho_id')) {
                $trabalho = TrabalhoAcademico::with([
                    'discenteVinculo.discenteProfile.user',
                    'discenteVinculo.linhaPesquisa',
                    'programa',
                    'orientacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tipo')->orderBy('ordem'),
                    'orientacoes.docenteProgramaVinculo.docenteProfile.user',
                    'bancas',
                ])->findOrFail($request->integer('trabalho_id'));
                $this->garantirProgramaDaSecretaria($trabalho->programa_id);
            }

            $trabalhos = TrabalhoAcademico::with([
                'discenteVinculo.discenteProfile.user',
                'orientacoes' => fn ($q) => $q->where('ativo', true),
                'bancas',
            ])
                ->where('programa_id', $this->programaDaSecretaria()->id)
                ->orderByDesc('created_at')
                ->get()
                ->filter(fn (TrabalhoAcademico $item) =>
                    $item->orientacoes->firstWhere('tipo', 'orientador') &&
                    $item->bancas->count() < 2
                );
        }

        $avaliadores = $trabalho ? $this->avaliadoresDisponiveis($trabalho) : collect();
        $avaliadoresAtuais = collect();
        $banca = new Banca(['tipo' => 'qualificacao', 'status' => 'cadastrada', 'libera_envio_trabalho' => false]);

        return view('bancas.form', $this->dadosFormulario($banca, $trabalho, $trabalhos, $avaliadores, $avaliadoresAtuais)
            + ['indicadores' => null, 'modoDiscente' => $modoDiscente]);
    }

    public function store(Request $request)
    {
        $modoDiscente = auth()->user()->categoria === 'discente';

        if ($modoDiscente) {
            $trabalhoDiscente = $this->trabalhoDoDiscenteAutenticado();
            abort_unless($trabalhoDiscente, 404);
            $request->merge([
                'trabalho_academico_id' => $trabalhoDiscente->id,
                'status' => 'cadastrada',
            ]);
        } else {
            $this->autorizarSecretaria();
        }

        $data = $this->validar($request);
        $trabalho = TrabalhoAcademico::with(['orientacoes.docenteProgramaVinculo.docenteProfile.user', 'bancas'])
            ->findOrFail($data['trabalho_academico_id']);

        if ($modoDiscente) {
            abort_unless($trabalho->id === $trabalhoDiscente->id, 403);
        } else {
            $this->garantirProgramaDaSecretaria($trabalho->programa_id);
        }

        if ($trabalho->bancas->where('tipo', $data['tipo'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'trabalho_academico_id' => 'Este trabalho já possui banca de ' . ($data['tipo'] === 'qualificacao' ? 'qualificação' : 'defesa') . ' cadastrada.',
            ]);
        }

        $qualificacao = $trabalho->bancas->where('tipo', 'qualificacao')->where('status', 'finalizada')->first();
        if ($data['tipo'] === 'defesa' &&
            (! $qualificacao || ! in_array($qualificacao->resultado, ['aprovado', 'aprovado_modificacoes'], true))
        ) {
            throw ValidationException::withMessages([
                'tipo' => 'A banca de defesa só pode ser cadastrada após aprovação na qualificação.',
            ]);
        }

        $this->validarComposicao($trabalho, $data['tipo'], collect($data['avaliadores'] ?? []));
        $dataHora = $this->montarDataHora($data);

        // Homologação única (regra 9): a banca nasce "proposta", seja do discente ou da Secretaria,
        // e só é homologada depois que o discente envia o trabalho no pedido.
        $homologacao = ['homologacao_status' => 'proposta', 'homologada_por' => null, 'homologada_em' => null, 'libera_envio_trabalho' => false];

        $banca = DB::transaction(function () use ($data, $dataHora, $homologacao) {
            $banca = Banca::create(array_merge([
                'trabalho_academico_id' => $data['trabalho_academico_id'],
                'tipo' => $data['tipo'],
                'status' => $data['status'],
                'data_hora' => $dataHora,
                'local' => $data['local'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
            ], $homologacao));

            $this->sincronizarAvaliadores($banca, collect($data['avaliadores'] ?? []));

            return $banca;
        });

        SincronizarOrcidBancaJob::dispatch($banca);

        if ($modoDiscente) {
            return redirect()
                ->route('meu-trabalho.index')
                ->with('success', 'Banca proposta. Agora envie o trabalho e, em seguida, o pedido à homologação.');
        }

        if ($request->boolean('enviar_email_participantes')) {
            $this->notificacao->enviarCadastro($banca);
        }

        return redirect()
            ->route('bancas.edit', $banca)
            ->with('success', $request->boolean('enviar_email_participantes')
                ? 'Banca cadastrada com sucesso. E-mail enviado aos participantes.'
                : 'Banca cadastrada como proposta. O discente envia o trabalho e o pedido à homologação.');
    }

    public function edit(Banca $banca)
    {
        $modoDiscente = $this->autorizarAcessoBanca($banca);

        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.discenteVinculo.linhaPesquisa',
            'trabalhoAcademico.programa',
            'trabalhoAcademico.orientacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tipo')->orderBy('ordem'),
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $trabalho = $banca->trabalhoAcademico;
        $trabalhos = collect([$trabalho]);
        $avaliadores = $this->avaliadoresDisponiveis($trabalho);
        $avaliadoresAtuais = $banca->membros
            ->where('papel', 'avaliador')
            ->pluck('docente_programa_vinculo_id');

        $indicadores = BancaIndicador::where('banca_id', $banca->id)->first();

        return view('bancas.form', $this->dadosFormulario($banca, $trabalho, $trabalhos, $avaliadores, $avaliadoresAtuais)
            + compact('indicadores') + ['modoDiscente' => $modoDiscente]);
    }

    public function update(Request $request, Banca $banca)
    {
        $modoDiscente = $this->autorizarAcessoBanca($banca);

        if ($modoDiscente) {
            $request->merge(['status' => 'cadastrada']);
        }

        $data = $this->validar($request, $banca);
        $banca->load('trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user');

        $this->validarComposicao($banca->trabalhoAcademico, $data['tipo'], collect($data['avaliadores'] ?? []));
        $dataHora = $this->montarDataHora($data);

        // Discente: a proposta segue com ele. Uma banca devolvida continua "devolvida"
        // (com a não conformidade visível) até o reenvio, que conta nova tentativa.
        $homologacao = $modoDiscente
            ? [
                'homologacao_status' => $banca->homologacao_status === 'devolvida' ? 'devolvida' : 'proposta',
                'homologada_por' => null,
                'homologada_em' => null,
                'libera_envio_trabalho' => false,
            ]
            : []; // Secretaria: editar não muda o estado da homologação.

        DB::transaction(function () use ($banca, $data, $dataHora, $homologacao) {
            $banca->update(array_merge([
                'tipo' => $data['tipo'],
                'status' => $data['status'],
                'data_hora' => $dataHora,
                'local' => $data['local'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
            ], $homologacao));

            $banca->membros()->delete();
            $this->sincronizarAvaliadores($banca, collect($data['avaliadores'] ?? []));
        });

        SincronizarOrcidBancaJob::dispatch($banca);

        if ($modoDiscente) {
            return redirect()
                ->route('meu-trabalho.index')
                ->with('success', 'Banca atualizada. Envie o pedido à homologação quando o trabalho estiver pronto.');
        }

        if ($request->boolean('enviar_email_participantes')) {
            $this->notificacao->enviarCadastro($banca);
        }

        return redirect()
            ->route('bancas.edit', $banca)
            ->with('success', $request->boolean('enviar_email_participantes')
                ? 'Banca atualizada com sucesso. E-mail enviado aos participantes.'
                : 'Banca atualizada com sucesso.');
    }

    public function destroy(Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($banca->trabalhoAcademico?->programa_id);

        $banca->delete();

        return redirect()
            ->route('bancas.index')
            ->with('success', 'Banca excluída. O discente deixa de estar liberado para novos envios até novo cadastro.');
    }

    public function reabrir(Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($banca->trabalhoAcademico?->programa_id);

        if ($banca->status !== 'finalizada') {
            return back()->withErrors(['status' => 'Apenas bancas finalizadas podem ser reabertas.']);
        }

        $banca->update(['status' => 'em_avaliacao']);

        return back()->with('success', 'Banca reaberta. Os avaliadores podem corrigir suas avaliações.');
    }

    private function validar(Request $request, ?Banca $banca = null): array
    {
        $rules = [
            'tipo' => ['required', 'in:qualificacao,defesa'],
            'status' => ['required', 'in:cadastrada,em_avaliacao,finalizada,cancelada'],
            'data_banca' => ['nullable', 'date_format:d/m/Y', 'required_with:horario_banca'],
            'horario_banca' => ['nullable', 'date_format:H:i', 'required_with:data_banca'],
            'local' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'quantidade_avaliadores' => ['required', 'integer', 'min:1', 'max:5'],
            'avaliador_interno_id' => ['nullable', 'exists:docente_programa_vinculos,id'],
            'avaliador_externo_id' => ['nullable', 'exists:docente_programa_vinculos,id'],
            'avaliadores_adicionais' => ['nullable', 'array'],
            'avaliadores_adicionais.*' => ['nullable', 'exists:docente_programa_vinculos,id'],
        ];

        if (! $banca?->exists) {
            $rules['trabalho_academico_id'] = ['required', 'exists:trabalhos_academicos,id'];
        }

        $data = $request->validate($rules, [
            'trabalho_academico_id.required' => 'Selecione o trabalho/discente.',
            'data_banca.date_format' => 'Informe a data no formato DD/MM/AAAA.',
            'data_banca.required_with' => 'Informe a data da banca.',
            'horario_banca.date_format' => 'Informe o horário no formato HH:MM, de 00:00 a 23:59.',
            'horario_banca.required_with' => 'Informe o horário da banca.',
            'quantidade_avaliadores.required' => 'Informe quantos avaliadores a banca terá.',
            'quantidade_avaliadores.min' => 'A banca deve ter pelo menos 1 avaliador.',
            'quantidade_avaliadores.max' => 'A banca deve ter no máximo 5 avaliadores.',
            'avaliador_interno_id.exists' => 'Avaliador interno inválido.',
            'avaliador_externo_id.exists' => 'Avaliador externo inválido.',
            'avaliadores_adicionais.*.exists' => 'Um ou mais avaliadores adicionais são inválidos.',
        ]);

        $data['avaliadores'] = $this->avaliadoresDaRequisicao($data);

        if (count($data['avaliadores']) !== (int) $data['quantidade_avaliadores']) {
            throw ValidationException::withMessages([
                'quantidade_avaliadores' => 'A quantidade informada deve corresponder ao número de avaliadores selecionados, sem repetir nomes.',
            ]);
        }

        return $data;
    }

    private function avaliadoresDaRequisicao(array $data): array
    {
        return collect([
            $data['avaliador_interno_id'] ?? null,
            $data['avaliador_externo_id'] ?? null,
        ])
            ->merge($data['avaliadores_adicionais'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function montarDataHora(array $data): ?Carbon
    {
        if (empty($data['data_banca']) && empty($data['horario_banca'])) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y H:i', "{$data['data_banca']} {$data['horario_banca']}");
    }

    private function validarComposicao(TrabalhoAcademico $trabalho, string $etapa, Collection $avaliadorIds): void
    {
        $orientador = $trabalho->orientacoes->firstWhere('tipo', 'orientador');

        if (! $orientador) {
            throw ValidationException::withMessages([
                'trabalho_academico_id' => 'Procure a Secretaria para registrar seu orientador antes de propor a banca.',
            ]);
        }

        $orientacaoIds = $trabalho->orientacoes->pluck('docente_programa_vinculo_id');
        $avaliadorIds = $avaliadorIds->map(fn ($id) => (int) $id)->unique()->values();

        if ($avaliadorIds->intersect($orientacaoIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'avaliadores' => 'Orientador e coorientadores constam na ata, mas não podem ser avaliadores.',
            ]);
        }

        $avaliadores = DocenteProgramaVinculo::with('docenteProfile')
            ->whereIn('id', $avaliadorIds)
            ->where('programa_id', $trabalho->programa_id)
            ->where('pode_avaliar', true)
            ->where('ativo', true)
            ->get();

        if ($avaliadores->count() !== $avaliadorIds->count()) {
            throw ValidationException::withMessages([
                'avaliadores' => 'Selecione apenas docentes ativos, vinculados ao programa e com permissão de avaliação.',
            ]);
        }

        $this->verificacao->exigir(
            $this->verificacao->verificar($trabalho, $etapa, $avaliadorIds),
            'avaliadores',
        );
    }

    private function sincronizarAvaliadores(Banca $banca, Collection $avaliadorIds): void
    {
        $avaliadorIds->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->each(function (int $vinculoId, int $index) use ($banca) {
                BancaMembro::create([
                    'banca_id' => $banca->id,
                    'docente_programa_vinculo_id' => $vinculoId,
                    'papel' => 'avaliador',
                    'ordem' => $index + 1,
                    'ativo' => true,
                ]);
            });
    }

    private function avaliadoresDisponiveis(TrabalhoAcademico $trabalho): Collection
    {
        $orientacaoIds = $trabalho->orientacoes->pluck('docente_programa_vinculo_id')->all();

        return DocenteProgramaVinculo::where('programa_id', $trabalho->programa_id)
            ->where('pode_avaliar', true)
            ->where('ativo', true)
            ->whereNotIn('id', $orientacaoIds)
            ->with('docenteProfile.user')
            ->get()
            ->sortBy('docenteProfile.user.name')
            ->values();
    }

    private function dadosFormulario(
        Banca $banca,
        ?TrabalhoAcademico $trabalho,
        Collection $trabalhos,
        Collection $avaliadores,
        Collection $avaliadoresAtuais,
    ): array {
        $internos = $avaliadores
            ->filter(fn (DocenteProgramaVinculo $vinculo) => $vinculo->tipo_vinculo === 'interno')
            ->values();

        $siglaInstituicao = $trabalho?->programa?->instituicao?->sigla;
        $externosUneb = $avaliadores
            ->filter(fn (DocenteProgramaVinculo $vinculo) => $this->verificacao->externoInstituicao($vinculo, $siglaInstituicao))
            ->values();
        $siglaPrograma = $trabalho?->programa?->sigla ?: 'programa';
        $siglaInstituicao = $siglaInstituicao ?: 'instituição';
        $regra = $trabalho ? $this->verificacao->regraPara($trabalho, $banca->tipo ?? 'qualificacao') : null;

        $primeiroInterno = $avaliadoresAtuais
            ->first(fn ($id) => $internos->contains('id', $id));

        $primeiroExterno = $avaliadoresAtuais
            ->first(fn ($id) => $externosUneb->contains('id', $id));

        $avaliadoresAdicionaisAtuais = $avaliadoresAtuais
            ->reject(fn ($id) => $id === $primeiroInterno || $id === $primeiroExterno)
            ->values()
            ->all();

        return compact(
            'banca',
            'trabalho',
            'trabalhos',
            'avaliadores',
            'avaliadoresAtuais',
            'internos',
            'externosUneb',
            'primeiroInterno',
            'primeiroExterno',
            'avaliadoresAdicionaisAtuais',
            'siglaPrograma',
            'siglaInstituicao',
            'regra',
        );
    }

}
