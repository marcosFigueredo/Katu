<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\AutoavaliacaoIndicadorService;
use App\Services\CursoProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoavaliacaoPrazoCursoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a13_usa_prazo_de_qualificacao_do_curso(): void
    {
        [$programa] = $this->qualificacaoDeDoutoradoEm26Meses();
        $periodo = AutoavaliacaoPeriodo::create([
            'programa_id' => $programa->id,
            'nome' => '2026',
            'ano' => 2026,
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
        ]);

        app(AutoavaliacaoIndicadorService::class)->calcular($periodo);

        $a13 = AutoavaliacaoIndicador::where('autoavaliacao_periodo_id', $periodo->id)->where('codigo', 'A13')->firstOrFail();
        $this->assertSame(100.0, (float) $a13->valor_numerico);
        $this->assertStringContainsString('prazo do curso', $a13->nome);
    }

    public function test_percentual_no_prazo_usa_prazo_de_defesa_do_curso(): void
    {
        [$programa] = $this->qualificacaoDeDoutoradoEm26Meses();
        $bancas = Banca::with('trabalhoAcademico.discenteVinculo.programaCurso')->get();
        $bancas->first()->tipo = 'defesa';

        // 26 meses: dentro dos 48 do doutorado; estaria fora com o limite fixo de 24.
        $this->assertSame(100.0, app(AutoavaliacaoIndicadorService::class)->percentualNoPrazo($bancas, 'defesa'));
        $this->assertNull(app(AutoavaliacaoIndicadorService::class)->percentualNoPrazo(collect(), 'defesa'));
    }

    private function qualificacaoDeDoutoradoEm26Meses(): array
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $curso = app(CursoProgramaService::class)->criar($programa, 'doutorado', 'academico');
        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'programa_curso_id' => $curso->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'doutorado',
            'status' => 'ativo',
            'data_ingresso' => '2024-01-01',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'doutorado',
            'status' => 'em_andamento',
        ]);
        Banca::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'qualificacao',
            'status' => 'finalizada',
            'data_hora' => '2026-03-01 10:00:00',
            'libera_envio_trabalho' => true,
        ]);
        return [$programa];
    }
}
