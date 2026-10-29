<?php

namespace Tests\Feature;

use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizacaoEHomepageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Instituicao, 2: Programa} */
    private function cenario(): array
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $inst = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $inst->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        return [$sec, $inst, $programa];
    }

    public function test_instituicao_salva_pais_estado_e_municipio(): void
    {
        [$sec, $inst] = $this->cenario();

        $this->actingAs($sec)->get(route('instituicao.edit'))
            ->assertOk()
            ->assertSee('name="pais"', false)
            ->assertSee('Município');

        $this->actingAs($sec)->put(route('instituicao.update'), [
            'nome' => 'UNEB', 'sigla' => 'UNEB', 'pais' => 'Brasil', 'estado' => 'BA', 'cidade' => 'Alagoinhas',
        ])->assertSessionHasNoErrors();

        $inst->refresh();
        $this->assertSame('Brasil', $inst->pais);
        $this->assertSame('BA', $inst->estado);
        $this->assertSame('Alagoinhas', $inst->cidade);
    }

    public function test_pais_padrao_e_brasil(): void
    {
        [$sec, $inst] = $this->cenario();

        $this->assertSame('Brasil', $inst->fresh()->pais);

        $this->actingAs($sec)->put(route('instituicao.update'), ['nome' => 'UNEB', 'sigla' => 'UNEB', 'pais' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('Brasil', $inst->fresh()->pais);
    }

    public function test_programa_salva_e_mostra_a_homepage_dos_trabalhos(): void
    {
        [$sec, $inst, $programa] = $this->cenario();

        $this->actingAs($sec)->put(route('programas.update', $programa), [
            'nome' => 'PPG', 'instituicao_id' => $inst->id, 'homepage_trabalhos' => 'https://ppgmsb.uneb.br/trabalhos',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://ppgmsb.uneb.br/trabalhos', $programa->fresh()->homepage_trabalhos);
        $this->actingAs($sec)->get(route('programas.edit', $programa))
            ->assertOk()
            ->assertSee('Homepage dos trabalhos')
            ->assertSee('https://ppgmsb.uneb.br/trabalhos');
    }

    public function test_homepage_invalida_e_recusada(): void
    {
        [$sec, $inst, $programa] = $this->cenario();

        $this->actingAs($sec)->put(route('programas.update', $programa), [
            'nome' => 'PPG', 'instituicao_id' => $inst->id, 'homepage_trabalhos' => 'não é url',
        ])->assertSessionHasErrors('homepage_trabalhos');
    }
}
