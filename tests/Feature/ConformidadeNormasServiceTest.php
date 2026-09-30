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

    public function test_extracao_do_trecho_ignora_a_pagina_de_sumario(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'conforme',
                'justificativa' => 'ok',
                'trecho_norma_citado' => 'trecho',
            ])]),
        ]);
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $textos = [
            1 => "Sumário\n1.8 Aderência ao Programa . . . . . 5\n1.8.1 ODS do Trabalho . . . 6",
            5 => "1.8 Aderência ao Programa\nEste trabalho está fortemente ligado à linha de pesquisa do programa de modelagem.",
        ];

        $service->analisar($analise, $programaId, $textos);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generate')) {
                return false;
            }

            $body = $request->data();
            $prompt = $body['prompt'] ?? '';

            return str_contains($prompt, 'fortemente ligado à linha de pesquisa')
                && ! str_contains($prompt, '. . . . . 5');
        });
    }

    public function test_veredito_invalido_do_ollama_marca_verificacao_como_falhou(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'incerto', // fora do enum aceito (conforme|parcial|nao_conforme)
                'justificativa' => 'x',
            ])]),
        ]);
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programaId, [1 => 'texto qualquer']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'falhou' && $r->veredito === null));
    }

    public function test_iniciar_cria_as_quatro_verificacoes_como_processando_antes_da_analise(): void
    {
        [$analise] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->iniciar($analise);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'processando'));
    }

    public function test_chunks_de_documento_de_referencia_nao_concluido_sao_ignorados(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response('não deveria ser chamado: nenhum chunk concluído deveria sobrar', 500),
        ]);
        $programa = Programa::create(['nome' => 'PPG Reindexando']);
        $documentoRef = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'x',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'processando',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documentoRef->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 1,
            'ordem' => 0,
            'conteudo' => 'Trecho de uma versão anterior/incompleta da norma.',
            'embedding' => [1, 0, 0],
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
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf', 'arquivo_pdf_nome_original' => 'a.pdf', 'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programa->id, [1 => 'texto qualquer']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'falhou'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generate'));
    }

    public function test_trecho_nao_localizado_no_manuscrito_ainda_assim_consulta_o_ollama(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'nao_conforme',
                'justificativa' => 'Trecho não localizado no manuscrito.',
                'trecho_norma_citado' => 'trecho',
            ])]),
        ]);
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        // Nenhuma página contém os termos de busca de nenhuma das 4 verificações.
        $service->analisar($analise, $programaId, [1 => 'Texto qualquer sem relação com nenhum dos termos buscados.']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generate')) {
                return false;
            }

            $body = $request->data();

            return str_contains($body['prompt'] ?? '', '(Trecho não localizado no manuscrito.)');
        });

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'concluida'));
    }

    public function test_resposta_json_invalida_do_ollama_marca_verificacao_como_falhou(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => 'isto não é um json válido']),
        ]);
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programaId, [1 => 'texto qualquer']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'falhou' && $r->erro !== null));
    }
}
