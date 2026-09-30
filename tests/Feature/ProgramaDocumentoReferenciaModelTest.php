<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaDocumentoReferenciaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_programa_so_pode_ter_um_documento_por_tipo(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);

        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'pendente',
        ]);

        $this->expectException(QueryException::class);

        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas-v2.pdf',
            'arquivo_nome_original' => 'normas-v2.pdf',
            'status_indexacao' => 'pendente',
        ]);
    }

    public function test_relacao_programa_documentos_referencia(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'template',
            'arquivo_path' => 'normas-referencia/1/template.pdf',
            'arquivo_nome_original' => 'template.pdf',
            'status_indexacao' => 'pendente',
        ]);

        $this->assertCount(1, $programa->documentosReferencia);
    }
}
