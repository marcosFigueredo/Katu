<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CriterioCpaTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_cria_criterio_cpa_para_o_programa(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $response = $this->actingAs($secretaria)->post(route('criterios-cpa.store'), [
            'codigo' => 'CPA-QUAL-01',
            'titulo' => 'Registro de atividades formativas',
            'descricao' => 'Comprovar as atividades realizadas no período.',
            'etapa' => 'qualificacao',
            'tipo_evidencia' => 'arquivo',
            'obrigatorio' => '1',
            'nivel' => 'mestrado',
            'ordem' => '1',
            'versao' => '1.0',
        ]);

        $response->assertRedirect(route('criterios-cpa.index'));
        $this->assertDatabaseHas('criterios_cpa', [
            'programa_id' => $programa->id,
            'codigo' => 'CPA-QUAL-01',
            'etapa' => 'qualificacao',
            'tipo_evidencia' => 'arquivo',
            'obrigatorio' => true,
        ]);
    }

    public function test_secretaria_inativa_criterio_sem_apaga_lo(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $id = DB::table('criterios_cpa')->insertGetId([
            'programa_id' => $programa->id,
            'criado_por' => $secretaria->id,
            'codigo' => 'CPA-DEF-01',
            'titulo' => 'Produção com DOI',
            'etapa' => 'defesa',
            'tipo_evidencia' => 'doi',
            'obrigatorio' => true,
            'nivel' => null,
            'ordem' => 1,
            'versao' => '1.0',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($secretaria)->delete(route('criterios-cpa.destroy', $id));

        $response->assertRedirect(route('criterios-cpa.index'));
        $this->assertDatabaseHas('criterios_cpa', ['id' => $id, 'ativo' => false]);
    }

    public function test_docente_nao_acessa_criterios_da_cpa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('criterios-cpa.index'))->assertStatus(403);
    }

    public function test_secretaria_nao_edita_criterio_de_outro_programa(): void
    {
        [$secretaria] = $this->secretariaComPrograma();
        $outroPrograma = Programa::create(['nome' => 'Outro Programa', 'sigla' => 'OUTRO']);
        $id = DB::table('criterios_cpa')->insertGetId([
            'programa_id' => $outroPrograma->id,
            'criado_por' => $secretaria->id,
            'codigo' => 'OUTRO-01',
            'titulo' => 'Critério isolado',
            'etapa' => 'defesa',
            'tipo_evidencia' => 'texto',
            'obrigatorio' => false,
            'ordem' => 1,
            'versao' => '1.0',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($secretaria)->get(route('criterios-cpa.edit', $id))->assertStatus(404);
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
