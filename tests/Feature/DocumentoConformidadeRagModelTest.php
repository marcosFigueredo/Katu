<?php

namespace Tests\Feature;

use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoConformidadeRagModelTest extends TestCase
{
    use RefreshDatabase;

    private function criarAnalise(): DocumentoAnalise
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo_documento' => 'qualificacao',
            'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf',
            'arquivo_pdf_nome_original' => 'a.pdf',
            'status_analise' => 'concluida',
        ]);

        return DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);
    }

    public function test_relacao_documento_analise_conformidade_rag(): void
    {
        $analise = $this->criarAnalise();
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id,
            'verificacao' => 'aderencia',
            'status' => 'processando',
        ]);

        $this->assertCount(1, $analise->conformidadeRag);
    }

    public function test_nao_permite_verificacao_duplicada_na_mesma_analise(): void
    {
        $analise = $this->criarAnalise();
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id,
            'verificacao' => 'ods',
            'status' => 'processando',
        ]);

        $this->expectException(QueryException::class);

        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id,
            'verificacao' => 'ods',
            'status' => 'processando',
        ]);
    }
}
