<?php

namespace Tests\Unit;

use App\Services\EmbeddingOllamaService;
use App\Services\IndexacaoBaseConhecimentoService;
use Tests\TestCase;

class IndexacaoBaseConhecimentoServiceChunkarTest extends TestCase
{
    private IndexacaoBaseConhecimentoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());
    }

    public function test_pagina_vazia_nao_gera_chunk(): void
    {
        $chunks = $this->service->chunkar([1 => '   ', 2 => '']);

        $this->assertSame([], $chunks);
    }

    public function test_texto_curto_vira_um_unico_chunk_com_a_pagina_correta(): void
    {
        $chunks = $this->service->chunkar([5 => 'A seção Aderência é obrigatória em todo trabalho.']);

        $this->assertCount(1, $chunks);
        $this->assertSame(5, $chunks[0]['pagina']);
        $this->assertSame(0, $chunks[0]['ordem']);
        $this->assertStringContainsString('Aderência', $chunks[0]['conteudo']);
    }

    public function test_texto_longo_e_quebrado_em_varios_chunks_respeitando_tamanho_alvo(): void
    {
        $frase = 'Esta é uma frase de exemplo com tamanho razoável para o teste. ';
        $textoLongo = str_repeat($frase, 30); // bem acima de 700 caracteres

        $chunks = $this->service->chunkar([1 => $textoLongo], tamanhoAlvo: 700);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(700 + mb_strlen($frase), mb_strlen($chunk['conteudo']));
        }
    }

    public function test_ordem_e_sequencial_entre_paginas(): void
    {
        $chunks = $this->service->chunkar([
            1 => 'Primeiro trecho da página um.',
            2 => 'Segundo trecho da página dois.',
        ]);

        $this->assertSame([0, 1], array_column($chunks, 'ordem'));
        $this->assertSame([1, 2], array_column($chunks, 'pagina'));
    }
}
