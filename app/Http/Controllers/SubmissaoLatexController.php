<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarSubmissaoLatexJob;
use App\Models\Banca;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\TrabalhoSubmissaoLatex;
use App\Services\ArquivoTrabalhoService;
use App\Services\PedidoHomologacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Submissão do projeto LaTeX (.zip) pelo discente de programa com template (Fluxo, passos 2-4). */
class SubmissaoLatexController extends Controller
{
    public function __construct(
        private readonly PedidoHomologacaoService $pedidoHomologacao,
        private readonly ArquivoTrabalhoService $arquivoService,
    ) {}

    public function store(Request $request)
    {
        abort_unless(auth()->user()->categoria === 'discente', 403);

        $regrasDeclaracoes = [];
        $mensagensDeclaracoes = [];
        foreach (array_keys(TrabalhoSubmissaoLatex::DECLARACOES) as $chave) {
            $regrasDeclaracoes["declaracoes.{$chave}"] = ['accepted'];
            $mensagensDeclaracoes["declaracoes.{$chave}.accepted"] = 'Marque esta declaração para enviar o projeto.';
        }

        $request->validate([
            'tipo_documento' => ['nullable', 'in:qualificacao,dissertacao,tese'],
            'zip' => ['required', 'file', 'mimes:zip', 'max:102400'],
            ...$regrasDeclaracoes,
        ], [
            ...$mensagensDeclaracoes,
            'zip.required' => 'Selecione o arquivo .zip do projeto LaTeX.',
            'zip.mimes' => 'O arquivo deve ser um .zip com o projeto LaTeX.',
            'zip.max' => 'O arquivo não pode ultrapassar 100 MB.',
        ]);

        $trabalho = $this->trabalhoDoDiscente();
        if (! $trabalho) {
            return back()->withErrors(['zip' => 'Proponha a banca antes de enviar o trabalho.'])->withInput();
        }

        $banca = $this->pedidoHomologacao->bancaDoPedido($trabalho);
        if (! $banca) {
            return back()->withErrors(['zip' => 'Proponha a banca antes de enviar o trabalho.'])->withInput();
        }

        // O discente só envia o .zip: o tipo vem da banca (qualificação) ou do nível do trabalho (dissertação/tese).
        $tipo = $request->filled('tipo_documento')
            ? $request->string('tipo_documento')->toString()
            : self::tipoDocumentoDaBanca($banca, $trabalho);
        if (! in_array($tipo, PedidoHomologacaoService::TIPOS_DOCUMENTO[$banca->tipo] ?? [], true)) {
            return back()->withErrors([
                'tipo_documento' => $banca->tipo === 'defesa'
                    ? 'Para a banca de defesa, envie a dissertação ou a tese.'
                    : 'Para a banca de qualificação, envie o documento de qualificação.',
            ])->withInput();
        }

        $arquivo = $request->file('zip');

        $submissao = DB::transaction(function () use ($trabalho, $banca, $tipo, $arquivo) {
            $submissao = TrabalhoSubmissaoLatex::create([
                'trabalho_academico_id' => $trabalho->id,
                'banca_id' => $banca->id,
                'enviado_por_id' => auth()->id(),
                'tipo_documento' => $tipo,
                'declaracoes' => array_fill_keys(array_keys(TrabalhoSubmissaoLatex::DECLARACOES), true),
                'declarado_em' => now(),
                'idioma' => 'pt',
                'zip_path' => '',
                'zip_hash_sha256' => hash_file('sha256', $arquivo->path()),
                'status' => 'processando',
            ]);

            $caminho = $submissao->diretorioArquivos().'/projeto.zip';
            Storage::disk('local')->put($caminho, file_get_contents($arquivo->path()));
            $submissao->update(['zip_path' => $caminho]);

            return $submissao;
        });

        ProcessarSubmissaoLatexJob::dispatch($submissao);

        return redirect()
            ->route('meu-trabalho.submissao-latex.show', $submissao)
            ->with('success', 'Projeto LaTeX enviado. A verificação automática está em andamento.');
    }

    public function show(Request $request, TrabalhoSubmissaoLatex $submissao)
    {
        $this->autorizarDono($submissao);

        $submissao->load('banca', 'documento');
        $vigente = $submissao->ehVigente();
        $podeEnviar = $this->podeEnviar($submissao, $vigente);
        $emAndamento = $this->verificacaoEmAndamento($submissao);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $submissao->status,
                'refs_status' => $submissao->refs_status,
                'em_andamento' => $emAndamento,
                'html' => view('meu-trabalho.partials.submissao-latex-resultado', compact('submissao', 'vigente', 'podeEnviar'))->render(),
            ]);
        }

        return view('meu-trabalho.submissao-latex', compact('submissao', 'vigente', 'podeEnviar', 'emAndamento'));
    }

    public function pdf(TrabalhoSubmissaoLatex $submissao, string $versao)
    {
        $usuario = auth()->user();
        $dono = $this->ehDono($submissao);

        if ($versao === 'conferencia') {
            // traz o apêndice das referências: só o discente vê (regra 8)
            abort_unless($dono, 403);
            $caminho = $submissao->pdf_conferencia_path;
        } else {
            if (! $dono) {
                if ($usuario->categoria === 'sec') {
                    $this->garantirProgramaDaSecretaria($submissao->trabalhoAcademico?->programa_id);
                } elseif (! $this->ehMembroDaBanca($submissao, $usuario->id)) {
                    abort(403);
                }
            }
            $caminho = $submissao->pdf_banca_path;
        }

        abort_unless($caminho && Storage::disk('local')->exists($caminho), 404);

        return response()->file(Storage::disk('local')->path($caminho), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="trabalho-'.$versao.'-'.$submissao->id.'.pdf"',
        ]);
    }

    /** Pedido único: banca do pedido + o PDF numerado desta submissão vão à homologação da Secretaria. */
    public function enviarHomologacao(TrabalhoSubmissaoLatex $submissao)
    {
        $this->autorizarDono($submissao);

        if (! $this->podeEnviar($submissao, $submissao->ehVigente())) {
            $mensagem = $this->referenciasEmAndamento($submissao)
                ? 'Aguarde a conclusão da conferência das referências antes de enviar à homologação.'
                : 'Só a submissão mais recente, com verificação "ok", pode ser enviada à homologação.';

            return back()->withErrors([
                'homologacao' => $mensagem,
            ]);
        }

        $trabalho = $submissao->trabalhoAcademico;
        $banca = $this->pedidoHomologacao->bancaDoPedido($trabalho);
        if (! $banca || $banca->id !== (int) $submissao->banca_id) {
            return back()->withErrors([
                'homologacao' => 'A banca do pedido não é a mesma verificada nesta submissão. Envie o projeto novamente.',
            ]);
        }

        // A conformidade foi conferida contra a composição de então (membros, data, local).
        if ($submissao->banca_composicao_hash === null
            || ! hash_equals($submissao->banca_composicao_hash, (string) TrabalhoSubmissaoLatex::impressaoBanca($banca))) {
            return back()->withErrors([
                'homologacao' => 'A banca mudou depois da verificação. Envie o projeto novamente.',
            ]);
        }

        $novoArquivo = null;

        try {
            DB::transaction(function () use ($submissao, $trabalho, $banca, &$novoArquivo) {
                $documento = $submissao->documento()->first();

                if (! $documento) {
                    $tipo = $submissao->tipo_documento;
                    $versao = (int) TrabalhoDocumento::where('trabalho_academico_id', $trabalho->id)
                        ->where('tipo_documento', $tipo)->max('versao') + 1;
                    $nome = $this->arquivoService->gerarNomePdf(auth()->user(), $tipo, $versao);
                    $novoArquivo = $this->arquivoService->gerarPathPdf($trabalho->id, $nome);

                    $disco = Storage::disk('local');
                    $disco->copy($submissao->pdf_banca_path, $novoArquivo);
                    $fisico = $disco->path($novoArquivo);

                    $documento = TrabalhoDocumento::create([
                        'trabalho_academico_id' => $trabalho->id,
                        'submissao_latex_id' => $submissao->id,
                        'enviado_por_id' => auth()->id(),
                        'tipo_documento' => $tipo,
                        'versao' => $versao,
                        'arquivo_pdf_path' => $novoArquivo,
                        'arquivo_pdf_nome_original' => $nome,
                        'arquivo_pdf_mime' => 'application/pdf',
                        'arquivo_pdf_tamanho' => filesize($fisico),
                        'arquivo_pdf_hash_sha256' => hash_file('sha256', $fisico),
                        'status_analise' => 'concluida',
                        'enviado_em' => now(),
                    ]);
                }

                $this->pedidoHomologacao->enviar($banca, $documento);
            });
        } catch (Throwable $e) {
            if ($novoArquivo !== null) {
                Storage::disk('local')->delete($novoArquivo);
            }
            throw $e;
        }

        return redirect()->route('meu-trabalho.index')
            ->with('success', 'Pedido enviado à homologação da Secretaria.');
    }

    private function podeEnviar(TrabalhoSubmissaoLatex $submissao, bool $vigente): bool
    {
        if (! $vigente || $submissao->status !== 'ok' || $submissao->pdf_banca_path === null) {
            return false;
        }

        // O botão de envio à homologação só abre após a conclusão da conferência das referências
        // (que orienta o estudante, sem impedir o envio após concluída).
        return ! $this->referenciasEmAndamento($submissao);
    }

    private function referenciasEmAndamento(TrabalhoSubmissaoLatex $submissao): bool
    {
        return $submissao->bbl_path !== null
            && in_array($submissao->refs_status, ['pendente', 'processando'], true);
    }

    private function verificacaoEmAndamento(TrabalhoSubmissaoLatex $submissao): bool
    {
        return $submissao->status === 'processando'
            || ($submissao->status === 'ok' && $this->referenciasEmAndamento($submissao));
    }

    private function trabalhoDoDiscente(): ?TrabalhoAcademico
    {
        $usuario = auth()->user()->load('discenteProfile.vinculosPrograma.trabalhoAcademico.programa');

        return $usuario->discenteProfile?->vinculosPrograma?->first()?->trabalhoAcademico;
    }

    private function ehDono(TrabalhoSubmissaoLatex $submissao): bool
    {
        if (auth()->user()->categoria !== 'discente') {
            return false;
        }
        $trabalho = $this->trabalhoDoDiscente();

        return $trabalho !== null && $trabalho->id === (int) $submissao->trabalho_academico_id;
    }

    private function autorizarDono(TrabalhoSubmissaoLatex $submissao): void
    {
        abort_unless($this->ehDono($submissao), 403);
    }

    private function ehMembroDaBanca(TrabalhoSubmissaoLatex $submissao, int $userId): bool
    {
        return $submissao->banca?->avaliadores()
            ->whereHas('docenteProgramaVinculo.docenteProfile', fn ($q) => $q->where('user_id', $userId))
            ->exists() ?? false;
    }

    public static function tipoDocumentoDaBanca(Banca $banca, TrabalhoAcademico $trabalho): string
    {
        if ($banca->tipo === 'qualificacao') {
            return 'qualificacao';
        }

        return $trabalho->nivel === 'doutorado' ? 'tese' : 'dissertacao';
    }
}
