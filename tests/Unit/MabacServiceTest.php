<?php

namespace Tests\Unit;

use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\MabacAnalise;
use App\Models\Programa;
use App\Services\MabacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MabacServiceTest extends TestCase
{
    use RefreshDatabase;

    private MabacService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MabacService();
    }

    /**
     * Acessa o método privado calcularMabac via Reflection.
     */
    private function calcularMabac(array $matriz, $itens, $pesosNorm): array
    {
        $method = new ReflectionMethod(MabacService::class, 'calcularMabac');
        $method->setAccessible(true);
        return $method->invoke($this->service, $matriz, $itens, $pesosNorm);
    }

    private function criarItens(): array
    {
        $programa = Programa::create(['nome' => 'PPGMSB']);
        $barema   = Barema::create(['programa_id' => $programa->id, 'nome' => 'B', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => '1.0']);
        $secao    = BaremaSecao::create(['barema_id' => $barema->id, 'nome' => 'S1', 'ordem' => 1]);

        $item1 = BaremaItem::create(['barema_secao_id' => $secao->id, 'nome' => 'I1', 'nota_maxima' => 10, 'peso' => 1, 'ordem' => 1]);
        $item2 = BaremaItem::create(['barema_secao_id' => $secao->id, 'nome' => 'I2', 'nota_maxima' => 10, 'peso' => 1, 'ordem' => 2]);

        return [$item1, $item2];
    }

    // ── Testes do algoritmo ───────────────────────────────────────────────────

    public function test_ranking_correto_com_tres_alternativas(): void
    {
        [$i1, $i2] = $this->criarItens();

        $itens     = collect([$i1, $i2]);
        $pesosNorm = collect([$i1->id => 0.5, $i2->id => 0.5]);

        // A1 domina em I1, A2 domina em I2, A3 é mediano
        $matriz = [
            1 => [$i1->id => 8.0, $i2->id => 7.0],
            2 => [$i1->id => 6.0, $i2->id => 9.0],
            3 => [$i1->id => 7.0, $i2->id => 5.0],
        ];

        $result  = $this->calcularMabac($matriz, $itens, $pesosNorm);
        $escores = $result['escores'];

        // A1 deve ter escore maior que A2, que deve ter escore maior que A3
        $this->assertGreaterThan($escores[2], $escores[1]);
        $this->assertGreaterThan($escores[3], $escores[2]);
    }

    public function test_alternativas_identicas_tem_escore_igual(): void
    {
        [$i1, $i2] = $this->criarItens();

        $itens     = collect([$i1, $i2]);
        $pesosNorm = collect([$i1->id => 0.5, $i2->id => 0.5]);

        $matriz = [
            1 => [$i1->id => 8.0, $i2->id => 7.0],
            2 => [$i1->id => 8.0, $i2->id => 7.0],
        ];

        $result  = $this->calcularMabac($matriz, $itens, $pesosNorm);
        $escores = $result['escores'];

        $this->assertEqualsWithDelta($escores[1], $escores[2], 0.0001);
    }

    public function test_melhor_alternativa_tem_escore_positivo(): void
    {
        [$i1, $i2] = $this->criarItens();

        $itens     = collect([$i1, $i2]);
        $pesosNorm = collect([$i1->id => 0.5, $i2->id => 0.5]);

        $matriz = [
            1 => [$i1->id => 10.0, $i2->id => 10.0],
            2 => [$i1->id => 5.0,  $i2->id => 5.0],
            3 => [$i1->id => 0.0,  $i2->id => 0.0],
        ];

        $result  = $this->calcularMabac($matriz, $itens, $pesosNorm);
        $escores = $result['escores'];

        $this->assertGreaterThan(0, $escores[1], 'Melhor alternativa deve ter escore positivo');
    }

    public function test_pior_alternativa_tem_escore_negativo(): void
    {
        [$i1, $i2] = $this->criarItens();

        $itens     = collect([$i1, $i2]);
        $pesosNorm = collect([$i1->id => 0.5, $i2->id => 0.5]);

        $matriz = [
            1 => [$i1->id => 10.0, $i2->id => 10.0],
            2 => [$i1->id => 5.0,  $i2->id => 5.0],
            3 => [$i1->id => 0.0,  $i2->id => 0.0],
        ];

        $result  = $this->calcularMabac($matriz, $itens, $pesosNorm);
        $escores = $result['escores'];

        $this->assertLessThan(0, $escores[3], 'Pior alternativa deve ter escore negativo');
    }

    public function test_detalhes_contem_distancias_e_fronteira(): void
    {
        [$i1, $i2] = $this->criarItens();

        $itens     = collect([$i1, $i2]);
        $pesosNorm = collect([$i1->id => 0.5, $i2->id => 0.5]);

        $matriz = [
            1 => [$i1->id => 8.0, $i2->id => 7.0],
            2 => [$i1->id => 6.0, $i2->id => 9.0],
        ];

        $result   = $this->calcularMabac($matriz, $itens, $pesosNorm);
        $detalhes = $result['detalhes'];

        $this->assertArrayHasKey('distancias', $detalhes[1]);
        $this->assertArrayHasKey('fronteira',  $detalhes[1]);
        $this->assertArrayHasKey($i1->id, $detalhes[1]['distancias']);
        $this->assertArrayHasKey($i2->id, $detalhes[1]['distancias']);
    }

    public function test_fronteira_baa_e_media_geometrica(): void
    {
        [$i1, $i2] = $this->criarItens();

        $itens     = collect([$i1, $i2]);
        $pesosNorm = collect([$i1->id => 1.0, $i2->id => 0.0]);

        // Com peso 0 em i2 e peso 1 em i1:
        // nij para i1: banca1=1, banca2=0 → vij: banca1=1*(1+1)=2, banca2=1*(0+1)=1
        // BAA g_i1 = (2 * 1)^(1/2) = sqrt(2) ≈ 1.4142
        $matriz = [
            1 => [$i1->id => 10.0, $i2->id => 5.0],
            2 => [$i1->id => 0.0,  $i2->id => 5.0],
        ];

        $result   = $this->calcularMabac($matriz, $itens, $pesosNorm);
        $fronteira = $result['detalhes'][1]['fronteira'];

        $this->assertEqualsWithDelta(sqrt(2), $fronteira[$i1->id], 0.0001);
    }

    public function test_analise_sem_bancas_retorna_sem_resultados(): void
    {
        $programa = Programa::create(['nome' => 'PPGMSB']);
        $barema   = Barema::create(['programa_id' => $programa->id, 'nome' => 'B', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => '1.0']);

        $analise = MabacAnalise::create([
            'barema_id'      => $barema->id,
            'nome'           => 'Teste vazio',
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim'    => '2025-12-31',
            'criado_por'     => 'Teste',
        ]);

        $resultado = $this->service->analisar($analise);

        $this->assertTrue($resultado->resultados->isEmpty());
    }
}
