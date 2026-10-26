<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarSubmissaoLatexJob;
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

        $request->validate([
            'tipo_documento' => ['required', 'in:qualificacao,dissertacao,tese'],
            'zip' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ], [
            'tipo_documento.required' => 'Selecione o tipo de documento.',
            'zip.required' => 'Selecione o arquivo .zip do projeto LaTeX.',
            'zip.mimes' => 'O arquivo deve ser um .zip com o projeto LaTeX.',
            'zip.max' => 'O arquivo não pode ultrapassar 100 MB.',
        ]);

        $trabalho = $this->trabalhoDoDiscente();
        if (! $trabalho) {
            return back()->withErrors(['zip' => 'Proponha a banca antes de enviar o trabalho.'])->withInput();
        }

        if (! $trabalho->programa?->temTemplateLatex()) {
            return back()->withErrors(['zip' => 'O programa não tem template LaTeX cadastrado; envie o trabalho em PDF.'])->withInput();
        }

        $banca = $this->pedidoHomologacao->bancaDoPedido($trabalho);
        if (! $banca) {
            return back()->withErrors(['zip' => 'Proponha a banca antes de enviar o trabalho.'])->withInput();
        }

        $tipo = $request->string('tipo_documento')->toString();
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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $submissao->status,
                'html' => view('meu-trabalho.partials.submissao-latex-resultado', compact('submissao', 'vigente', 'podeEnviar'))->render(),
            ]);
        }

        return view('meu-trabalho.submissao-latex', compact('submissao', 'vigente', 'podeEnviar'));
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
            return back()->withErrors([
                'homologacao' => 'Só a submissão mais recente, com verificação "ok", pode ser enviada à homologação.',
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
        return $vigente && $submissao->status === 'ok' && $submissao->pdf_banca_path !== null;
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
}
