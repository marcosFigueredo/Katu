<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoImpacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrabalhoImpactoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipo_aplicavel_exige_descricao(): void
    {
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->put(route('trabalho-impacto.update'), [
            'impacto_social_aplicavel' => '1',
            'impacto_social_descricao' => '',
        ])->assertSessionHasErrors('impacto_social_descricao');

        $this->assertDatabaseCount('trabalho_impactos', 0);
    }

    public function test_salva_e_limpa_descricao_de_tipo_nao_aplicavel(): void
    {
        [$discente, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->put(route('trabalho-impacto.update'), [
            'impacto_cientifico_aplicavel' => '1',
            'impacto_cientifico_descricao' => 'Novo modelo.',
            'impacto_ambiental_descricao' => 'x',
        ])->assertSessionHasNoErrors();

        $impacto = TrabalhoImpacto::where('trabalho_academico_id', $trabalho->id)->sole();
        $this->assertTrue($impacto->impacto_cientifico_aplicavel);
        $this->assertSame('Novo modelo.', $impacto->impacto_cientifico_descricao);
        $this->assertNull($impacto->impacto_ambiental_descricao);
    }

    public function test_pedido_de_defesa_aguardando_trava_edicao(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        TrabalhoImpacto::create(['trabalho_academico_id' => $trabalho->id, 'impacto_social_aplicavel' => true, 'impacto_social_descricao' => 'Antes']);
        $this->banca($trabalho, 'aguardando');

        $this->actingAs($discente)->get(route('trabalho-impacto.edit'))
            ->assertOk()
            ->assertSee('O pedido de defesa já foi enviado à homologação; estes dados não podem mais ser alterados.')
            ->assertDontSee('action="'.route('trabalho-impacto.update').'"', false);

        $this->actingAs($discente)->put(route('trabalho-impacto.update'), [
            'impacto_social_aplicavel' => '1',
            'impacto_social_descricao' => 'Depois',
        ])->assertSessionHasErrors('travado');

        $this->assertSame('Antes', TrabalhoImpacto::sole()->impacto_social_descricao);
    }

    public function test_pedido_devolvido_libera_edicao(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        $this->banca($trabalho, 'devolvida');

        $this->actingAs($discente)->put(route('trabalho-impacto.update'), [
            'impacto_social_aplicavel' => '1',
            'impacto_social_descricao' => 'Depois',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Depois', TrabalhoImpacto::sole()->impacto_social_descricao);
    }

    private function banca(TrabalhoAcademico $trabalho, string $homologacao): Banca
    {
        return Banca::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
            'status' => 'cadastrada',
            'homologacao_status' => $homologacao,
            'homologacao_tentativa' => 1,
            'libera_envio_trabalho' => false,
        ]);
    }

    /** @return array{0: User, 1: TrabalhoAcademico} */
    private function estrutura(): array
    {
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
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

        return [$discente, $trabalho];
    }
}
