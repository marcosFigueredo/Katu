<?php

namespace Tests\Feature;

use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseConhecimentoChunkModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_embedding_e_persistido_e_lido_como_array(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $documento = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'concluido',
        ]);

        $chunk = BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documento->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 3,
            'ordem' => 0,
            'conteudo' => 'A seção Aderência é obrigatória.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $fresco = BaseConhecimentoChunk::findOrFail($chunk->id);

        $this->assertIsArray($fresco->embedding);
        $this->assertSame([0.1, 0.2, 0.3], $fresco->embedding);
    }

    public function test_apagar_documento_referencia_apaga_seus_chunks(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $documento = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'concluido',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documento->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 1,
            'ordem' => 0,
            'conteudo' => 'trecho',
            'embedding' => [0.1],
        ]);

        $documento->delete();

        $this->assertSame(0, BaseConhecimentoChunk::count());
    }
}
