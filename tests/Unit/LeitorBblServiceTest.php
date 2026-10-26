<?php

namespace Tests\Unit;

use App\Services\Latex\LeitorBblService;
use PHPUnit\Framework\TestCase;

class LeitorBblServiceTest extends TestCase
{
    private function ler(string $nome): array
    {
        return (new LeitorBblService)->ler(__DIR__.'/../fixtures/latex/bbl/'.$nome.'.bbl');
    }

    public function test_apacite(): void
    {
        $refs = $this->ler('apacite');

        $this->assertCount(3, $refs);
        $this->assertSame(['silva2021', 'costa2019', 'lima2025'], array_column($refs, 'chave'));
        $this->assertSame(['10.1590/s0034-8910.2021.001', null, '10.1234/cad.2025.77'], array_column($refs, 'doi'));
        $this->assertSame([2021, 2019, 2025], array_column($refs, 'ano'));
        $this->assertSame('Modelagem matemática em saúde coletiva', $refs[0]['titulo']);
        $this->assertStringNotContainsString('\\', $refs[0]['texto']);
        $this->assertStringContainsString('saúde', $refs[0]['texto']);
    }

    public function test_biblatex(): void
    {
        $refs = $this->ler('biblatex');

        $this->assertCount(3, $refs);
        $this->assertSame(['santos2024', 'pereira2020', 'nobel2023'], array_column($refs, 'chave'));
        $this->assertSame(['10.5555/rd.2024.9', null, '10.7777/mp.2023.1'], array_column($refs, 'doi'));
        $this->assertSame([2024, 2020, 2023], array_column($refs, 'ano'));
        $this->assertSame('Redes bayesianas na vigilância em saúde', $refs[0]['titulo']);
        $this->assertStringContainsString('Santos', $refs[0]['texto']);
        $this->assertStringNotContainsString('\\', $refs[1]['texto']);
    }

    public function test_plain(): void
    {
        $refs = $this->ler('plain');

        $this->assertCount(4, $refs);
        $this->assertSame(['alfa', 'beta', 'gama', 'delta'], array_column($refs, 'chave'));
        $this->assertSame(['10.2222/rx.2022.45', null, '10.3333/cz.2021.8', null], array_column($refs, 'doi'));
        $this->assertSame([2022, 2018, 2021, null], array_column($refs, 'ano'));
        $this->assertSame('Modelos de transmissão de doenças', $refs[0]['titulo']);
        $this->assertNull($refs[3]['titulo']);
    }

    public function test_arquivo_inexistente_devolve_vazio(): void
    {
        $this->assertSame([], (new LeitorBblService)->ler('/nao/existe.bbl'));
    }
}
