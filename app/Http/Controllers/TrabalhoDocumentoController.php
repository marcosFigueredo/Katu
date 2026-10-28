<?php

namespace App\Http\Controllers;

use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\DocumentoAnalise;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Services\ArquivoTrabalhoService;
use App\Services\ConformidadeNormasService;
use App\Services\DocumentoAnaliseService;
use App\Services\OllamaResumoService;
use App\Services\PedidoHomologacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class TrabalhoDocumentoController extends Controller
{
    public function __construct(
        private readonly ArquivoTrabalhoService $arquivoService,
        private readonly DocumentoAnaliseService $analiseService,
        private readonly OllamaResumoService $ollamaService,
        private readonly ConformidadeNormasService $conformidadeService,
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {}

    public function index()
    {
        $usuario = auth()->user()->load([
            'discenteProfile.vinculosPrograma.programa',
            'discenteProfile.vinculosPrograma.linhaPesquisa',
            'discenteProfile.vinculosPrograma.trabalhoAcademico.bancas',
            'discenteProfile.vinculosPrograma.trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
        ]);

        $vinculo  = $usuario->discenteProfile?->vinculosPrograma?->first();
        $trabalho = $vinculo?->trabalhoAcademico;

        $bancas = $trabalho?->bancas ?? collect();

        $documentos = $trabalho
            ? $trabalho->documentos()->with('ultimaAnalise.ollama')->latest('id')->get()
            : collect();

        // Banca em proposta|devolvida: o discente envia o trabalho e o pedido à homologação.
        $bancaPedido = $trabalho ? $this->pedidoHomologacao->bancaDoPedido($trabalho) : null;

        // O discente envia o projeto LaTeX (.zip); o template do programa, quando existe, fica para download.
        $programa = $vinculo?->programa;
        $temTemplateLatex = (bool) $programa?->temTemplateLatex();
        $submissoesLatex = $trabalho
            ? $trabalho->submissoesLatex()->latest('id')->limit(10)->get()
            : collect();

        return view('meu-trabalho.index', compact(
            'usuario', 'vinculo', 'trabalho', 'documentos', 'bancas', 'bancaPedido',
            'programa', 'temTemplateLatex', 'submissoesLatex'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo_documento' => ['required', 'in:qualificacao,dissertacao,tese'],
            'titulo'         => ['required', 'string', 'max:500'],
            'resumo'         => ['required', 'string'],
            'palavras_chave' => ['required', 'string', 'max:500'],
            'pdf'            => ['required', 'file', 'mimes:pdf', 'max:30720'],
        ], [
            'tipo_documento.required' => 'Selecione o tipo de documento.',
            'titulo.required'         => 'Informe o título do trabalho.',
            'titulo.max'              => 'O título não pode ultrapassar 500 caracteres.',
            'resumo.required'         => 'Informe o resumo do trabalho.',
            'palavras_chave.required' => 'Informe as palavras-chave.',
            'palavras_chave.max'      => 'As palavras-chave não podem ultrapassar 500 caracteres.',
            'pdf.required'            => 'Selecione um arquivo PDF.',
            'pdf.mimes'               => 'O arquivo deve ser um PDF.',
            'pdf.max'                 => 'O PDF não pode ultrapassar 30 MB.',
        ]);

        $usuario = auth()->user()->load('discenteProfile.vinculosPrograma.trabalhoAcademico.bancas');
        $vinculo = $usuario->discenteProfile?->vinculosPrograma?->first();

        if (! $vinculo) {
            return back()->withErrors(['pdf' => 'Vínculo acadêmico não encontrado. Procure a secretaria.']);
        }

        $trabalho = TrabalhoAcademico::firstOrCreate(
            ['discente_programa_vinculo_id' => $vinculo->id],
            [
                'programa_id'       => $vinculo->programa_id,
                'linha_pesquisa_id' => $vinculo->linha_pesquisa_id,
                'nivel'             => $vinculo->nivel ?? 'mestrado',
                'status'            => 'em_andamento',
                'data_inicio'       => $vinculo->data_ingresso,
            ]
        );

        if (! $this->pedidoHomologacao->bancaDoPedido($trabalho)) {
            return back()->withErrors([
                'pdf' => 'Proponha a banca antes de enviar o trabalho.',
            ])->withInput();
        }

        // Programa com template LaTeX: o trabalho vai como projeto .zip (SubmissaoLatexController).
        if ($trabalho->programa?->temTemplateLatex()) {
            return back()->withErrors([
                'pdf' => 'Seu programa usa o template LaTeX: envie o projeto em .zip.',
            ])->withInput();
        }

        // Atualiza metadados do trabalho com os dados informados pelo discente
        $trabalho->update([
            'titulo'         => $request->titulo,
            'resumo'         => $request->resumo,
            'palavras_chave' => $request->palavras_chave,
        ]);

        $tipo = $request->tipo_documento;

        // Remove versões anteriores do mesmo tipo (arquivo + registro)
        TrabalhoDocumento::where('trabalho_academico_id', $trabalho->id)
            ->where('tipo_documento', $tipo)
            ->each(function (TrabalhoDocumento $antigo) {
                Storage::disk('local')->delete($antigo->arquivo_pdf_path);
                $antigo->analises()->each(fn ($a) => $a->itens()->delete());
                $antigo->analises()->each(fn ($a) => $a->ollama()->delete());
                $antigo->analises()->delete();
                $antigo->delete();
            });

        $versao = 1;

        $arquivo    = $request->file('pdf');
        $nomeSeguro = $this->arquivoService->gerarNomePdf($usuario, $tipo, $versao);
        $destino    = $this->arquivoService->gerarPathPdf($trabalho->id, $nomeSeguro);

        Storage::disk('local')->put($destino, file_get_contents($arquivo->path()));
        $caminhoFisico = Storage::disk('local')->path($destino);

        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id'     => $trabalho->id,
            'enviado_por_id'            => $usuario->id,
            'tipo_documento'            => $tipo,
            'versao'                    => $versao,
            'arquivo_pdf_path'          => $destino,
            'arquivo_pdf_nome_original' => $arquivo->getClientOriginalName(),
            'arquivo_pdf_mime'          => $arquivo->getMimeType(),
            'arquivo_pdf_tamanho'       => filesize($caminhoFisico),
            'arquivo_pdf_hash_sha256'   => hash_file('sha256', $caminhoFisico),
            'status_analise'            => 'aguardando',
            'enviado_em'                => now(),
        ]);

        $analise = $this->analiseService->analisar($documento);

        $normasIndexadas = config('ia.conformidade_rag')
            && $analise->status_geral !== 'falha'
            && ProgramaDocumentoReferencia::where('programa_id', $trabalho->programa_id)
                ->where('tipo', 'normas')
                ->where('status_indexacao', 'concluido')
                ->exists();

        if ($normasIndexadas) {
            $this->conformidadeService->iniciar($analise);
            AnalisarConformidadeRagJob::dispatch($analise);
        }

        return redirect()
            ->route('meu-trabalho.analise', $analise)
            ->with('success', 'Documento enviado e analisado com sucesso.');
    }

    public function analise(DocumentoAnalise $analise)
    {
        $usuario  = auth()->user()->load('discenteProfile.vinculosPrograma.trabalhoAcademico');
        $trabalho = $usuario->discenteProfile?->vinculosPrograma?->first()?->trabalhoAcademico;

        if (! $trabalho || $analise->trabalhoDocumento->trabalho_academico_id !== $trabalho->id) {
            abort(403);
        }

        $analise->load(['itens', 'ollama', 'trabalhoDocumento', 'conformidadeRag']);

        return view('meu-trabalho.analise', compact('analise'));
    }

    public function download(TrabalhoDocumento $documento)
    {
        // Valida assinatura do URL (gerado por URL::temporarySignedRoute)
        if (! request()->hasValidSignature()) {
            abort(403, 'Link inválido ou expirado.');
        }

        $caminho = Storage::disk('local')->path($documento->arquivo_pdf_path);

        if (! file_exists($caminho)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return response()->download($caminho, $documento->arquivo_pdf_nome_original);
    }

    /** Envia o pedido (banca proposta + este documento) à homologação da Secretaria. */
    public function enviarHomologacao(TrabalhoDocumento $documento)
    {
        $usuario  = auth()->user()->load('discenteProfile.vinculosPrograma.trabalhoAcademico');
        $trabalho = $usuario->discenteProfile?->vinculosPrograma?->first()?->trabalhoAcademico;

        if (! $trabalho || $documento->trabalho_academico_id !== $trabalho->id) {
            abort(403);
        }

        // Programa com template LaTeX: só a submissão vigente com "ok" segue à homologação
        // (SubmissaoLatexController::enviarHomologacao); PDFs antigos não passam por aqui.
        if ($trabalho->programa?->temTemplateLatex()) {
            return back()->withErrors([
                'homologacao' => 'Seu programa usa o template LaTeX: envie à homologação pela submissão do projeto com verificação "ok".',
            ]);
        }

        $banca = $this->pedidoHomologacao->bancaDoPedido($trabalho);

        if (! $banca) {
            return back()->withErrors(['homologacao' => 'Não há banca proposta ou devolvida para enviar à homologação.']);
        }

        $this->pedidoHomologacao->enviar($banca, $documento);

        return back()->with('success', 'Pedido enviado à homologação da Secretaria.');
    }

    public function avaliarResumo(DocumentoAnalise $analise): JsonResponse
    {
        set_time_limit(0);

        $usuario  = auth()->user()->load('discenteProfile.vinculosPrograma.trabalhoAcademico');
        $trabalho = $usuario->discenteProfile?->vinculosPrograma?->first()?->trabalhoAcademico;

        if (! $trabalho || $analise->trabalhoDocumento->trabalho_academico_id !== $trabalho->id) {
            abort(403);
        }

        // Só roda se ainda estiver pendente
        $analise->load(['ollama', 'trabalhoDocumento.trabalhoAcademico.linhaPesquisa.areaConcentracao']);
        if ($analise->ollama?->status !== 'processando') {
            return response()->json(['status' => $analise->ollama?->status ?? 'nao_iniciado']);
        }

        $documento  = $analise->trabalhoDocumento;
        $caminhoPdf = Storage::disk('local')->path($documento->arquivo_pdf_path);

        $paginas = (new Parser())->parseFile($caminhoPdf)->getPages();
        $textosPorPagina = [];
        foreach ($paginas as $i => $pagina) {
            $textosPorPagina[$i + 1] = trim($pagina->getText());
        }

        $this->ollamaService->analisar(
            $analise,
            $textosPorPagina,
            $documento->trabalhoAcademico?->resumo,
            $documento->trabalhoAcademico?->palavras_chave,
            $documento->trabalhoAcademico?->linhaPesquisa,
        );

        return response()->json(['status' => 'concluida']);
    }
}
