<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\ProducaoExtensao;
use App\Models\ProducaoPopularizacao;
use App\Models\ProducaoPublicacao;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoIndicador;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducaoAssociadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_adiciona_publicacao_extensao_e_popularizacao(): void
    {
        [$discente, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao())
            ->assertSessionHasNoErrors();
        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'extensao'), [
            'tipo' => 'oficina', 'titulo' => 'Oficina de modelagem', 'publico' => 40, 'parceiro' => 'Escola Estadual X',
            'carga_horaria' => 8, 'inicio' => '2025-05-10', 'fim' => '2025-05-11', 'registro_proex' => 'PROEX 12/2025',
            'produto_entregue' => 'Apostila', 'link' => 'https://uneb.br/oficina',
        ])->assertSessionHasNoErrors();
        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'popularizacao'), [
            'tipo' => 'podcast', 'titulo' => 'Epidemias no rádio', 'veiculo' => 'Rádio UNEB', 'alcance' => 1200,
            'data' => '2025-08-01', 'link' => 'https://uneb.br/podcast',
        ])->assertSessionHasNoErrors();

        $publicacao = ProducaoPublicacao::where('trabalho_academico_id', $trabalho->id)->sole();
        $this->assertSame('10.1016/j.annepidem.2025.06.012', $publicacao->doi);
        $this->assertSame(1, ProducaoExtensao::where('trabalho_academico_id', $trabalho->id)->count());
        $this->assertSame(1, ProducaoPopularizacao::where('trabalho_academico_id', $trabalho->id)->count());

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'extensao'), [
            'tipo' => 'oficina', 'titulo' => 'X', 'publico' => 1, 'parceiro' => 'Y', 'carga_horaria' => 1,
            'inicio' => '2025-05-10', 'fim' => '2025-05-01',
        ])->assertSessionHasErrors('fim');
    }

    public function test_canal_conforme_o_tipo(): void
    {
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao(['tipo' => 'livro', 'canal' => '']))
            ->assertSessionHasErrors('canal');

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao(['canal' => 'internacional']))
            ->assertSessionHasNoErrors();
        $this->assertNull(ProducaoPublicacao::sole()->canal);

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao(['tipo' => 'anais', 'canal' => 'comercial_nacional', 'doi' => '']))
            ->assertSessionHasErrors('canal');
        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao(['tipo' => 'anais', 'canal' => 'internacional', 'doi' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame('internacional', ProducaoPublicacao::where('tipo', 'anais')->value('canal'));
    }

    public function test_sugestoes_do_openalex(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        TrabalhoIndicador::create([
            'trabalho_academico_id' => $trabalho->id,
            'dados_json' => [
                ['id' => 'https://openalex.org/W1', 'doi' => '10.1/obra-nova', 'titulo' => 'Obra ainda não adicionada', 'ano' => 2025, 'tipo' => 'article', 'host_venue' => 'Revista A'],
                ['id' => 'https://openalex.org/W2', 'doi' => '10.1016/j.annepidem.2025.06.012', 'titulo' => 'Obra já adicionada', 'ano' => 2025, 'tipo' => 'article', 'host_venue' => 'Revista B'],
            ],
        ]);
        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao());

        $this->actingAs($discente)->get(route('meu-trabalho.producao.index'))
            ->assertOk()
            ->assertSee('Obra ainda não adicionada')
            ->assertSee('Adicionar')
            ->assertDontSee('Obra já adicionada');
    }

    public function test_excluir_item_de_outro_trabalho_e_404(): void
    {
        [$discente] = $this->estrutura();
        [, $outro] = $this->estrutura('PPGX');
        $alheia = ProducaoPublicacao::create(['trabalho_academico_id' => $outro->id] + $this->publicacao());

        $this->actingAs($discente)->delete(route('meu-trabalho.producao.destroy', ['publicacao', $alheia->id]))->assertNotFound();
        $this->actingAs($discente)->delete(route('meu-trabalho.producao.destroy', ['inexistente', $alheia->id]))->assertNotFound();
        $this->assertModelExists($alheia);
    }

    public function test_confirmar_lista_e_alteracao_desfaz_confirmacao(): void
    {
        [$discente, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('meu-trabalho.producao.confirmar'))->assertSessionHasNoErrors();
        $this->assertNotNull($trabalho->fresh()->producao_confirmada_em);

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao());
        $this->assertNull($trabalho->fresh()->producao_confirmada_em);

        $this->actingAs($discente)->post(route('meu-trabalho.producao.confirmar'));
        $this->actingAs($discente)->delete(route('meu-trabalho.producao.destroy', ['publicacao', ProducaoPublicacao::sole()->id]));
        $this->assertNull($trabalho->fresh()->producao_confirmada_em);
        $this->assertDatabaseCount('producao_publicacoes', 0);
    }

    public function test_tela_mostra_o_minimo_do_curso(): void
    {
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->get(route('meu-trabalho.producao.index'))
            ->assertSee('Seu curso exige pelo menos 1 publicação para a defesa.')
            ->assertSee('Você informou 0.');
    }

    public function test_trava_com_pedido_de_defesa_aguardando(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        $publicacao = ProducaoPublicacao::create(['trabalho_academico_id' => $trabalho->id] + $this->publicacao());
        Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa', 'status' => 'cadastrada',
            'homologacao_status' => 'aguardando', 'homologacao_tentativa' => 1, 'libera_envio_trabalho' => false,
        ]);

        $this->actingAs($discente)->post(route('meu-trabalho.producao.store', 'publicacao'), $this->publicacao())
            ->assertSessionHasErrors('travado');
        $this->actingAs($discente)->delete(route('meu-trabalho.producao.destroy', ['publicacao', $publicacao->id]))
            ->assertSessionHasErrors('travado');
        $this->actingAs($discente)->post(route('meu-trabalho.producao.confirmar'))->assertSessionHasErrors('travado');

        $this->assertSame(1, ProducaoPublicacao::count());
        $this->assertNull($trabalho->fresh()->producao_confirmada_em);
    }

    public function test_outro_perfil_nao_acessa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('meu-trabalho.producao.index'))->assertForbidden();
        $this->actingAs($docente)->post(route('meu-trabalho.producao.confirmar'))->assertForbidden();
    }

    public function test_secretaria_define_minimo_de_publicacoes_do_curso(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG', 'oferta_mestrado' => false, 'oferta_doutorado' => false]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        foreach (['mestrado' => 1, 'doutorado' => 4] as $nivel => $minimo) {
            $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => $nivel, 'modalidade' => 'academico'])
                ->assertSessionHasNoErrors();
            $this->assertSame($minimo, ProgramaCurso::where('nivel', $nivel)->sole()->min_publicacoes_defesa);
        }

        $curso = ProgramaCurso::where('nivel', 'mestrado')->sole();
        $this->actingAs($sec)->put(route('programa-cursos.update', $curso), [
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => '1', 'min_publicacoes_defesa' => 4,
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, $curso->fresh()->min_publicacoes_defesa);

        $this->actingAs($sec)->put(route('programa-cursos.update', $curso), [
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => '1', 'min_publicacoes_defesa' => 21,
        ])->assertSessionHasErrors('min_publicacoes_defesa');

        $this->actingAs($sec)->get(route('programa-cursos.index'))->assertSee('Publicações mínimas para a defesa');
    }

    private function publicacao(array $sobrescrever = []): array
    {
        return $sobrescrever + [
            'tipo' => 'artigo',
            'canal' => '',
            'titulo' => 'Integrated statistical modeling and machine learning techniques',
            'ano' => 2025,
            'veiculo' => 'Annals of Epidemiology',
            'doi' => 'https://doi.org/10.1016/j.annepidem.2025.06.012',
            'issn' => '1047-2797',
        ];
    }

    /** @return array{0: User, 1: TrabalhoAcademico} */
    private function estrutura(string $sigla = 'PPGMSB'): array
    {
        $instituicao = Instituicao::firstOrCreate(['sigla' => 'UNEB'], ['nome' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => $sigla, 'sigla' => $sigla, 'instituicao_id' => $instituicao->id]);
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);

        return [$discente, $trabalho];
    }
}
