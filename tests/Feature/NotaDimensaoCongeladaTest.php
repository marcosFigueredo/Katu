<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoOrientadorNota;
use App\Models\BancaAvaliacaoNota;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class NotaDimensaoCongeladaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private function notas(Barema $barema, array $valores): array
    {
        $itens = $barema->secoes()->with('itens')->get()->flatMap->itens->sortBy('ordem')->values();

        return $itens->mapWithKeys(fn ($item, $i) => [$item->id => $valores[$i]])->all();
    }

    public function test_nota_do_avaliador_guarda_a_dimensao_e_nao_muda_com_reclassificacao(): void
    {
        [, , $banca, , , , , $avaliadores] = $this->criarCenarioCompleto('qualificacao');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);
        $barema = Barema::where('tipo', 'qualificacao')->firstOrFail();
        $rigor = DimensaoQualidade::doCodigo('rigor');
        $originalidade = DimensaoQualidade::doCodigo('originalidade');

        // Rascunho e depois envio: a dimensão é gravada e não duplica linhas
        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $barema]), ['notas' => $this->notas($barema, [7, 8]), 'acao' => 'rascunho']);
        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $barema]), ['notas' => $this->notas($barema, [7, 8]), 'acao' => 'enviar'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, BancaAvaliacaoNota::count());
        $this->assertSame(2, BancaAvaliacaoNota::where('dimensao_qualidade_id', $rigor->id)->count());

        // Reclassificar a pergunta depois não muda a nota já dada; a próxima avaliação usa a nova
        BaremaItem::whereHas('secao', fn ($q) => $q->where('barema_id', $barema->id))->update(['dimensao_qualidade_id' => $originalidade->id]);

        $this->assertSame(2, BancaAvaliacaoNota::where('dimensao_qualidade_id', $rigor->id)->count());

        $this->actingAs($avaliadores[1])->post(route('avaliacoes.salvar', [$banca, $barema]), ['notas' => $this->notas($barema, [6, 6]), 'acao' => 'enviar'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, BancaAvaliacaoNota::where('dimensao_qualidade_id', $originalidade->id)->count());
        $this->assertSame(2, BancaAvaliacaoNota::where('dimensao_qualidade_id', $rigor->id)->count());
    }

    public function test_autoavaliacao_guarda_a_dimensao(): void
    {
        [, , $banca, , $orientador] = $this->criarCenarioCompleto('defesa');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);
        $barema = Barema::where('tipo', 'autoavaliacao_orientador')->firstOrFail();

        $this->actingAs($orientador)->post(route('avaliacoes.autoavaliacao.store', $banca), ['notas' => $this->notas($barema, [9, 8])])
            ->assertSessionHasNoErrors();

        $processo = DimensaoQualidade::doCodigo('processo');
        $this->assertSame(2, AutoavaliacaoOrientadorNota::where('dimensao_qualidade_id', $processo->id)->count());
    }
}
