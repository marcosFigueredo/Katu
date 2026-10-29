<?php

namespace Tests\Feature;

use App\Models\AreaCnpq;
use App\Models\Banca;
use App\Models\Programa;
use App\Models\SetorCnae;
use App\Models\User;
use App\Services\CursoProgramaService;
use App\Services\DadosLattesBancaService;
use Database\Seeders\TabelasLattesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class DadosLattesBancaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    /** Banca finalizada com programa, curso, instituição e dados do estudante preenchidos. */
    private function bancaFinalizada(string $tipo = 'defesa', string $nivel = 'mestrado', bool $completa = true): array
    {
        $this->seed(TabelasLattesSeeder::class);
        [$secretaria, $programa, $banca, $discente, $orientador, $coorientador, , $avaliadores] = $this->criarCenarioCompleto($tipo);
        $trabalho = $banca->trabalhoAcademico;
        $trabalho->update(['nivel' => $nivel, 'titulo' => 'Modelagem de Biossistemas', 'palavras_chave' => 'modelagem; simulação']);
        $banca->update(['status' => 'finalizada', 'homologacao_status' => 'homologada', 'data_hora' => '2026-11-30 14:00:00', 'resultado' => 'aprovado']);

        if ($completa) {
            $programa->update(['homepage_trabalhos' => 'https://ppgmsb.uneb.br/trabalhos']);
            $programa->instituicao->update(['pais' => 'Brasil']);
            $curso = app(CursoProgramaService::class)->criar($programa, $nivel, 'academico');
            $trabalho->discenteVinculo->update(['programa_curso_id' => $curso->id]);
            $trabalho->update([
                'titulo_en' => 'Biosystems Modeling', 'outras_informacoes' => 'Bolsa CAPES.', 'outras_informacoes_en' => 'CAPES grant.',
                'visibilidade' => 'publica', 'setor_cnae_id' => SetorCnae::where('codigo', '86')->value('id'),
            ]);
            $trabalho->areasCnpq()->sync([AreaCnpq::where('codigo', '40601005')->value('id')]);
        }

        return [$secretaria, $programa, $banca->fresh(), $discente, $orientador, $coorientador, $avaliadores];
    }

    /** @return array<string, string> */
    private function campos(Banca $banca): array
    {
        return collect(app(DadosLattesBancaService::class)->campos($banca))->pluck('valor', 'rotulo')->all();
    }

    public function test_campos_da_defesa_de_mestrado(): void
    {
        [, , $banca, $discente, $orientador, $coorientador, $avaliadores] = $this->bancaFinalizada();

        $c = $this->campos($banca);

        $this->assertSame('Mestrado', $c['Natureza']);
        $this->assertSame('Acadêmico', $c['Tipo']);
        $this->assertSame('Modelagem de Biossistemas', $c['Título']);
        $this->assertSame('2026', $c['Ano']);
        $this->assertSame('Brasil', $c['País']);
        $this->assertSame('Português', $c['Idioma']);
        $this->assertSame('https://ppgmsb.uneb.br/trabalhos', $c['Homepage do trabalho']);
        $this->assertSame($discente->name, $c['Nome do candidato']);
        $this->assertSame('UNEB', $c['Instituição']);
        $this->assertStringContainsString('Mestrado Acadêmico', $c['Curso']);
        foreach ([$orientador, $coorientador, ...$avaliadores, $discente] as $pessoa) {
            $this->assertStringContainsString($pessoa->name, $c['Participantes da banca']);
        }
        $this->assertSame('modelagem; simulação', $c['Palavras-chave']);
        $this->assertSame('Ciências da Saúde › Saúde Coletiva › Epidemiologia', $c['Áreas']);
        $this->assertStringContainsString('Atividades de atenção à saúde humana', $c['Setor de aplicação']);
        $this->assertSame('Bolsa CAPES.', $c['Outras informações']);
        $this->assertSame('Biosystems Modeling', $c['Título em inglês']);
        $this->assertSame('CAPES grant.', $c['Other information']);
        $this->assertSame('Pública', $c['Visibilidade']);
    }

    public function test_natureza_da_qualificacao_de_doutorado(): void
    {
        [, , $banca] = $this->bancaFinalizada('qualificacao', 'doutorado');

        $this->assertSame('Exame de qualificação de doutorado', $this->campos($banca)['Natureza']);
    }

    public function test_campos_vazios_aparecem_com_travessao(): void
    {
        [, , $banca, , , , $avaliadores] = $this->bancaFinalizada(completa: false);

        $c = $this->campos($banca);
        foreach (['Homepage do trabalho', 'Áreas', 'Setor de aplicação', 'Título em inglês', 'Outras informações', 'Other information', 'Tipo'] as $rotulo) {
            $this->assertSame('—', $c[$rotulo], $rotulo);
        }

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.lattes', $banca))->assertOk()->assertSee('—');
    }

    public function test_lista_mostra_bancas_finalizadas_de_que_participou_uma_vez_cada(): void
    {
        [, $programa, $banca, , $orientador, , $avaliadores] = $this->bancaFinalizada();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.index'))
            ->assertOk()
            ->assertSee('Bancas do '.$programa->sigla.' de que participou')
            ->assertSee(route('avaliacoes.lattes', $banca), false);

        $pagina = $this->actingAs($orientador)->get(route('avaliacoes.index'))->assertOk();
        $this->assertSame(1, substr_count($pagina->getContent(), route('avaliacoes.lattes', $banca)));
    }

    public function test_banca_nao_finalizada_ou_cancelada_fica_fora_e_quadro_da_403(): void
    {
        [, , $banca, , , , $avaliadores] = $this->bancaFinalizada();

        foreach (['em_avaliacao', 'cancelada'] as $status) {
            $banca->update(['status' => $status]);
            $this->actingAs($avaliadores[0])->get(route('avaliacoes.index'))
                ->assertOk()->assertDontSee(route('avaliacoes.lattes', $banca), false);
            $this->actingAs($avaliadores[0])->get(route('avaliacoes.lattes', $banca))->assertForbidden();
        }
    }

    public function test_quem_nao_participou_recebe_403(): void
    {
        [$secretaria, , $banca, $discente] = $this->bancaFinalizada();

        foreach ([User::factory()->docente()->create(), $discente, $secretaria] as $usuario) {
            $this->actingAs($usuario)->get(route('avaliacoes.lattes', $banca))->assertForbidden();
        }
    }

    public function test_quadro_mostra_os_campos_com_botao_de_copiar(): void
    {
        [, , $banca, , $orientador] = $this->bancaFinalizada();

        $this->actingAs($orientador)->get(route('avaliacoes.lattes', $banca))
            ->assertOk()
            ->assertSeeInOrder(['Natureza', 'Mestrado', 'Tipo', 'Acadêmico'])
            ->assertSee('Copiar');
    }

    public function test_externo_nao_ve_bancas_que_voce_orienta(): void
    {
        [, , , , , , $avaliadores] = $this->bancaFinalizada();

        $this->actingAs($avaliadores[1])->get(route('avaliacoes.index'))
            ->assertOk()
            ->assertDontSee('Bancas que você orienta');
    }
}
