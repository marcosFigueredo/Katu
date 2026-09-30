<?php

namespace Tests\Unit;

use Tests\TestCase;

class OllamaConfigTest extends TestCase
{
    public function test_modelo_de_embedding_tem_padrao_nomic_embed_text(): void
    {
        $this->assertSame('nomic-embed-text', config('ollama.embedding_model'));
    }
}
