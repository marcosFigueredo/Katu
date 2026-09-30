<?php

namespace App\Http\Controllers;

use App\Mail\TrabalhoEnviadoBancaMail;
use App\Models\DocumentoAnalise;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Services\ArquivoTrabalhoService;
use App\Services\DocumentoAnaliseService;
use App\Services\OllamaResumoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Smalot\PdfParser\Parser;

class TrabalhoDocumentoController extends Controller
{
    public function __construct(
        private readonly ArquivoTrabalhoService $arquivoService,
        private readonly DocumentoAnaliseService $analiseService,
        private readonly OllamaResumoService $ollamaService,
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

        $bancaLiberada = $bancas->contains(
            fn ($b) => $b->libera_envio_trabalho && $b->status !== 'cancelada'
        );

        return view('meu-trabalho.index', compact(
            'usuario', 'vinculo', 'trabalho', 'documentos', 'bancas', 'bancaLiberada'
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

        $trabalho->load('bancas');

        $bancaAtiva = $trabalho->bancas->first(
            fn (\App\Models\Banca $b) => $b->libera_envio_trabalho && $b->status !== 'cancelada'
        );

        if (! $bancaAtiva) {
            return back()->withErrors([
                'pdf' => 'O envio do trabalho ainda não foi liberado pela secretaria. Aguarde o cadastro da banca.',
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

        $analise->load(['itens', 'ollama', 'trabalhoDocumento']);

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

    public function enviarParaBanca(TrabalhoDocumento $documento)
    {
        $usuario  = auth()->user()->load('discenteProfile.vinculosPrograma.trabalhoAcademico.bancas.membros.docenteProgramaVinculo.docenteProfile.user');
        $trabalho = $usuario->discenteProfile?->vinculosPrograma?->first()?->trabalhoAcademico;

        if (! $trabalho || $documento->trabalho_academico_id !== $trabalho->id) {
            abort(403);
        }

        if (blank($usuario->discenteProfile?->orcid_url)) {
            return back()->withErrors(['orcid' => 'Informe seu ORCID no seu cadastro antes de enviar o trabalho para a banca.']);
        }

        $documento->load('trabalhoAcademico.discenteVinculo.discenteProfile.user');

        $banca = $trabalho->bancas
            ->where('status', '!=', 'cancelada')
            ->sortByDesc('id')
            ->first();

        if (! $banca) {
            return back()->withErrors(['banca' => 'Nenhuma banca ativa encontrada.']);
        }

        $avaliadores = $banca->membros
            ->where('papel', 'avaliador')
            ->filter(fn ($m) => $m->docenteProgramaVinculo?->docenteProfile?->user?->email);

        if ($avaliadores->isEmpty()) {
            return back()->with('warning', 'A banca não possui avaliadores com e-mail cadastrado.');
        }

        $urlDownload = URL::temporarySignedRoute(
            'meu-trabalho.download',
            now()->addDays(7),
            ['documento' => $documento->id],
        );

        foreach ($avaliadores as $membro) {
            $destinatario = $membro->docenteProgramaVinculo->docenteProfile->user;
            Mail::to($destinatario->email)
                ->send(new TrabalhoEnviadoBancaMail($documento, $urlDownload, $destinatario->name));
        }

        return back()->with('success', "Trabalho enviado para {$avaliadores->count()} avaliador(es) da banca.");
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
