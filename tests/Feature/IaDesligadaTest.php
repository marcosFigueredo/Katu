<?php

namespace Tests\Feature;

use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoAnaliseOllama;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IaDesligadaTest extends TestCase
{
    use RefreshDatabase;

    private function discenteComNormas(): User
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
            'homologacao_status' => 'proposta', 'libera_envio_trabalho' => false,
        ]);
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);

        return $discente;
    }

    private function enviarPdf(User $discente): void
    {
        $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->createWithContent('trabalho.pdf', file_get_contents(base_path('docs/dissertacao.pdf'))),
        ]);
    }

    public function test_envio_pdf_nao_dispara_rag_com_flag_desligada(): void
    {
        Storage::fake('local');
        Queue::fake();
        $discente = $this->discenteComNormas();

        $this->enviarPdf($discente);

        $this->assertDatabaseCount('trabalho_documentos', 1);
        Queue::assertNotPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_envio_pdf_nao_cria_analise_ollama_com_flag_desligada(): void
    {
        Storage::fake('local');
        Queue::fake();
        $discente = $this->discenteComNormas();

        $this->enviarPdf($discente);

        $this->assertDatabaseCount('trabalho_documentos', 1);
        $this->assertSame(0, DocumentoAnaliseOllama::count());
    }

    public function test_tela_de_normas_responde_404_com_flag_desligada(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->get(route('programa-documentos-referencia.index'))->assertNotFound();
    }
}
