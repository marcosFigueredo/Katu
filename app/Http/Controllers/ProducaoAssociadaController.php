<?php

namespace App\Http\Controllers;

use App\Jobs\VerificarProducaoOpenAlexJob;
use App\Models\ProducaoExtensao;
use App\Models\ProducaoPopularizacao;
use App\Models\ProducaoPublicacao;
use App\Models\TrabalhoAcademico;
use App\Services\PedidoHomologacaoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Produção associada à dissertação (publicações, extensão, popularização), informada pelo estudante. */
class ProducaoAssociadaController extends Controller
{
    public function __construct(
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {}

    public function index()
    {
        $trabalho = $this->trabalho();
        $publicacoes = $trabalho->publicacoes()->orderByDesc('ano')->get();
        $extensoes = $trabalho->extensoes()->orderByDesc('inicio')->get();
        $popularizacoes = $trabalho->popularizacoes()->orderByDesc('data')->get();
        $minimo = $this->pedidoHomologacao->minimoPublicacoes($trabalho);
        $travado = $this->pedidoHomologacao->defesaEnviada($trabalho);

        // Obras do OpenAlex (Bibliometria) que ainda não estão na lista.
        $doisJaAdicionados = $publicacoes->pluck('doi')->filter()->map(fn ($d) => mb_strtolower($d))->all();
        $idsJaAdicionados = $publicacoes->pluck('openalex_id')->filter()->all();
        $sugestoes = collect($trabalho->indicador?->dados_json ?? [])
            ->reject(fn ($obra) => in_array(mb_strtolower((string) ($obra['doi'] ?? '')), $doisJaAdicionados, true)
                || in_array($obra['id'] ?? null, $idsJaAdicionados, true))
            ->values();

        return view('meu-trabalho.producao', compact(
            'trabalho', 'publicacoes', 'extensoes', 'popularizacoes', 'minimo', 'travado', 'sugestoes'
        ));
    }

    public function store(Request $request, string $tipo)
    {
        [$classe, $regras] = $this->tipo($tipo);
        $trabalho = $this->trabalho();
        $this->exigirDestravado($trabalho);

        if ($tipo === 'publicacao' && $request->filled('doi')) {
            $request->merge(['doi' => preg_replace('#^https?://(dx\.)?doi\.org/#i', '', trim($request->input('doi')))]);
        }

        $dados = $request->validate($regras($request), [
            'canal.required' => 'Informe o canal de publicação.',
            'canal.in' => 'Escolha um canal compatível com o tipo de publicação.',
            'fim.after_or_equal' => 'O fim não pode ser antes do início.',
        ]);

        if ($tipo === 'publicacao' && $dados['tipo'] === 'artigo') {
            $dados['canal'] = null;
        }

        $item = DB::transaction(function () use ($classe, $dados, $trabalho) {
            $trabalho->update(['producao_confirmada_em' => null]);

            return $classe::create($dados + ['trabalho_academico_id' => $trabalho->id]);
        });

        // Conferência informativa no OpenAlex, na fila (não encontrar não invalida a produção).
        if ($tipo === 'publicacao' && config('services.openalex.verificar', true)) {
            VerificarProducaoOpenAlexJob::dispatch($item->id);
        }

        return redirect()->route('meu-trabalho.producao.index')->with('success', 'Item adicionado. Confirme a lista novamente quando estiver completa.');
    }

    public function destroy(string $tipo, int $id)
    {
        [$classe] = $this->tipo($tipo);
        $trabalho = $this->trabalho();
        /** @var Model $item */
        $item = $classe::where('trabalho_academico_id', $trabalho->id)->findOrFail($id);
        $this->exigirDestravado($trabalho);

        if ($item instanceof ProducaoPublicacao && $item->conferida_em !== null) {
            throw ValidationException::withMessages(['publicacao' => 'Publicação já conferida pela Secretaria não pode ser excluída.']);
        }

        DB::transaction(function () use ($item, $trabalho) {
            $item->delete();
            $trabalho->update(['producao_confirmada_em' => null]);
        });

        return redirect()->route('meu-trabalho.producao.index')->with('success', 'Item excluído. Confirme a lista novamente quando estiver completa.');
    }

    public function confirmar()
    {
        $trabalho = $this->trabalho();
        $this->exigirDestravado($trabalho);

        $trabalho->update(['producao_confirmada_em' => now()]);

        return redirect()->route('meu-trabalho.producao.index')->with('success', 'Produção associada confirmada.');
    }

    /** @return array{0: class-string<Model>, 1: \Closure(Request): array} */
    private function tipo(string $tipo): array
    {
        $ano = (int) now()->year + 1;

        return match ($tipo) {
            'publicacao' => [ProducaoPublicacao::class, fn (Request $r) => [
                'tipo' => ['required', Rule::in(array_keys(ProducaoPublicacao::TIPOS))],
                'canal' => $r->input('tipo') === 'artigo' || ! isset(ProducaoPublicacao::CANAIS[$r->input('tipo')])
                    ? ['nullable']
                    : ['required', Rule::in(array_keys(ProducaoPublicacao::CANAIS[$r->input('tipo')]))],
                'titulo' => ['required', 'string', 'max:500'],
                'ano' => ['required', 'integer', 'min:1950', "max:{$ano}"],
                'veiculo' => ['required', 'string', 'max:300'],
                'doi' => ['nullable', 'string', 'max:255'],
                'issn' => ['nullable', 'string', 'max:20'],
                'openalex_id' => ['nullable', 'string', 'max:100'],
            ]],
            'extensao' => [ProducaoExtensao::class, fn () => [
                'tipo' => ['required', Rule::in(array_keys(ProducaoExtensao::TIPOS))],
                'titulo' => ['required', 'string', 'max:500'],
                'publico' => ['required', 'integer', 'min:0'],
                'parceiro' => ['required', 'string', 'max:300'],
                'carga_horaria' => ['required', 'integer', 'min:0'],
                'inicio' => ['required', 'date'],
                'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
                'registro_proex' => ['nullable', 'string', 'max:60'],
                'produto_entregue' => ['nullable', 'string', 'max:500'],
                'link' => ['nullable', 'url', 'max:500'],
            ]],
            'popularizacao' => [ProducaoPopularizacao::class, fn () => [
                'tipo' => ['required', Rule::in(array_keys(ProducaoPopularizacao::TIPOS))],
                'titulo' => ['required', 'string', 'max:500'],
                'veiculo' => ['required', 'string', 'max:300'],
                'alcance' => ['required', 'integer', 'min:0'],
                'data' => ['required', 'date'],
                'link' => ['nullable', 'url', 'max:500'],
            ]],
            default => abort(404),
        };
    }

    private function exigirDestravado(TrabalhoAcademico $trabalho): void
    {
        if ($this->pedidoHomologacao->defesaEnviada($trabalho)) {
            throw ValidationException::withMessages(['travado' => TrabalhoImpactoController::MENSAGEM_TRAVADO]);
        }
    }

    private function trabalho(): TrabalhoAcademico
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'discente', 403);

        $trabalho = $user->discenteProfile?->vinculosPrograma()->with('trabalhoAcademico')->get()
            ->pluck('trabalhoAcademico')->filter()->first();
        abort_unless($trabalho, 404);

        return $trabalho;
    }
}
