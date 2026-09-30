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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrientacaoCoorientadoresTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;
    private TrabalhoAcademico $trabalho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id, 'max_coorientadores' => 2]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);

        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function docente(string $nome, string $tipo, bool $podeOrientar, ?Programa $programa = null): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create(['name' => $nome])->id,
            'instituicao_sigla' => $tipo === 'externo' ? 'UFBA' : 'UNEB', 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => ($programa ?? $this->programa)->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo, 'pode_orientar' => $podeOrientar, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function salvar(array $dados)
    {
        return $this->actingAs($this->secretaria)->put(route('orientacoes.update', $this->trabalho), $dados);
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

    public function test_tela_mostra_orientador_principal_e_campos_de_coorientador_conforme_maximo(): void
    {
        $this->docente('Profa. Interna', 'interno', true);
        $this->docente('Prof. Externo', 'externo', false);

        $resposta = $this->actingAs($this->secretaria)->get(route('orientacoes.edit', $this->trabalho))->assertOk();

        $resposta->assertSee('Orientador principal')
            ->assertSee('name="orientador_vinculo_id"', false)
            ->assertSee('name="coorientadores[0]"', false)
            ->assertSee('name="coorientadores[1]"', false)
            ->assertDontSee('name="coorientadores[2]"', false)
            ->assertDontSee('type="checkbox"', false)
            ->assertSee('Prof. Externo');
    }

    public function test_sem_coorientadores_quando_maximo_e_zero(): void
    {
        $this->programa->update(['max_coorientadores' => 0]);

        $this->actingAs($this->secretaria)->get(route('orientacoes.edit', $this->trabalho))
            ->assertOk()->assertDontSee('name="coorientadores[0]"', false);
    }

    public function test_externo_pode_ser_coorientador_mas_nao_orientador_principal(): void
    {
        $interno = $this->docente('Profa. Interna', 'interno', true);
        $externo = $this->docente('Prof. Externo', 'externo', false);

        $this->salvar(['orientador_vinculo_id' => $interno->id, 'coorientadores' => [$externo->id]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orientacoes', ['docente_programa_vinculo_id' => $externo->id, 'tipo' => 'coorientador']);

        $this->salvar(['orientador_vinculo_id' => $externo->id])->assertSessionHasErrors('orientador_vinculo_id');
    }

    public function test_limite_de_coorientadores_e_respeitado(): void
    {
        $orientador = $this->docente('Orientador', 'interno', true);
        $co = collect(range(1, 3))->map(fn ($i) => $this->docente("Co $i", 'interno', true)->id)->all();

        $this->salvar(['orientador_vinculo_id' => $orientador->id, 'coorientadores' => $co])
            ->assertSessionHasErrors('coorientadores');
        $this->assertSame(0, Orientacao::where('trabalho_academico_id', $this->trabalho->id)->count());
    }

    public function test_coorientador_repetido_ou_igual_ao_orientador_e_recusado(): void
    {
        $orientador = $this->docente('Orientador', 'interno', true);
        $co = $this->docente('Co', 'interno', true);

        $this->salvar(['orientador_vinculo_id' => $orientador->id, 'coorientadores' => [$co->id, $co->id]])
            ->assertSessionHasErrors('coorientadores');
        $this->salvar(['orientador_vinculo_id' => $orientador->id, 'coorientadores' => [$orientador->id]])
            ->assertSessionHasErrors('coorientadores');
    }

    public function test_coorientador_de_outro_programa_e_recusado(): void
    {
        $orientador = $this->docente('Orientador', 'interno', true);
        $alheio = $this->docente('Alheio', 'externo', false, Programa::create(['nome' => 'Outro']));

        $this->salvar(['orientador_vinculo_id' => $orientador->id, 'coorientadores' => [$alheio->id]])
            ->assertSessionHasErrors('coorientadores');
    }

    public function test_campos_vazios_de_coorientador_sao_ignorados(): void
    {
        $orientador = $this->docente('Orientador', 'interno', true);

        $this->salvar(['orientador_vinculo_id' => $orientador->id, 'coorientadores' => ['', '']])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Orientacao::where('trabalho_academico_id', $this->trabalho->id)->count());
    }
}
