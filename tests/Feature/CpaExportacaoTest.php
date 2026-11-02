<?php

namespace Tests\Feature;

use App\Models\AcessoCpa;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class CpaExportacaoTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    public function test_exporta_csv_com_blocos_e_sem_nomes_e_registra_acesso(): void
    {
        [, $programa, $banca, $discente, $orientador] = $this->criarCenarioCompleto('defesa', false);
        $banca->trabalhoAcademico->publicacoes()->delete();
        $banca->trabalhoAcademico->update(['titulo' => 'Título Secreto']);
        $banca->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'data_hora' => '2025-05-10 14:00:00']);
        $barema = Barema::create(['programa_id' => $programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $secao = $barema->secoes()->create(['nome' => 'Seção', 'peso' => 1, 'ordem' => 1, 'ativo' => true]);
        $item = BaremaItem::create(['barema_secao_id' => $secao->id, 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo('rigor')->id,
            'nome' => 'Clareza', 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true]);
        foreach ($banca->avaliadores()->get() as $membro) {
            $av = BancaAvaliacao::create(['banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id, 'status' => 'enviada', 'enviada_em' => now()]);
            $av->notas()->create(['barema_item_id' => $item->id, 'dimensao_qualidade_id' => $item->dimensao_qualidade_id, 'nota' => 8]);
        }
        PeriodoQualidade::create(['programa_id' => $programa->id, 'nome' => 'Quadriênio', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);
        $cpa = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $programa->id, 'user_id' => $cpa->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato']);

        $resposta = $this->actingAs($cpa)->get(route('cpa.exportar'));

        $resposta->assertOk();
        $this->assertStringContainsString('text/csv', $resposta->headers->get('Content-Type'));
        $csv = $resposta->streamedContent();
        foreach (['Panorama', 'Distribuição por dimensão', 'Evolução', 'Recortes', 'Pontos fortes', 'Confiabilidade', 'Rigor científico'] as $bloco) {
            $this->assertStringContainsString($bloco, $csv);
        }
        foreach ([$discente->name, $orientador->name, 'Título Secreto'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $csv);
        }
        $this->assertTrue(AcessoCpa::where('pagina', 'cpa.exportar')->exists());
    }

    public function test_nao_membro_nao_exporta(): void
    {
        $this->actingAs(User::factory()->docente()->create())->get(route('cpa.exportar'))->assertForbidden();
        $this->actingAs(User::factory()->secretaria()->create())->get(route('cpa.exportar'))->assertForbidden();
    }
}
