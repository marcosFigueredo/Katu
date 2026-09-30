<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaCursoTest extends TestCase
{
    use RefreshDatabase;

    private function secretariaComPrograma(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG', 'oferta_mestrado' => false, 'oferta_doutorado' => false]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        return [$sec, $programa];
    }

    public function test_criar_curso_gera_regras_padrao_e_atualiza_ofertas(): void
    {
        [$sec, $programa] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-cursos.store'), [
            'nivel' => 'doutorado', 'modalidade' => 'academico',
        ])->assertRedirect(route('programa-cursos.index'));

        $curso = ProgramaCurso::with('regrasBanca')->firstOrFail();
        $this->assertSame(30, $curso->prazo_qualificacao_meses);
        $this->assertSame(48, $curso->prazo_defesa_meses);
        $this->assertSame(5, $curso->regraPara('defesa')->min_avaliadores);
        $this->assertSame(2, $curso->regraPara('qualificacao')->min_externos_instituicao);
        $this->assertTrue($programa->fresh()->oferta_doutorado);
        $this->assertFalse($programa->fresh()->oferta_mestrado);
    }

    public function test_nao_duplica_nivel_e_modalidade(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);

        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico'])
            ->assertSessionHasErrors('modalidade');
        $this->assertDatabaseCount('programa_cursos', 1);
    }

    public function test_mestrado_academico_e_profissional_convivem(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'profissional'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('programa_cursos', 2);
    }

    public function test_inativar_curso_recalcula_ofertas_e_mantem_regras(): void
    {
        [$sec, $programa] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);
        $curso = ProgramaCurso::firstOrFail();

        $this->actingAs($sec)->put(route('programa-cursos.update', $curso), [
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => '0',
        ])->assertRedirect();

        $this->assertFalse($curso->fresh()->ativo);
        $this->assertFalse($programa->fresh()->oferta_mestrado);
        $this->assertSame(2, $curso->fresh()->regrasBanca()->count());
    }

    public function test_regras_validam_limites(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);
        $curso = ProgramaCurso::firstOrFail();

        $invalida = ['regras' => [
            'qualificacao' => ['min_avaliadores' => 3, 'max_avaliadores' => 2, 'min_internos' => 1, 'min_externos_instituicao' => 1],
            'defesa' => ['min_avaliadores' => 2, 'max_avaliadores' => 3, 'min_internos' => 2, 'min_externos_instituicao' => 2],
        ]];
        $this->actingAs($sec)->put(route('programa-cursos.regras', $curso), $invalida)
            ->assertSessionHasErrors(['regras.qualificacao.max_avaliadores', 'regras.defesa.min_externos_instituicao']);

        $valida = ['regras' => [
            'qualificacao' => ['min_avaliadores' => 3, 'max_avaliadores' => 4, 'min_internos' => 1, 'min_externos_instituicao' => 1],
            'defesa' => ['min_avaliadores' => 3, 'max_avaliadores' => 5, 'min_internos' => 2, 'min_externos_instituicao' => 1],
        ]];
        $this->actingAs($sec)->put(route('programa-cursos.regras', $curso), $valida)->assertSessionHasNoErrors();
        $this->assertSame(4, $curso->fresh()->regraPara('qualificacao')->max_avaliadores);
    }

    public function test_curso_de_outro_programa_retorna_403(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $outro = Programa::create(['nome' => 'Outro']);
        $curso = ProgramaCurso::create([
            'programa_id' => $outro->id, 'nivel' => 'mestrado', 'modalidade' => 'academico',
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => true,
        ]);

        $this->actingAs($sec)->put(route('programa-cursos.update', $curso), [
            'prazo_qualificacao_meses' => 1, 'prazo_defesa_meses' => 2, 'ativo' => '1',
        ])->assertForbidden();
    }

    public function test_docente_recebe_403(): void
    {
        $this->actingAs(User::factory()->docente()->create())
            ->get(route('programa-cursos.index'))->assertForbidden();
    }

    public function test_tela_de_cursos_exibe_cursos_e_regras(): void
    {
        [$sec, $programa] = $this->secretariaComPrograma();
        app(\App\Services\CursoProgramaService::class)->criar($programa, 'mestrado', 'profissional');

        $this->actingAs($sec)->get(route('programa-cursos.index'))
            ->assertOk()
            ->assertSee('Mestrado Profissional')
            ->assertSee('name="regras[qualificacao][min_avaliadores]"', false)
            ->assertSee('name="regras[defesa][min_externos_instituicao]"', false)
            ->assertSee('name="nivel"', false);
    }
}
