<?php

namespace Tests\Feature;

use App\Models\BaseConhecimentoChunk;
use App\Models\DocumentoAnalise;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoConformidadeRag;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\ConformidadeNormasService;
use App\Services\EmbeddingOllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConformidadeNormasServiceTest extends TestCase
{
    use RefreshDatabase;

    private function analiseComChunks(): array
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $documentoRef = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'concluido',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documentoRef->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 10,
            'ordem' => 0,
            'conteudo' => 'A seção Aderência deve apresentar o texto-base obrigatório sobre a relação do trabalho com o programa.',
            'embedding' => [1, 0, 0],
        ]);

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
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);

        return [$analise, $programa->id];
    }

    public function test_grava_as_quatro_verificacoes_quando_ollama_responde_bem(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'conforme',
                'justificativa' => 'Atende ao exigido.',
                'trecho_norma_citado' => 'A seção Aderência deve...',
            ])]),
        ]);
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programaId, [1 => 'Aderência ao Programa: este trabalho se relaciona com...']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'concluida' && $r->veredito === 'conforme'));
    }

    public function test_uma_verificacao_falhando_nao_impede_as_demais(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'embeddings')) {
                return Http::response(['embedding' => [1, 0, 0]]);
            }

            $body = $request->data();
            if (str_contains($body['prompt'] ?? '', 'Uso de Inteligência Artificial')) {
                return Http::response('erro interno', 500);
            }

            return Http::response(['response' => json_encode([
                'veredito' => 'parcial',
                'justificativa' => 'Parcialmente atendido.',
                'trecho_norma_citado' => 'trecho',
            ])]);
        });
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programaId, [1 => 'texto qualquer do manuscrito']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get()->keyBy('verificacao');
        $this->assertSame('falhou', $registros['uso_ia']->status);
        $this->assertSame('concluida', $registros['aderencia']->status);
        $this->assertSame('concluida', $registros['ods']->status);
        $this->assertSame('concluida', $registros['citacao_apa']->status);
    }

    public function test_sem_chunks_indexados_marca_verificacao_como_falhou(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
        ]);
        $programa = Programa::create(['nome' => 'PPG Sem Base']);
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
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf', 'arquivo_pdf_nome_original' => 'a.pdf', 'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programa->id, [1 => 'texto qualquer']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'falhou'));
    }
}
