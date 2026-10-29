<?php

namespace Tests\Feature;

use App\Models\AreaCnpq;
use App\Models\Programa;
use App\Models\SetorCnae;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Database\Seeders\TabelasLattesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabelasLattesTest extends TestCase
{
    use RefreshDatabase;

    private function secretaria(string $programa = 'PPG'): User
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        app(ContextoSecretaria::class)->vincular($sec, Programa::create(['nome' => $programa]));

        return $sec;
    }

    public function test_seeder_carrega_as_listas_e_e_idempotente(): void
    {
        $this->seed(TabelasLattesSeeder::class);
        $this->seed(TabelasLattesSeeder::class);

        $this->assertSame(9, AreaCnpq::where('nivel', 'grande_area')->count());
        $this->assertGreaterThan(80, AreaCnpq::where('nivel', 'area')->count());
        $this->assertGreaterThan(300, AreaCnpq::where('nivel', 'subarea')->count());
        $this->assertSame(87, SetorCnae::count());

        $epidemiologia = AreaCnpq::where('codigo', '40601005')->firstOrFail();
        $this->assertSame('Ciências da Saúde › Saúde Coletiva › Epidemiologia', $epidemiologia->caminho());
        $this->assertSame('Atividades de atenção à saúde humana', SetorCnae::where('codigo', '86')->value('nome'));
    }

    public function test_seeder_nao_desfaz_edicao_da_secretaria(): void
    {
        $this->seed(TabelasLattesSeeder::class);
        AreaCnpq::where('codigo', '40601005')->update(['nome' => 'Epidemiologia (editada)', 'ativo' => false]);

        $this->seed(TabelasLattesSeeder::class);

        $area = AreaCnpq::where('codigo', '40601005')->first();
        $this->assertSame('Epidemiologia (editada)', $area->nome);
        $this->assertFalse($area->ativo);
    }

    public function test_secretaria_busca_inclui_edita_e_desativa_area_e_setor(): void
    {
        $this->seed(TabelasLattesSeeder::class);
        $sec = $this->secretaria();

        $this->actingAs($sec)->get(route('tabelas-lattes.index', ['lista' => 'cnpq', 'busca' => 'Epidemio']))
            ->assertOk()->assertSee('Epidemiologia')->assertDontSee('Álgebra');
        $this->actingAs($sec)->get(route('tabelas-lattes.index', ['lista' => 'cnae', 'busca' => 'saúde']))
            ->assertOk()->assertSee('Atividades de atenção à saúde humana');

        $saudeColetiva = AreaCnpq::where('codigo', '40600009')->firstOrFail();
        $this->actingAs($sec)->post(route('tabelas-lattes.store', ['lista' => 'cnpq']), [
            'codigo' => '40699999', 'nome' => 'Modelagem em Saúde', 'nivel' => 'subarea', 'pai_id' => $saudeColetiva->id,
        ])->assertRedirect();
        $nova = AreaCnpq::where('codigo', '40699999')->firstOrFail();
        $this->assertTrue($nova->ativo);

        $this->actingAs($sec)->put(route('tabelas-lattes.update', ['lista' => 'cnpq', 'id' => $nova->id]), [
            'codigo' => '40699999', 'nome' => 'Modelagem Computacional em Saúde', 'nivel' => 'subarea', 'pai_id' => $saudeColetiva->id,
        ])->assertRedirect();
        $this->assertSame('Modelagem Computacional em Saúde', $nova->fresh()->nome);

        $setor = SetorCnae::where('codigo', '86')->firstOrFail();
        $this->actingAs($sec)->patch(route('tabelas-lattes.alternar', ['lista' => 'cnae', 'id' => $setor->id]))->assertRedirect();
        $this->assertFalse($setor->fresh()->ativo);
        $this->actingAs($sec)->patch(route('tabelas-lattes.alternar', ['lista' => 'cnae', 'id' => $setor->id]));
        $this->assertTrue($setor->fresh()->ativo);

        $this->actingAs($sec)->post(route('tabelas-lattes.store', ['lista' => 'cnae']), ['codigo' => '86', 'nome' => 'Duplicado'])
            ->assertSessionHasErrors('codigo');
    }

    public function test_nao_ha_exclusao_so_desativacao(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('tabelas-lattes.destroy'));
    }

    public function test_docente_e_discente_recebem_403(): void
    {
        $this->seed(TabelasLattesSeeder::class);

        foreach ([User::factory()->docente()->create(), User::factory()->discente()->create()] as $usuario) {
            $this->actingAs($usuario)->get(route('tabelas-lattes.index'))->assertForbidden();
            $this->actingAs($usuario)->post(route('tabelas-lattes.store', ['lista' => 'cnae']), ['codigo' => '99', 'nome' => 'X'])->assertForbidden();
        }
    }

    public function test_mudanca_vale_para_todos_os_programas(): void
    {
        $this->seed(TabelasLattesSeeder::class);
        $setor = SetorCnae::where('codigo', '85')->firstOrFail();

        $this->actingAs($this->secretaria('A'))->put(route('tabelas-lattes.update', ['lista' => 'cnae', 'id' => $setor->id]), [
            'codigo' => '85', 'nome' => 'Educação (todas as etapas)', 'secao' => 'P',
        ])->assertRedirect();

        $this->actingAs($this->secretaria('B'))->get(route('tabelas-lattes.index', ['lista' => 'cnae', 'busca' => 'Educação']))
            ->assertOk()->assertSee('Educação (todas as etapas)');
    }

    public function test_pai_precisa_ser_do_nivel_acima_e_nao_forma_ciclo(): void
    {
        $this->seed(TabelasLattesSeeder::class);
        $sec = $this->secretaria();
        $grande = AreaCnpq::where('codigo', '40000001')->firstOrFail();
        $area = AreaCnpq::where('codigo', '40600009')->firstOrFail();
        $sub = AreaCnpq::where('codigo', '40601005')->firstOrFail();
        $editar = fn (AreaCnpq $item, array $dados) => $this->actingAs($sec)
            ->put(route('tabelas-lattes.update', ['lista' => 'cnpq', 'id' => $item->id]), ['codigo' => $item->codigo, 'nome' => $item->nome] + $dados);

        // Ciclo: a área passa a pertencer à própria subárea
        $editar($area, ['nivel' => 'area', 'pai_id' => $sub->id])->assertSessionHasErrors('pai_id');
        // Pai de nível errado e item apontando para si mesmo
        $editar($sub, ['nivel' => 'subarea', 'pai_id' => $grande->id])->assertSessionHasErrors('pai_id');
        $editar($area, ['nivel' => 'area', 'pai_id' => $area->id])->assertSessionHasErrors('pai_id');
        // Grande área não tem pai
        $editar($grande, ['nivel' => 'grande_area', 'pai_id' => $area->id])->assertSessionHasErrors('pai_id');

        $this->assertSame($grande->id, $area->fresh()->pai_id);
        $this->actingAs($sec)->get(route('tabelas-lattes.index', ['lista' => 'cnpq', 'busca' => 'Epidemio']))->assertOk();
    }

    public function test_caminho_nao_trava_com_ciclo_ja_gravado(): void
    {
        $a = AreaCnpq::create(['codigo' => '1', 'nome' => 'A', 'nivel' => 'area']);
        $b = AreaCnpq::create(['codigo' => '2', 'nome' => 'B', 'nivel' => 'area', 'pai_id' => $a->id]);
        $a->update(['pai_id' => $b->id]);

        $this->assertSame('A › B', $b->fresh()->caminho());
    }
}
