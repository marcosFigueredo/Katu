<?php

namespace Tests\Feature;

use App\Models\AtividadeFormativa;
use App\Models\AtividadeRealizada;
use App\Models\Banca;
use App\Models\Barema;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDadosPesquisa;
use App\Models\TrabalhoImpacto;
use App\Models\TrabalhoInformacoesAdicionais;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class InformacoesEstudanteBancaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    public function test_avaliador_ve_dados_da_pesquisa_da_copia_da_banca(): void
    {
        [, $banca, $avaliador] = $this->cenario('defesa');
        TrabalhoDadosPesquisa::create([
            'trabalho_academico_id' => $banca->trabalho_academico_id, 'codigo' => 'nao_houve', 'dados_tipos' => [],
            'dados_disponibilidade' => 'nao_se_aplica', 'seres_humanos' => true, 'caae' => '99999999.9.9999.9999',
            'parecer_numero' => '2', 'tcle' => 'termo', 'animais' => false, 'preenchido_em' => now(),
        ]);

        $this->actingAs($avaliador)->get($this->rotaForm($banca))
            ->assertOk()
            ->assertSee('Informações do estudante')
            ->assertSee('12345678.9.0000.0057')
            ->assertDontSee('99999999.9.9999.9999');
    }

    public function test_avaliador_da_defesa_ve_impacto_producao_e_informacoes_adicionais(): void
    {
        [, $banca, $avaliador] = $this->cenario('defesa');
        $trabalho = $banca->trabalhoAcademico;
        TrabalhoImpacto::create([
            'trabalho_academico_id' => $trabalho->id,
            'impacto_social_aplicavel' => true, 'impacto_social_descricao' => 'Apoio às equipes de vigilância do SUS.',
        ]);
        $atividade = AtividadeFormativa::create([
            'programa_id' => $trabalho->programa_id, 'nome' => 'Atividade Extensionista', 'quantidade_exigida' => 1,
            'etapa' => 'defesa', 'informada_por' => 'estudante', 'ordem' => 1,
        ]);
        AtividadeRealizada::create([
            'trabalho_academico_id' => $trabalho->id, 'atividade_formativa_id' => $atividade->id,
            'descricao' => 'Oficina na escola estadual', 'conferencia' => 'confirmada',
        ]);
        TrabalhoInformacoesAdicionais::create([
            'trabalho_academico_id' => $trabalho->id, 'conflito_interesses' => false, 'disponibilidade_dados' => 'nenhum_dado_novo',
            'amostras_fisicas' => false, 'aprovacao_etica' => true, 'aprovacao_etica_path' => 'informacoes-adicionais/x.pdf',
            'aprovacao_etica_nome' => 'parecer.pdf', 'participantes' => false, 'uso_ia' => true,
            'ia_ferramentas' => 'Claude Opus', 'ia_etapas' => 'Revisão de texto', 'originalidade_confirmada' => true, 'preenchido_em' => now(),
        ]);

        $this->actingAs($avaliador)->get($this->rotaForm($banca))
            ->assertOk()
            ->assertSee('Apoio às equipes de vigilância do SUS.')
            ->assertSee('Artigo derivado da dissertação')
            ->assertSee('Claude Opus')
            ->assertSee(route('bancas.aprovacao-etica', [$banca, 'documento' => 'aprovacao']), false)
            ->assertSee('Oficina na escola estadual')
            ->assertDontSee('Espelho');
    }

    public function test_qualificacao_mostra_dados_da_pesquisa_e_atividades(): void
    {
        [, $banca, $avaliador] = $this->cenario('qualificacao');
        TrabalhoImpacto::create([
            'trabalho_academico_id' => $banca->trabalho_academico_id,
            'impacto_social_aplicavel' => true, 'impacto_social_descricao' => 'Descrição que não aparece na qualificação.',
        ]);

        $this->actingAs($avaliador)->get($this->rotaForm($banca))
            ->assertOk()
            ->assertSee('12345678.9.0000.0057')
            ->assertSee('Atividades formativas')
            ->assertDontSee('Descrição que não aparece na qualificação.')
            ->assertDontSee('Produção associada')
            ->assertDontSee('Informações adicionais');
    }

    public function test_sem_copia_mostra_aviso(): void
    {
        [, $banca, $avaliador] = $this->cenario('defesa');
        $banca->update(['dados_pesquisa_enviados' => null]);

        $this->actingAs($avaliador)->get($this->rotaForm($banca))
            ->assertSee('Dados da pesquisa não enviados com este pedido.');
    }

    public function test_documento_de_etica_so_para_banca_orientador_e_secretaria(): void
    {
        Storage::fake('local');
        [$secretaria, $banca, $avaliador, $orientador, $discente] = $this->cenario('defesa');
        Storage::disk('local')->put('informacoes-adicionais/1/parecer.pdf', '%PDF-1.4 teste');
        TrabalhoInformacoesAdicionais::create([
            'trabalho_academico_id' => $banca->trabalho_academico_id, 'conflito_interesses' => false, 'disponibilidade_dados' => 'nenhum_dado_novo',
            'amostras_fisicas' => false, 'aprovacao_etica' => true, 'aprovacao_etica_path' => 'informacoes-adicionais/1/parecer.pdf',
            'aprovacao_etica_nome' => 'parecer.pdf', 'participantes' => false, 'uso_ia' => false, 'originalidade_confirmada' => true, 'preenchido_em' => now(),
        ]);
        $rota = route('bancas.aprovacao-etica', [$banca, 'documento' => 'aprovacao']);

        foreach ([$avaliador, $orientador, $secretaria] as $usuario) {
            $this->actingAs($usuario)->get($rota)->assertOk();
        }
        $this->actingAs(User::factory()->docente()->create())->get($rota)->assertForbidden();
        $this->actingAs($discente)->get($rota)->assertForbidden();

        [$outraSecretaria] = $this->criarCenarioCompleto('qualificacao');
        $this->actingAs($outraSecretaria)->get($rota)->assertNotFound();
    }

    public function test_tela_de_dados_da_pesquisa_diz_que_a_banca_ve_a_copia(): void
    {
        [, , , , $discente] = $this->cenario('qualificacao');

        $this->actingAs($discente)->get(route('meu-trabalho.dados-pesquisa.edit'))
            ->assertSee('é essa cópia que a banca vê');
    }

    private function rotaForm(Banca $banca): string
    {
        $barema = Barema::where('tipo', $banca->tipo)->firstOrFail();

        return route('avaliacoes.form', [$banca, $barema]);
    }

    /** Banca homologada com cópia dos dados da pesquisa (CAAE 12345678.9.0000.0057). */
    private function cenario(string $tipo): array
    {
        [$secretaria, , $banca, $discente, $orientador, , , $avaliadores] = $this->criarCenarioCompleto($tipo);
        $banca->update([
            'homologacao_status' => 'homologada',
            'dados_pesquisa_enviados' => [
                'codigo' => 'publico', 'codigo_link' => 'https://github.com/juliana/modelo', 'dados_disponibilidade' => 'nao_se_aplica',
                'dados_tipos' => [], 'seres_humanos' => true, 'caae' => '12345678.9.0000.0057', 'parecer_numero' => '6.543.210',
                'tcle' => 'termo', 'animais' => false,
            ],
        ]);

        return [$secretaria, $banca->fresh(), $avaliadores[0], $orientador, $discente];
    }
}
