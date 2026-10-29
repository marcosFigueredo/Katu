<?php

namespace Tests\Feature;

use App\Models\AreaCnpq;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\SetorCnae;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoSubmissaoLatex;
use App\Models\User;
use Database\Seeders\TabelasLattesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrabalhoLattesTest extends TestCase
{
    use RefreshDatabase;

    private User $discente;
    private TrabalhoAcademico $trabalho;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TabelasLattesSeeder::class);

        $programa = Programa::create(['nome' => 'PPG']);
        $this->discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $this->discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function area(string $codigo): AreaCnpq
    {
        return AreaCnpq::where('codigo', $codigo)->firstOrFail();
    }

    private function salvar(array $dados)
    {
        return $this->actingAs($this->discente)->put(route('meu-trabalho.lattes.update'), $dados);
    }

    public function test_estudante_salva_areas_setor_textos_e_visibilidade(): void
    {
        $epidemiologia = $this->area('40601005');
        $saudeColetiva = $this->area('40600009');
        $setor = SetorCnae::where('codigo', '86')->firstOrFail();

        $this->salvar([
            'areas' => [$epidemiologia->id, $saudeColetiva->id],
            'setor_cnae_id' => $setor->id,
            'titulo_en' => 'Stochastic modeling of biosystems',
            'outras_informacoes' => 'Trabalho financiado pela CAPES.',
            'outras_informacoes_en' => 'Funded by CAPES.',
            'visibilidade' => 'privada',
        ])->assertRedirect(route('meu-trabalho.lattes.edit'))
            ->assertSessionHas('success', 'Dados para o Lattes salvos.');

        $t = $this->trabalho->fresh();
        $this->assertEqualsCanonicalizing([$epidemiologia->id, $saudeColetiva->id], $t->areasCnpq->pluck('id')->all());
        $this->assertSame($setor->id, $t->setor_cnae_id);
        $this->assertSame('Stochastic modeling of biosystems', $t->titulo_en);
        $this->assertSame('Funded by CAPES.', $t->outras_informacoes_en);
        $this->assertSame('privada', $t->visibilidade);
    }

    public function test_visibilidade_padrao_e_publica(): void
    {
        $this->assertSame('publica', $this->trabalho->fresh()->visibilidade);

        $this->actingAs($this->discente)->get(route('meu-trabalho.lattes.edit'))
            ->assertOk()
            ->assertSee('value="publica" checked', false);
    }

    public function test_titulo_em_ingles_vem_da_submissao_latex(): void
    {
        TrabalhoSubmissaoLatex::create([
            'trabalho_academico_id' => $this->trabalho->id, 'enviado_por_id' => $this->discente->id,
            'tipo_documento' => 'dissertacao', 'idioma' => 'pt', 'zip_path' => 'x.zip', 'zip_hash_sha256' => str_repeat('a', 64),
            'status' => 'ok', 'elementos' => ['titulo_en' => 'Title extracted from LaTeX'],
        ]);

        $this->actingAs($this->discente)->get(route('meu-trabalho.lattes.edit'))
            ->assertOk()
            ->assertSee('value="Title extracted from LaTeX"', false);
    }

    public function test_area_inativa_ou_inexistente_e_recusada(): void
    {
        $inativa = $this->area('40601005');
        $inativa->update(['ativo' => false]);

        $this->salvar(['areas' => [$inativa->id], 'visibilidade' => 'publica'])->assertSessionHasErrors('areas.0');
        $this->salvar(['areas' => [999999], 'visibilidade' => 'publica'])->assertSessionHasErrors('areas.0');
        $this->salvar(['setor_cnae_id' => 999999, 'visibilidade' => 'publica'])->assertSessionHasErrors('setor_cnae_id');

        $this->assertSame(0, $this->trabalho->fresh()->areasCnpq()->count());
    }

    public function test_item_desativado_depois_continua_no_trabalho_mas_some_do_seletor(): void
    {
        $area = $this->area('40601005');
        $this->salvar(['areas' => [$area->id], 'visibilidade' => 'publica'])->assertSessionHasNoErrors();

        $area->update(['ativo' => false]);

        $this->assertTrue($this->trabalho->fresh()->areasCnpq->contains('id', $area->id));
        $pagina = $this->actingAs($this->discente)->get(route('meu-trabalho.lattes.edit'))->assertOk();
        $pagina->assertSee('Epidemiologia (inativa)');
        $this->assertSame(1, substr_count($pagina->getContent(), 'value="'.$area->id.'"'));
    }

    public function test_outro_perfil_recebe_403(): void
    {
        foreach ([User::factory()->docente()->create(), User::factory()->secretaria()->create()] as $usuario) {
            $this->actingAs($usuario)->get(route('meu-trabalho.lattes.edit'))->assertForbidden();
            $this->actingAs($usuario)->put(route('meu-trabalho.lattes.update'), ['visibilidade' => 'publica'])->assertForbidden();
        }
    }

    public function test_card_em_meu_trabalho_leva_aos_dados_para_o_lattes(): void
    {
        $this->actingAs($this->discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee(route('meu-trabalho.lattes.edit'), false);
    }
}
