<?php

namespace Tests\Feature;

use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoConformidadeRag;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnvioManuscritoDisparaConformidadeRagTest extends TestCase
{
    use RefreshDatabase;

    private function discenteComBancaLiberada(): array
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'orcid_url' => 'https://orcid.org/0000-0002-1825-0097', 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'cadastrada',
            'libera_envio_trabalho' => true,
        ]);

        return [$discente, $programa];
    }

    public function test_despacha_job_quando_programa_tem_normas_indexadas(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente, $programa] = $this->discenteComBancaLiberada();
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->createWithContent('trabalho.pdf', file_get_contents(base_path('docs/dissertacao.pdf'))),
        ]);

        Queue::assertPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_nao_despacha_job_quando_analise_do_pdf_falha(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente, $programa] = $this->discenteComBancaLiberada();
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);

        // Conteúdo sem cabeçalho %PDF- válido: a análise estrutural marca status_geral = falha.
        $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->create('trabalho.pdf', 100, 'application/pdf'),
        ]);

        Queue::assertNotPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_nao_despacha_job_quando_programa_nao_tem_normas_indexadas(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente] = $this->discenteComBancaLiberada();

        $response = $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->create('trabalho.pdf', 100, 'application/pdf'),
        ]);

        // O envio do manuscrito continua funcionando normalmente mesmo sem RAG disponível.
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('trabalho_documentos', 1);
        Queue::assertNotPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_nao_despacha_job_quando_normas_ainda_esta_processando(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente, $programa] = $this->discenteComBancaLiberada();
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'processando',
        ]);

        $response = $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->create('trabalho.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('trabalho_documentos', 1);
        Queue::assertNotPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_cria_as_quatro_verificacoes_como_processando_imediatamente_apos_o_envio(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente, $programa] = $this->discenteComBancaLiberada();
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->createWithContent('trabalho.pdf', file_get_contents(base_path('docs/dissertacao.pdf'))),
        ]);

        // Job não roda (Queue::fake), mas as 4 linhas já devem existir para a tela mostrar "processando".
        $documento = TrabalhoDocumento::firstOrFail();
        $analise = $documento->analises()->firstOrFail();
        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'processando'));
    }
}
