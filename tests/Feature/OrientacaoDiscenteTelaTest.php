<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\OrientacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrientacaoDiscenteTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;
    private User $discente;
    private TrabalhoAcademico $trabalho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id, 'max_coorientadores' => 1]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);

        $this->discente = User::factory()->discente()->create(['name' => 'Discente Fulana']);
        $profile = DiscenteProfile::create(['user_id' => $this->discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function docente(string $nome, string $tipo = 'interno', bool $podeOrientar = true): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create(['name' => $nome])->id,
            'instituicao_sigla' => $tipo === 'externo' ? 'UFBA' : 'UNEB', 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo, 'pode_orientar' => $podeOrientar, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function comOrientador(string $nome = 'Profa. Antiga'): DocenteProgramaVinculo
    {
        $docente = $this->docente($nome);
        app(OrientacaoService::class)->definirOrientadorInicial($this->trabalho, $docente->id, $this->secretaria);

        return $docente;
    }

    public function test_edicao_do_discente_mostra_bloco_de_orientacao_e_linha_do_tempo(): void
    {
        $this->comOrientador('Profa. Antiga');
        $novo = $this->docente('Prof. Novo');
        app(OrientacaoService::class)->trocarOrientador($this->trabalho, $novo->id, '0001.2026/123', 'Aposentadoria da orientadora.', $this->secretaria);

        $this->actingAs($this->secretaria)->get(route('usuarios.edit', $this->discente))
            ->assertOk()
            ->assertSee('Orientação')
            ->assertSee('Profa. Antiga')
            ->assertSee('Prof. Novo')
            ->assertSee('0001.2026/123')
            ->assertSee('Aposentadoria da orientadora.')
            ->assertSee('Trocar orientador')
            ->assertSee(route('usuarios.orientacao.trocar', $this->discente), false);
    }

    public function test_sem_orientador_mostra_formulario_de_inclusao(): void
    {
        $this->docente('Profa. Disponível');

        $this->actingAs($this->secretaria)->get(route('usuarios.edit', $this->discente))
            ->assertOk()
            ->assertSee('Sem orientador')
            ->assertSee(route('usuarios.orientacao.definir', $this->discente), false)
            ->assertSee('Profa. Disponível');
    }

    public function test_secretaria_define_orientador_pela_tela(): void
    {
        $docente = $this->docente('Profa. Inicial');

        $this->actingAs($this->secretaria)
            ->post(route('usuarios.orientacao.definir', $this->discente), ['orientador_vinculo_id' => $docente->id])
            ->assertRedirect(route('usuarios.edit', $this->discente))
            ->assertSessionHas('success', 'Orientador registrado.');

        $this->assertSame($docente->id, $this->trabalho->fresh()->orientadorVigente()?->docente_programa_vinculo_id);
    }

    public function test_secretaria_troca_orientador_pela_tela(): void
    {
        $antigo = $this->comOrientador();
        $novo = $this->docente('Prof. Novo');

        $this->actingAs($this->secretaria)
            ->post(route('usuarios.orientacao.trocar', $this->discente), [
                'orientador_vinculo_id' => $novo->id,
                'processo_sei' => '0001.2026/9',
                'justificativa' => 'Mudança de linha de pesquisa.',
            ])
            ->assertRedirect(route('usuarios.edit', $this->discente))
            ->assertSessionHas('success', 'Orientador trocado.');

        $this->assertDatabaseHas('orientacoes', ['docente_programa_vinculo_id' => $antigo->id, 'ativo' => false]);
        $this->assertDatabaseHas('orientacoes', [
            'docente_programa_vinculo_id' => $novo->id, 'ativo' => true,
            'processo_sei' => '0001.2026/9', 'justificativa' => 'Mudança de linha de pesquisa.',
        ]);
    }

    public function test_troca_sem_sei_volta_com_erro(): void
    {
        $this->comOrientador();
        $novo = $this->docente('Prof. Novo');

        $this->actingAs($this->secretaria)
            ->post(route('usuarios.orientacao.trocar', $this->discente), [
                'orientador_vinculo_id' => $novo->id,
                'processo_sei' => '',
                'justificativa' => 'Motivo.',
            ])
            ->assertSessionHasErrors(['processo_sei']);

        $this->assertSame(1, Orientacao::count());
    }

    public function test_inclui_e_retira_coorientador_pela_tela(): void
    {
        $this->comOrientador();
        $co = $this->docente('Prof. Externo', 'externo', false);

        $this->actingAs($this->secretaria)
            ->post(route('usuarios.orientacao.coorientador.incluir', $this->discente), ['coorientador_vinculo_id' => $co->id])
            ->assertRedirect(route('usuarios.edit', $this->discente))
            ->assertSessionHas('success', 'Coorientador incluído.');

        $coorientacao = Orientacao::where('tipo', 'coorientador')->firstOrFail();

        $this->actingAs($this->secretaria)->get(route('usuarios.edit', $this->discente))
            ->assertSee(route('usuarios.orientacao.coorientador.retirar', [$this->discente, $coorientacao]), false)
            ->assertDontSee('Incluir coorientador');

        $this->actingAs($this->secretaria)
            ->delete(route('usuarios.orientacao.coorientador.retirar', [$this->discente, $coorientacao]))
            ->assertRedirect(route('usuarios.edit', $this->discente))
            ->assertSessionHas('success', 'Coorientador retirado.');

        $this->assertFalse($coorientacao->fresh()->ativo);
    }

    public function test_coorientacao_de_outro_discente_nao_e_retirada(): void
    {
        $this->comOrientador();
        $co = app(OrientacaoService::class)->incluirCoorientador($this->trabalho, $this->docente('Co')->id, $this->secretaria);

        $outro = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $outro->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);

        $this->actingAs($this->secretaria)
            ->delete(route('usuarios.orientacao.coorientador.retirar', [$outro, $co]))
            ->assertNotFound();

        $this->assertTrue($co->fresh()->ativo);
    }

    public function test_secretaria_de_outro_programa_recebe_403(): void
    {
        $this->comOrientador();
        $docente = $this->docente('Prof. X');
        $co = app(OrientacaoService::class)->incluirCoorientador($this->trabalho, $this->docente('Co')->id, $this->secretaria);

        $outra = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        app(ContextoSecretaria::class)->vincular($outra, Programa::create(['nome' => 'Outro']));

        $this->actingAs($outra)->post(route('usuarios.orientacao.definir', $this->discente), ['orientador_vinculo_id' => $docente->id])->assertForbidden();
        $this->actingAs($outra)->post(route('usuarios.orientacao.trocar', $this->discente), [
            'orientador_vinculo_id' => $docente->id, 'processo_sei' => '1', 'justificativa' => 'x',
        ])->assertForbidden();
        $this->actingAs($outra)->post(route('usuarios.orientacao.coorientador.incluir', $this->discente), ['coorientador_vinculo_id' => $docente->id])->assertForbidden();
        $this->actingAs($outra)->delete(route('usuarios.orientacao.coorientador.retirar', [$this->discente, $co]))->assertForbidden();

        $this->assertSame(2, Orientacao::where('ativo', true)->count());
    }

    public function test_docente_recebe_403(): void
    {
        $docenteUser = User::factory()->docente()->create();
        $vinculo = $this->docente('Prof. X');

        $this->actingAs($docenteUser)
            ->post(route('usuarios.orientacao.definir', $this->discente), ['orientador_vinculo_id' => $vinculo->id])
            ->assertForbidden();
    }

    public function test_menu_nao_tem_mais_orientacoes(): void
    {
        $this->actingAs($this->secretaria)->get(route('usuarios.index'))
            ->assertOk()
            ->assertDontSee('href="'.url('/orientacoes').'"', false);
    }

    public function test_rota_antiga_nao_existe(): void
    {
        $this->actingAs($this->secretaria)->get('/orientacoes')->assertNotFound();
    }

    public function test_programa_define_maximo_de_coorientadores(): void
    {
        $this->actingAs($this->secretaria)->put(route('programas.update', $this->programa), [
            'nome' => 'PPG', 'instituicao_id' => $this->programa->instituicao_id, 'max_coorientadores' => 3,
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->programa->fresh()->max_coorientadores);

        $this->actingAs($this->secretaria)->put(route('programas.update', $this->programa), [
            'nome' => 'PPG', 'instituicao_id' => $this->programa->instituicao_id, 'max_coorientadores' => 6,
        ])->assertSessionHasErrors('max_coorientadores');

        $this->actingAs($this->secretaria)->get(route('programas.edit', $this->programa))
            ->assertSee('name="max_coorientadores"', false);
    }

    public function test_programa_novo_tem_um_coorientador_por_padrao(): void
    {
        $programa = Programa::create(['nome' => 'Novo']);

        $this->assertSame(1, $programa->fresh()->max_coorientadores);
    }
}
