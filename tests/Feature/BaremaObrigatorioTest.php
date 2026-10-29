<?php

namespace Tests\Feature;

use App\Models\Barema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class BaremaObrigatorioTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_homologacao_recusada_sem_barema_confidencial_da_qualificacao(): void
    {
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto('qualificacao', comBaremas: false);
        $this->criarBaremasAtivos($programa, ['qualificacao']);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors(['homologacao' => 'Barema de Qualificação Confidencial não cadastrado.']);

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
        Mail::assertNothingSent();
    }

    public function test_defesa_exige_barema_de_autoavaliacao(): void
    {
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto('defesa', comBaremas: false);
        $this->criarBaremasAtivos($programa, ['defesa', 'defesa_confidencial']);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors(['homologacao' => 'Barema de Autoavaliação do orientador não cadastrado.']);

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_barema_de_outro_nivel_ou_inativo_nao_conta(): void
    {
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto('qualificacao', comBaremas: false);
        $this->criarBaremasAtivos($programa, ['qualificacao']);
        $this->criarBaremasAtivos($programa, ['qualificacao_confidencial'], nivel: 'doutorado');
        $this->criarBaremasAtivos($programa, ['qualificacao_confidencial']);
        Barema::where('tipo', 'qualificacao_confidencial')->where('nivel', 'ambos')->update(['ativo' => false]);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors(['homologacao' => 'Barema de Qualificação Confidencial não cadastrado.']);
    }

    public function test_com_todos_os_baremas_ativos_homologa(): void
    {
        [$secretaria, , $banca] = $this->criarCenarioCompleto('defesa');

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))->assertSessionHasNoErrors();

        $this->assertSame('homologada', $banca->fresh()->homologacao_status);
    }

    public function test_tela_mostra_pendencia_de_barema(): void
    {
        [$secretaria, , $banca] = $this->criarCenarioCompleto('qualificacao', comBaremas: false);

        $this->actingAs($secretaria)->get(route('bancas.index'))
            ->assertOk()
            ->assertSee('Baremas cadastrados');

        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))
            ->assertOk()
            ->assertSee('Barema de Qualificação não cadastrado.');
    }
}
