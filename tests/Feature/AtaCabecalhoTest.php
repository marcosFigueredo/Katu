<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtaCabecalhoTest extends TestCase
{
    use RefreshDatabase;

    private function banca(?string $logoPrograma): array
    {
        $instituicao = Instituicao::create([
            'nome' => 'Universidade Teste', 'sigla' => 'UT', 'departamento' => 'Depto X',
            'cidade' => 'Feira', 'estado' => 'BA', 'logo_path' => 'logos/i.png', 'ativo' => true,
        ]);
        $programa = Programa::create([
            'nome' => 'Programa de Pós-Graduação em Teste', 'sigla' => 'PPGT',
            'instituicao_id' => $instituicao->id, 'logo_path' => $logoPrograma,
        ]);
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $banca = Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa', 'status' => 'finalizada',
            'resultado' => 'aprovado', 'data_hora' => '2026-09-01 14:00:00', 'libera_envio_trabalho' => true,
        ]);

        return [$sec, $programa, $instituicao, $banca];
    }

    public function test_ata_usa_dados_e_logotipos_do_cadastro(): void
    {
        [$sec, $programa, $instituicao, $banca] = $this->banca('logos/p.png');

        $this->actingAs($sec)->get(route('avaliacoes.ata', $banca))
            ->assertOk()
            ->assertSee('Universidade Teste')
            ->assertSee('Depto X')
            ->assertSee('Programa de Pós-Graduação em Teste')
            ->assertSee(route('programas.logo', $programa), false)
            ->assertSee(route('instituicoes.logo', $instituicao), false)
            ->assertDontSee('Modelagem e Simulação de Biossistemas')
            ->assertDontSee('PPGMSB');
    }

    public function test_ata_sem_logo_do_programa_mostra_so_o_da_instituicao(): void
    {
        [$sec, $programa, $instituicao, $banca] = $this->banca(null);

        $this->actingAs($sec)->get(route('avaliacoes.ata', $banca))
            ->assertOk()
            ->assertDontSee(route('programas.logo', $programa), false)
            ->assertSee(route('instituicoes.logo', $instituicao), false);
    }
}
