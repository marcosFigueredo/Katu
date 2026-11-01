<?php

namespace Tests\Feature;

use App\Jobs\VerificarProducaoOpenAlexJob;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\ProducaoPublicacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\IndiceQualidadeService;
use App\Services\ProducaoOpenAlexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProducaoOpenAlexTest extends TestCase
{
    use RefreshDatabase;

    private User $discente;
    private TrabalhoAcademico $trabalho;

    protected function setUp(): void
    {
        parent::setUp();

        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id]);
        $this->discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $this->discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create(['programa_id' => $programa->id, 'discente_profile_id' => $profile->id, 'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true]);
        $this->trabalho = TrabalhoAcademico::create(['programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id, 'nivel' => 'mestrado', 'status' => 'em_andamento']);
    }

    private function publicacao(array $extra = []): ProducaoPublicacao
    {
        return $this->trabalho->publicacoes()->create([
            'tipo' => 'artigo', 'titulo' => 'Modelagem da dengue em Salvador', 'ano' => 2025, 'veiculo' => 'Revista X',
            'doi' => '10.1000/abc.123', ...$extra,
        ]);
    }

    private function obra(array $extra = []): array
    {
        return ['id' => 'https://openalex.org/W123', 'display_name' => 'Modelagem da dengue em Salvador', 'publication_year' => 2025, 'cited_by_count' => 7, ...$extra];
    }

    public function test_com_doi_encontrada_grava_id_e_citacoes(): void
    {
        Http::fake(['api.openalex.org/works/https://doi.org/10.1000/abc.123' => Http::response($this->obra())]);
        $publicacao = $this->publicacao();

        app(ProducaoOpenAlexService::class)->verificar($publicacao);

        $publicacao->refresh();
        $this->assertSame('encontrada', $publicacao->openalex_situacao);
        $this->assertSame('https://openalex.org/W123', $publicacao->openalex_id);
        $this->assertSame(7, $publicacao->openalex_citacoes);
        $this->assertNotNull($publicacao->openalex_verificada_em);
    }

    public function test_doi_com_prefixo_e_normalizado(): void
    {
        Http::fake(['api.openalex.org/works/https://doi.org/10.1000/abc.123' => Http::response($this->obra())]);
        $publicacao = $this->publicacao(['doi' => 'https://doi.org/10.1000/ABC.123']);

        app(ProducaoOpenAlexService::class)->verificar($publicacao);

        $this->assertSame('encontrada', $publicacao->fresh()->openalex_situacao);
    }

    public function test_doi_inexistente_fica_nao_encontrada(): void
    {
        Http::fake(['api.openalex.org/*' => Http::response([], 404)]);
        $publicacao = $this->publicacao();

        app(ProducaoOpenAlexService::class)->verificar($publicacao);

        $this->assertSame('nao_encontrada', $publicacao->fresh()->openalex_situacao);
        $this->assertNull($publicacao->fresh()->openalex_citacoes);
    }

    public function test_sem_doi_busca_por_titulo_e_ano(): void
    {
        Http::fake(['api.openalex.org/works?*' => Http::response(['results' => [
            $this->obra(['id' => 'https://openalex.org/W9', 'display_name' => 'Outro assunto qualquer']),
            $this->obra(['display_name' => 'Modelagem da Dengue em Salvador', 'publication_year' => 2024]),
        ]])]);
        $publicacao = $this->publicacao(['doi' => null]);

        app(ProducaoOpenAlexService::class)->verificar($publicacao);

        $this->assertSame('encontrada', $publicacao->fresh()->openalex_situacao);
        $this->assertSame('https://openalex.org/W123', $publicacao->fresh()->openalex_id);
    }

    public function test_titulo_diferente_nao_casa(): void
    {
        Http::fake(['api.openalex.org/works?*' => Http::response(['results' => [
            $this->obra(['display_name' => 'Modelagem da malária no Amazonas']),
            $this->obra(['publication_year' => 2019]),
        ]])]);
        $publicacao = $this->publicacao(['doi' => null]);

        app(ProducaoOpenAlexService::class)->verificar($publicacao);

        $this->assertSame('nao_encontrada', $publicacao->fresh()->openalex_situacao);
    }

    public function test_falha_de_rede_grava_falha(): void
    {
        Http::fake(['api.openalex.org/*' => Http::response('erro', 503)]);
        $publicacao = $this->publicacao();

        app(ProducaoOpenAlexService::class)->verificar($publicacao);

        $this->assertSame('falha', $publicacao->fresh()->openalex_situacao);
    }

    public function test_adicionar_publicacao_despacha_job(): void
    {
        config(['services.openalex.verificar' => true]);
        Queue::fake();

        $this->actingAs($this->discente)->post(route('meu-trabalho.producao.store', 'publicacao'), [
            'tipo' => 'artigo', 'canal' => '', 'titulo' => 'Artigo novo', 'ano' => 2025, 'veiculo' => 'Revista', 'doi' => '',
        ])->assertSessionHasNoErrors();

        Queue::assertPushed(VerificarProducaoOpenAlexJob::class);
    }

    public function test_selo_aparece_para_estudante_e_nao_muda_pontuacao(): void
    {
        $publicacao = $this->publicacao(['situacao' => 'publicada', 'derivada_dissertacao' => true, 'conferida_em' => now()]);
        $antes = app(IndiceQualidadeService::class)->pontuacaoProducao($this->trabalho);

        $publicacao->update(['openalex_situacao' => 'nao_encontrada', 'openalex_verificada_em' => now()]);

        $this->assertSame($antes, app(IndiceQualidadeService::class)->pontuacaoProducao($this->trabalho->fresh()));
        $this->actingAs($this->discente)->get(route('meu-trabalho.producao.index'))
            ->assertOk()->assertSee('Não encontrada no OpenAlex')->assertSee('não invalida a produção');
    }

    public function test_comando_verifica_pendentes(): void
    {
        Http::fake(['api.openalex.org/*' => Http::response($this->obra())]);
        $pendente = $this->publicacao();
        $falhou = $this->publicacao(['doi' => '10.1000/outro', 'openalex_situacao' => 'falha']);
        $jaVista = $this->publicacao(['doi' => '10.1000/visto', 'openalex_situacao' => 'nao_encontrada', 'openalex_verificada_em' => now()]);

        $this->artisan('producao:verificar-openalex')->assertSuccessful();

        $this->assertSame('encontrada', $pendente->fresh()->openalex_situacao);
        $this->assertSame('encontrada', $falhou->fresh()->openalex_situacao);
        $this->assertSame('nao_encontrada', $jaVista->fresh()->openalex_situacao);
    }
}
