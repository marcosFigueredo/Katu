<?php

namespace Tests\Feature;

use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\ProjetoPesquisa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjetoPesquisaTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);
    }

    private function docente(string $categoria = 'permanente'): User
    {
        $user = User::factory()->docente()->create(['primeiro_acesso' => false]);
        $profile = DocenteProfile::create(['user_id' => $user->id, 'instituicao_sigla' => 'UNEB', 'ativo' => true]);
        DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id, 'tipo_vinculo' => 'interno',
            'categoria_docente' => $categoria, 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);

        return $user;
    }

    private function dados(string $titulo = 'Modelagem de arboviroses'): array
    {
        return ['titulo' => $titulo, 'descricao' => 'Modelos compartimentais para dengue.', 'inicio_em' => '2025-03-01'];
    }

    public function test_docente_permanente_cadastra_o_proprio_projeto(): void
    {
        $docente = $this->docente();

        $this->actingAs($docente)->get(route('avaliacoes.index'))->assertSee('Cadastre seu projeto de pesquisa');
        $this->actingAs($docente)->post(route('meu-projeto.store'), $this->dados())->assertSessionHasNoErrors();

        $this->assertSame(1, ProjetoPesquisa::where('situacao', 'ativo')->count());
        $this->actingAs($docente)->get(route('meu-projeto.edit'))->assertOk()->assertSee('Modelagem de arboviroses');
        $this->actingAs($docente)->get(route('avaliacoes.index'))->assertSee('Modelagem de arboviroses');
    }

    public function test_novo_projeto_conclui_o_anterior_pela_tela(): void
    {
        $docente = $this->docente();
        $this->actingAs($docente)->post(route('meu-projeto.store'), $this->dados('Projeto A'));
        $this->actingAs($docente)->post(route('meu-projeto.store'), $this->dados('Projeto B'));

        $this->assertSame('concluido', ProjetoPesquisa::where('titulo', 'Projeto A')->value('situacao'));
        $this->assertSame('ativo', ProjetoPesquisa::where('titulo', 'Projeto B')->value('situacao'));
        $this->actingAs($docente)->get(route('meu-projeto.edit'))->assertSee('Projeto A')->assertSee('Concluído');
    }

    public function test_docente_corrige_projeto_ativo(): void
    {
        $docente = $this->docente();
        $this->actingAs($docente)->post(route('meu-projeto.store'), $this->dados('Titulo com erro'));
        $projeto = ProjetoPesquisa::first();

        $this->actingAs($docente)->put(route('meu-projeto.update', $projeto), $this->dados('Título corrigido'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Título corrigido', $projeto->fresh()->titulo);
        $this->assertSame(1, ProjetoPesquisa::count());
    }

    public function test_docente_nao_edita_projeto_de_outro(): void
    {
        $dono = $this->docente();
        $this->actingAs($dono)->post(route('meu-projeto.store'), $this->dados());

        $this->actingAs($this->docente())->put(route('meu-projeto.update', ProjetoPesquisa::first()), $this->dados('Invasão'))
            ->assertForbidden();
    }

    public function test_colaborador_nao_cadastra(): void
    {
        $colaborador = $this->docente('colaborador');

        $this->actingAs($colaborador)->post(route('meu-projeto.store'), $this->dados())->assertForbidden();
        $this->actingAs($colaborador)->get(route('meu-projeto.edit'))->assertForbidden();
        $this->actingAs($colaborador)->get(route('avaliacoes.index'))->assertDontSee('Meu projeto de pesquisa');
    }

    public function test_secretaria_ve_projeto_na_edicao_do_docente(): void
    {
        $docente = $this->docente();
        $this->actingAs($docente)->post(route('meu-projeto.store'), $this->dados());

        $this->actingAs($this->secretaria)->get(route('usuarios.edit', $docente))
            ->assertOk()->assertSee('Projeto de pesquisa')->assertSee('Modelagem de arboviroses');
    }

    public function test_discente_e_secretaria_nao_acessam_rotas(): void
    {
        $this->actingAs($this->secretaria)->get(route('meu-projeto.edit'))->assertForbidden();
        $this->actingAs(User::factory()->discente()->create())->post(route('meu-projeto.store'), $this->dados())->assertForbidden();
    }

    public function test_fim_antes_do_inicio_e_recusado(): void
    {
        $this->actingAs($this->docente())->post(route('meu-projeto.store'), $this->dados() + ['fim_em' => '2024-01-01'])
            ->assertSessionHasErrors('fim_em');
        $this->assertSame(0, ProjetoPesquisa::count());
    }
}
