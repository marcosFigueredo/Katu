<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EsquemaProgramaCursoTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_expoe_nome_identificador_e_regra_por_etapa(): void
    {
        $programa = Programa::create(['nome' => 'PPG Teste', 'sigla' => 'PPGT']);
        $curso = ProgramaCurso::create([
            'programa_id' => $programa->id,
            'nivel' => 'doutorado',
            'modalidade' => 'profissional',
            'prazo_qualificacao_meses' => 30,
            'prazo_defesa_meses' => 48,
            'ativo' => true,
        ]);
        $curso->regrasBanca()->create([
            'etapa' => 'defesa',
            'min_avaliadores' => 5,
            'max_avaliadores' => 5,
            'min_internos' => 2,
            'min_externos_instituicao' => 2,
        ]);

        $this->assertSame('Doutorado Profissional', $curso->nome);
        $this->assertSame('doutorado_profissional', $curso->identificador);
        $this->assertSame(2, $curso->regraPara('defesa')->min_internos);
        $this->assertNull($curso->regraPara('qualificacao'));
        $this->assertTrue($programa->cursos()->whereKey($curso->id)->exists());
    }

    public function test_vinculo_de_discente_referencia_curso(): void
    {
        $programa = Programa::create(['nome' => 'PPG Teste']);
        $curso = ProgramaCurso::create([
            'programa_id' => $programa->id, 'nivel' => 'mestrado', 'modalidade' => 'academico',
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => true,
        ]);
        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'programa_curso_id' => $curso->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);

        $this->assertTrue($vinculo->programaCurso->is($curso));
    }
}
