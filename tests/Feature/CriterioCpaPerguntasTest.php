<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\CriterioCpa;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\CriterioCpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CriterioCpaPerguntasTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_cadastra_pergunta_com_etapa_e_nivel(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $this->actingAs($secretaria)->post(route('criterios-cpa.store'), [
            'pergunta' => 'O discente publicou um artigo?',
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'ordem' => '2',
        ])->assertRedirect(route('criterios-cpa.index'));

        $this->assertDatabaseHas('criterios_cpa', [
            'programa_id' => $programa->id,
            'criado_por' => $secretaria->id,
            'pergunta' => 'O discente publicou um artigo?',
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'ordem' => 2,
            'ativo' => true,
        ]);

        $this->actingAs($secretaria)->post(route('criterios-cpa.store'), [
            'pergunta' => 'Cumpriu os créditos?',
            'etapa' => '',
            'nivel' => '',
            'ordem' => '1',
        ])->assertRedirect(route('criterios-cpa.index'));

        $this->assertDatabaseHas('criterios_cpa', ['pergunta' => 'Cumpriu os créditos?', 'etapa' => null, 'nivel' => null]);
    }

    public function test_lista_mostra_etapa_nivel_e_datas(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $this->pergunta($programa, ['pergunta' => 'Pergunta A']);

        $this->actingAs($secretaria)->get(route('criterios-cpa.index'))
            ->assertOk()
            ->assertSee('Pergunta A')
            ->assertSee('Qualificação e defesa')
            ->assertSee('Mestrado e doutorado')
            ->assertSee(now()->format('d/m/Y'));
    }

    public function test_inativar_grava_data_e_nao_apaga(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $pergunta = $this->pergunta($programa);

        $this->actingAs($secretaria)->post(route('criterios-cpa.inativar', $pergunta))
            ->assertRedirect(route('criterios-cpa.index'));

        $pergunta->refresh();
        $this->assertFalse($pergunta->ativo);
        $this->assertNotNull($pergunta->inativado_em);
        $this->assertDatabaseCount('criterios_cpa', 1);
    }

    public function test_pergunta_inativa_nao_pode_ser_editada(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $pergunta = $this->pergunta($programa, ['pergunta' => 'Original']);
        $pergunta->inativar();

        $this->actingAs($secretaria)->put(route('criterios-cpa.update', $pergunta), [
            'pergunta' => 'Alterada', 'etapa' => '', 'nivel' => '', 'ordem' => '1',
        ])->assertStatus(404);

        $this->assertSame('Original', $pergunta->fresh()->pergunta);
    }

    public function test_secretaria_edita_pergunta_ativa(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $pergunta = $this->pergunta($programa);

        $this->actingAs($secretaria)->put(route('criterios-cpa.update', $pergunta), [
            'pergunta' => 'Nova redação', 'etapa' => 'defesa', 'nivel' => 'doutorado', 'ordem' => '3',
        ])->assertRedirect(route('criterios-cpa.index'));

        $this->assertDatabaseHas('criterios_cpa', [
            'id' => $pergunta->id, 'pergunta' => 'Nova redação', 'etapa' => 'defesa', 'nivel' => 'doutorado', 'ordem' => 3,
        ]);
    }

    public function test_perguntas_para_filtra_etapa_nivel_e_ativas(): void
    {
        [, $programa] = $this->secretariaComPrograma();
        $outro = Programa::create(['nome' => 'Outro', 'sigla' => 'OUT']);

        $ambas = $this->pergunta($programa, ['pergunta' => 'ambas', 'ordem' => 3]);
        $qual = $this->pergunta($programa, ['pergunta' => 'qual', 'etapa' => 'qualificacao', 'nivel' => 'mestrado', 'ordem' => 1]);
        $qualNivel = $this->pergunta($programa, ['pergunta' => 'qual-nivel', 'etapa' => 'qualificacao', 'ordem' => 2]);
        $this->pergunta($programa, ['pergunta' => 'defesa', 'etapa' => 'defesa']);
        $this->pergunta($programa, ['pergunta' => 'doutorado', 'nivel' => 'doutorado']);
        $this->pergunta($programa, ['pergunta' => 'inativa'])->inativar();
        $this->pergunta($outro, ['pergunta' => 'outro programa']);

        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
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
        $banca = Banca::create(['trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'cadastrada']);

        $resultado = app(CriterioCpaService::class)->perguntasPara($banca);

        $this->assertSame(
            [$qual->id, $qualNivel->id, $ambas->id],
            $resultado->pluck('id')->all()
        );
    }

    public function test_secretaria_nao_ve_pergunta_de_outro_programa(): void
    {
        [$secretaria] = $this->secretariaComPrograma();
        $outro = Programa::create(['nome' => 'Outro Programa', 'sigla' => 'OUTRO']);
        $pergunta = $this->pergunta($outro);

        $this->actingAs($secretaria)->get(route('criterios-cpa.edit', $pergunta))->assertStatus(404);
        $this->actingAs($secretaria)->post(route('criterios-cpa.inativar', $pergunta))->assertStatus(404);
        $this->assertTrue($pergunta->fresh()->ativo);
    }

    public function test_discente_nao_acessa(): void
    {
        $discente = User::factory()->discente()->create();

        $this->actingAs($discente)->get(route('criterios-cpa.index'))->assertStatus(403);
    }

    public function test_docente_nao_acessa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('criterios-cpa.index'))->assertStatus(403);
    }

    public function test_menu_de_configuracoes_exibe_link_para_criterios_cpa(): void
    {
        [$secretaria] = $this->secretariaComPrograma();

        $this->actingAs($secretaria)->get(route('criterios-cpa.index'))
            ->assertOk()
            ->assertSee('href="'.route('criterios-cpa.index').'"', false)
            ->assertSee('Critérios CPA');
    }

    private function pergunta(Programa $programa, array $atributos = []): CriterioCpa
    {
        return CriterioCpa::create(array_merge([
            'programa_id' => $programa->id,
            'pergunta' => 'Pergunta?',
            'etapa' => null,
            'nivel' => null,
            'ordem' => 1,
            'ativo' => true,
        ], $atributos));
    }

    private function secretariaComPrograma(): array
    {
        $secretaria = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$secretaria, $programa];
    }
}
