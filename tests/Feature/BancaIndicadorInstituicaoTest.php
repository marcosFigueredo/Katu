<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\BancaIndicadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BancaIndicadorInstituicaoTest extends TestCase
{
    use RefreshDatabase;

    private function bancaComAvaliador(string $siglaAvaliador): Banca
    {
        $instituicao = Instituicao::create(['nome' => 'Universidade Federal da Bahia', 'sigla' => 'UFBA', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG UFBA', 'instituicao_id' => $instituicao->id]);
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
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao',
            'status' => 'cadastrada', 'libera_envio_trabalho' => true,
        ]);
        $docente = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => $siglaAvaliador, 'ativo' => true,
        ]);
        $vinculoDocente = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'docente_profile_id' => $docente->id,
            'tipo_vinculo' => 'externo', 'pode_avaliar' => true, 'ativo' => true,
        ]);
        BancaMembro::create([
            'banca_id' => $banca->id, 'docente_programa_vinculo_id' => $vinculoDocente->id,
            'papel' => 'avaliador', 'ordem' => 1, 'ativo' => true,
        ]);

        return $banca;
    }

    public function test_b2_considera_externo_a_instituicao_do_programa(): void
    {
        Http::fake();

        $this->assertTrue(app(BancaIndicadorService::class)->calcular($this->bancaComAvaliador('UNEB'))->b2);
    }

    public function test_b2_nao_conta_avaliador_da_propria_instituicao(): void
    {
        Http::fake();

        $this->assertFalse(app(BancaIndicadorService::class)->calcular($this->bancaComAvaliador('ufba '))->b2);
    }
}
