<?php

namespace App\Http\Controllers;

use App\Jobs\IndexarBaseConhecimentoJob;
use App\Models\BaseConhecimentoChunk;
use App\Models\ProgramaDocumentoReferencia;
use App\Services\ContextoSecretaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProgramaDocumentoReferenciaController extends Controller
{
    public function __construct(private readonly ContextoSecretaria $contexto)
    {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma(auth()->user());

        $documentos = ProgramaDocumentoReferencia::where('programa_id', $programa->id)
            ->get()
            ->keyBy('tipo');

        return view('programas.documentos-referencia', compact('programa', 'documentos'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());

        $data = $request->validate([
            'tipo' => ['required', Rule::in(['normas', 'template'])],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:30720'],
        ], [
            'tipo.required' => 'Tipo de documento inválido.',
            'arquivo.required' => 'Selecione um arquivo PDF.',
            'arquivo.mimes' => 'O arquivo deve ser um PDF.',
            'arquivo.max' => 'O PDF não pode ultrapassar 30 MB.',
        ]);

        $anterior = ProgramaDocumentoReferencia::where('programa_id', $programa->id)
            ->where('tipo', $data['tipo'])
            ->first();

        if ($anterior) {
            Storage::disk('local')->delete($anterior->arquivo_path);
        }

        $arquivo = $request->file('arquivo');
        $destino = "normas-referencia/{$programa->id}/{$data['tipo']}.pdf";

        Storage::disk('local')->put($destino, file_get_contents($arquivo->path()));
        $caminhoFisico = Storage::disk('local')->path($destino);

        $documento = ProgramaDocumentoReferencia::updateOrCreate(
            ['programa_id' => $programa->id, 'tipo' => $data['tipo']],
            [
                'arquivo_path' => $destino,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'tamanho' => filesize($caminhoFisico),
                'hash_sha256' => hash_file('sha256', $caminhoFisico),
                'enviado_por' => auth()->id(),
                'enviado_em' => now(),
                'status_indexacao' => 'pendente',
                'indexado_em' => null,
                'erro_indexacao' => null,
            ]
        );

        // Apaga os chunks da versão anterior imediatamente, para que nenhuma
        // análise use conteúdo obsoleto enquanto a reindexação não termina.
        BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->delete();

        IndexarBaseConhecimentoJob::dispatch($documento);

        $rotulo = $data['tipo'] === 'normas' ? 'Normas e Procedimentos' : 'Template';

        return redirect()->route('programa-documentos-referencia.index')
            ->with('status', "{$rotulo} enviado. A indexação será processada em segundo plano.");
    }
}
