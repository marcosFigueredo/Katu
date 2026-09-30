<?php

namespace Tests\Feature;

use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\BaseConhecimentoChunk;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\ConformidadeNormasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalisarConformidadeRagJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_extrai_texto_do_pdf_real_e_grava_as_quatro_verificacoes(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'parcial',
                'justificativa' => 'Avaliação de teste.',
                'trecho_norma_citado' => 'trecho',
            ])]),
        ]);

        $programa = Programa::create(['nome' => 'PPG']);
        $documentoRef = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documentoRef->id, 'programa_id' => $programa->id,
            'tipo' => 'normas', 'pagina' => 1, 'ordem' => 0,
            'conteudo' => 'Trecho de norma sobre aderência, ODS, citação e uso de IA.', 'embedding' => [1, 0, 0],
        ]);

        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $caminhoPdf = 'trabalhos/' . $trabalho->id . '/manuscrito.pdf';
        Storage::disk('local')->put($caminhoPdf, file_get_contents(base_path('docs/dissertacao.pdf')));
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => $caminhoPdf, 'arquivo_pdf_nome_original' => 'manuscrito.pdf', 'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);

        (new AnalisarConformidadeRagJob($analise))->handle(app(ConformidadeNormasService::class));

        $this->assertCount(4, DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get());
    }

    public function test_job_nao_lanca_excecao_e_marca_falhou_quando_pdf_nao_pode_ser_lido(): void
    {
        Storage::fake('local');

        $programa = Programa::create(['nome' => 'PPG']);
        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $caminhoPdf = 'trabalhos/' . $trabalho->id . '/manuscrito.pdf';
        Storage::disk('local')->put($caminhoPdf, 'isto nao e um pdf valido');
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => $caminhoPdf, 'arquivo_pdf_nome_original' => 'manuscrito.pdf', 'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);

        (new AnalisarConformidadeRagJob($analise))->handle(app(ConformidadeNormasService::class));

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'falhou'));
    }
}
