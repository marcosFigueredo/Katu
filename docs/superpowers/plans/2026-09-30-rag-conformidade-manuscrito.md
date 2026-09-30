# RAG de Conformidade do Manuscrito Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que a Secretaria cadastre, por programa, o manual de Normas e Procedimentos e o Template do PPGMSB; indexá-los em embeddings (RAG); e, ao discente enviar o manuscrito, rodar 4 verificações semânticas via Ollama que citam a norma correspondente — sempre consultivas, nunca bloqueando o envio à banca.

**Architecture:** Dois pipelines assíncronos (fila `database`, já usada no projeto). (1) Upload de PDF de referência pela Secretaria → `IndexarBaseConhecimentoJob` extrai texto (`smalot/pdfparser`), quebra em trechos e gera embeddings (`Ollama /api/embeddings`, modelo `nomic-embed-text`), grava em `base_conhecimento_chunks`. (2) Envio de manuscrito pelo discente → se o programa tiver `normas` indexado com sucesso, `AnalisarConformidadeRagJob` roda 4 verificações fixas: para cada uma, busca os trechos da norma mais similares (cosseno, calculado em PHP — corpus pequeno, sem banco vetorial), localiza o trecho correspondente no manuscrito e pede um veredito estruturado ao Ollama (`qwen2.5:7b`, `format: json`), gravando em `documento_conformidade_rag`.

**Tech Stack:** Laravel 12 / PHP 8.2+, MySQL 8+, `smalot/pdfparser` (já instalado), Ollama local (`qwen2.5:7b` para geração, `nomic-embed-text` novo para embeddings), fila `QUEUE_CONNECTION=database`.

**Spec:** `docs/superpowers/specs/2026-09-30-rag-conformidade-manuscrito-design.md`

## Global Constraints

- PHP 8.2+, Laravel 12, MySQL 8+ (`smalot/pdfparser: ^2.12` já está no `composer.json` — não reinstalar).
- Ollama local: `qwen2.5:7b` (geração, já configurado) + `nomic-embed-text` (embeddings, novo — requer `ollama pull nomic-embed-text` no ambiente onde o worker roda; documentar no README/handoff, não é código).
- Esta funcionalidade é **sempre consultiva**: nunca altera `libera_envio_trabalho`, nunca bloqueia `meu-trabalho.enviar-banca`, nunca impede o upload do manuscrito.
- Gate: a análise de conformidade só roda se o programa tiver `programa_documentos_referencia` do tipo `normas` com `status_indexacao = concluido`. `template` é sempre opcional.
- PDFs de referência ficam em `storage/app/private/` (disco `local`), nunca em disco público — mesmo padrão de `trabalho_documentos`.
- Rotas novas protegidas com `['auth', 'verified']`; acesso de escrita/gestão dos documentos de referência é exclusivo de `categoria = sec`, isolado pelo programa da Secretaria logada (`ContextoSecretaria`).
- Nomes de rotas seguem `recurso.acao` (ex.: `programa-documentos-referencia.index`), no padrão já usado por `programa-cursos.*`.
- Nunca lançar exceção não tratada de dentro de um job — cada job/serviço grava seu próprio status de erro (`falhou` + mensagem), mesmo padrão de `OllamaResumoService`.

## Review Focus

- Reenviar um documento de referência (Normas ou Template) substitui os chunks antigos daquele documento — não pode haver acumulação/duplicação de chunks de versões anteriores. (Task 7, Task 9)
- Falha do Ollama (HTTP erro, timeout, resposta vazia) durante a indexação não pode deixar `status_indexacao` travado em `processando` para sempre — precisa cair em `falhou` com mensagem. (Task 7, Task 8)
- Um programa sem `normas` indexada com sucesso (nunca enviou, ainda processando, ou falhou) não pode disparar `AnalisarConformidadeRagJob` nem quebrar o fluxo normal de envio do manuscrito. (Task 12)
- Uma das 4 verificações falhando (Ollama retorna erro ou JSON inválido) não pode impedir as outras 3 de rodarem e serem gravadas normalmente. (Task 10)
- Um PDF sem texto extraível (ex.: digitalizado sem OCR) não pode lançar exceção não tratada nem deixar o job "preso"; deve resultar em `falhou` com mensagem clara. (Task 7, Task 8)

---

## Task 1: Tabela e model `programa_documentos_referencia`

**Files:**
- Create: `database/migrations/2026_09_30_120000_create_programa_documentos_referencia_table.php`
- Create: `app/Models/ProgramaDocumentoReferencia.php`
- Modify: `app/Models/Programa.php` (adicionar relação `documentosReferencia()`)
- Test: `tests/Feature/ProgramaDocumentoReferenciaModelTest.php`

**Interfaces:**
- Produces: `ProgramaDocumentoReferencia` (fillable: `programa_id`, `tipo`, `arquivo_path`, `arquivo_nome_original`, `tamanho`, `hash_sha256`, `enviado_por`, `enviado_em`, `status_indexacao`, `indexado_em`, `erro_indexacao`); `Programa::documentosReferencia(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaDocumentoReferenciaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_programa_so_pode_ter_um_documento_por_tipo(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);

        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'pendente',
        ]);

        $this->expectException(QueryException::class);

        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas-v2.pdf',
            'arquivo_nome_original' => 'normas-v2.pdf',
            'status_indexacao' => 'pendente',
        ]);
    }

    public function test_relacao_programa_documentos_referencia(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'template',
            'arquivo_path' => 'normas-referencia/1/template.pdf',
            'arquivo_nome_original' => 'template.pdf',
            'status_indexacao' => 'pendente',
        ]);

        $this->assertCount(1, $programa->documentosReferencia);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProgramaDocumentoReferenciaModelTest`
Expected: FAIL — `Class "App\Models\ProgramaDocumentoReferencia" not found` (ou tabela inexistente).

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_documentos_referencia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->enum('tipo', ['normas', 'template']);

            $table->string('arquivo_path');
            $table->string('arquivo_nome_original');
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->string('hash_sha256')->nullable();

            $table->foreignId('enviado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('enviado_em')->nullable();

            $table->enum('status_indexacao', ['pendente', 'processando', 'concluido', 'falhou'])
                ->default('pendente');
            $table->timestamp('indexado_em')->nullable();
            $table->text('erro_indexacao')->nullable();

            $table->timestamps();

            $table->unique(['programa_id', 'tipo'], 'prog_doc_ref_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_documentos_referencia');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaDocumentoReferencia extends Model
{
    protected $table = 'programa_documentos_referencia';

    protected $fillable = [
        'programa_id',
        'tipo',
        'arquivo_path',
        'arquivo_nome_original',
        'tamanho',
        'hash_sha256',
        'enviado_por',
        'enviado_em',
        'status_indexacao',
        'indexado_em',
        'erro_indexacao',
    ];

    protected function casts(): array
    {
        return [
            'enviado_em' => 'datetime',
            'indexado_em' => 'datetime',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(BaseConhecimentoChunk::class);
    }
}
```

- [ ] **Step 5: Add the relation to `Programa`**

In `app/Models/Programa.php`, adicionar logo após `criteriosCpa()`:

```php
    public function documentosReferencia(): HasMany
    {
        return $this->hasMany(ProgramaDocumentoReferencia::class);
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProgramaDocumentoReferenciaModelTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_30_120000_create_programa_documentos_referencia_table.php app/Models/ProgramaDocumentoReferencia.php app/Models/Programa.php tests/Feature/ProgramaDocumentoReferenciaModelTest.php
git commit -m "feat: tabela e model de documentos de referência do programa"
```

---

## Task 2: Tabela e model `base_conhecimento_chunks`

**Files:**
- Create: `database/migrations/2026_09_30_120100_create_base_conhecimento_chunks_table.php`
- Create: `app/Models/BaseConhecimentoChunk.php`
- Test: `tests/Feature/BaseConhecimentoChunkModelTest.php`

**Interfaces:**
- Consumes: `ProgramaDocumentoReferencia` (Task 1).
- Produces: `BaseConhecimentoChunk` (fillable: `programa_documento_referencia_id`, `programa_id`, `tipo`, `pagina`, `ordem`, `conteudo`, `embedding`; `embedding` cast como `array`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseConhecimentoChunkModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_embedding_e_persistido_e_lido_como_array(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $documento = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'concluido',
        ]);

        $chunk = BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documento->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 3,
            'ordem' => 0,
            'conteudo' => 'A seção Aderência é obrigatória.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $fresco = BaseConhecimentoChunk::findOrFail($chunk->id);

        $this->assertIsArray($fresco->embedding);
        $this->assertSame([0.1, 0.2, 0.3], $fresco->embedding);
    }

    public function test_apagar_documento_referencia_apaga_seus_chunks(): void
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $documento = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'concluido',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documento->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 1,
            'ordem' => 0,
            'conteudo' => 'trecho',
            'embedding' => [0.1],
        ]);

        $documento->delete();

        $this->assertSame(0, BaseConhecimentoChunk::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BaseConhecimentoChunkModelTest`
Expected: FAIL — `Class "App\Models\BaseConhecimentoChunk" not found`.

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_conhecimento_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_documento_referencia_id')
                ->constrained('programa_documentos_referencia')
                ->cascadeOnDelete();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->enum('tipo', ['normas', 'template']);
            $table->unsignedInteger('pagina');
            $table->unsignedInteger('ordem');
            $table->text('conteudo');
            $table->json('embedding');

            $table->timestamps();

            $table->index(['programa_id', 'tipo'], 'base_conhec_chunks_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_conhecimento_chunks');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseConhecimentoChunk extends Model
{
    protected $table = 'base_conhecimento_chunks';

    protected $fillable = [
        'programa_documento_referencia_id',
        'programa_id',
        'tipo',
        'pagina',
        'ordem',
        'conteudo',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public function documentoReferencia(): BelongsTo
    {
        return $this->belongsTo(ProgramaDocumentoReferencia::class, 'programa_documento_referencia_id');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=BaseConhecimentoChunkModelTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_30_120100_create_base_conhecimento_chunks_table.php app/Models/BaseConhecimentoChunk.php tests/Feature/BaseConhecimentoChunkModelTest.php
git commit -m "feat: tabela e model dos trechos indexados (embeddings) da base de conhecimento"
```

---

## Task 3: Tabela e model `documento_conformidade_rag`

**Files:**
- Create: `database/migrations/2026_09_30_120200_create_documento_conformidade_rag_table.php`
- Create: `app/Models/DocumentoConformidadeRag.php`
- Modify: `app/Models/DocumentoAnalise.php` (adicionar relação `conformidadeRag()`)
- Test: `tests/Feature/DocumentoConformidadeRagModelTest.php`

**Interfaces:**
- Consumes: `DocumentoAnalise` (já existe em `app/Models/DocumentoAnalise.php`).
- Produces: `DocumentoConformidadeRag` (fillable: `documento_analise_id`, `verificacao`, `status`, `veredito`, `justificativa`, `trecho_norma_citado`, `erro`); `DocumentoAnalise::conformidadeRag(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoConformidadeRagModelTest extends TestCase
{
    use RefreshDatabase;

    private function criarAnalise(): DocumentoAnalise
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo_documento' => 'qualificacao',
            'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf',
            'arquivo_pdf_nome_original' => 'a.pdf',
            'status_analise' => 'concluida',
        ]);

        return DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);
    }

    public function test_relacao_documento_analise_conformidade_rag(): void
    {
        $analise = $this->criarAnalise();
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id,
            'verificacao' => 'aderencia',
            'status' => 'processando',
        ]);

        $this->assertCount(1, $analise->conformidadeRag);
    }

    public function test_nao_permite_verificacao_duplicada_na_mesma_analise(): void
    {
        $analise = $this->criarAnalise();
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id,
            'verificacao' => 'ods',
            'status' => 'processando',
        ]);

        $this->expectException(QueryException::class);

        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id,
            'verificacao' => 'ods',
            'status' => 'processando',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentoConformidadeRagModelTest`
Expected: FAIL — `Class "App\Models\DocumentoConformidadeRag" not found`.

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_conformidade_rag', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_analise_id')
                ->constrained('documento_analises')
                ->cascadeOnDelete();

            $table->string('verificacao');

            $table->enum('status', ['processando', 'concluida', 'falhou'])
                ->default('processando');

            $table->string('veredito')->nullable();
            $table->text('justificativa')->nullable();
            $table->text('trecho_norma_citado')->nullable();
            $table->text('erro')->nullable();

            $table->timestamps();

            $table->unique(['documento_analise_id', 'verificacao'], 'doc_conform_rag_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_conformidade_rag');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoConformidadeRag extends Model
{
    protected $table = 'documento_conformidade_rag';

    protected $fillable = [
        'documento_analise_id',
        'verificacao',
        'status',
        'veredito',
        'justificativa',
        'trecho_norma_citado',
        'erro',
    ];

    public function analise(): BelongsTo
    {
        return $this->belongsTo(DocumentoAnalise::class, 'documento_analise_id');
    }
}
```

- [ ] **Step 5: Add the relation to `DocumentoAnalise`**

Em `app/Models/DocumentoAnalise.php`, adicionar após o método `ollama()`:

```php
    public function conformidadeRag()
    {
        return $this->hasMany(DocumentoConformidadeRag::class, 'documento_analise_id');
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=DocumentoConformidadeRagModelTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_30_120200_create_documento_conformidade_rag_table.php app/Models/DocumentoConformidadeRag.php app/Models/DocumentoAnalise.php tests/Feature/DocumentoConformidadeRagModelTest.php
git commit -m "feat: tabela e model dos resultados de conformidade RAG por documento"
```

---

## Task 4: Config do modelo de embedding

**Files:**
- Modify: `config/ollama.php`
- Test: `tests/Unit/OllamaConfigTest.php`

**Interfaces:**
- Produces: `config('ollama.embedding_model')`, padrão `nomic-embed-text`, sobrescrevível por `OLLAMA_EMBEDDING_MODEL`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OllamaConfigTest`
Expected: FAIL — `config('ollama.embedding_model')` retorna `null`.

- [ ] **Step 3: Update the config file**

`config/ollama.php` completo:

```php
<?php

return [
    'url'              => env('OLLAMA_URL', 'http://localhost:11434'),
    'model'            => env('OLLAMA_MODEL', 'qwen2.5:7b'),
    'embedding_model'  => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'timeout'          => (int) env('OLLAMA_TIMEOUT', 120),
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=OllamaConfigTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/ollama.php tests/Unit/OllamaConfigTest.php
git commit -m "feat: configura modelo de embedding do Ollama (nomic-embed-text)"
```

---

## Task 5: `EmbeddingOllamaService`

**Files:**
- Create: `app/Services/EmbeddingOllamaService.php`
- Test: `tests/Unit/EmbeddingOllamaServiceTest.php`

**Interfaces:**
- Consumes: `config('ollama.url')`, `config('ollama.embedding_model')`, `config('ollama.timeout')` (Task 4).
- Produces: `EmbeddingOllamaService::embedder(string $texto): array` (lança `RuntimeException` em falha); `EmbeddingOllamaService::similaridadeCosseno(array $a, array $b): float`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Services\EmbeddingOllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingOllamaServiceTest extends TestCase
{
    public function test_embedder_retorna_vetor_da_api_do_ollama(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);

        $vetor = (new EmbeddingOllamaService())->embedder('texto de teste');

        $this->assertSame([0.1, 0.2, 0.3], $vetor);
    }

    public function test_embedder_lanca_excecao_em_erro_http(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response('erro', 500),
        ]);

        $this->expectException(\RuntimeException::class);

        (new EmbeddingOllamaService())->embedder('texto de teste');
    }

    public function test_embedder_lanca_excecao_quando_resposta_sem_embedding(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => []]),
        ]);

        $this->expectException(\RuntimeException::class);

        (new EmbeddingOllamaService())->embedder('texto de teste');
    }

    public function test_similaridade_cosseno_de_vetores_identicos_e_um(): void
    {
        $service = new EmbeddingOllamaService();

        $this->assertEqualsWithDelta(1.0, $service->similaridadeCosseno([1, 0, 0], [1, 0, 0]), 0.0001);
    }

    public function test_similaridade_cosseno_de_vetores_ortogonais_e_zero(): void
    {
        $service = new EmbeddingOllamaService();

        $this->assertEqualsWithDelta(0.0, $service->similaridadeCosseno([1, 0], [0, 1]), 0.0001);
    }

    public function test_similaridade_cosseno_com_vetor_vazio_retorna_zero(): void
    {
        $service = new EmbeddingOllamaService();

        $this->assertSame(0.0, $service->similaridadeCosseno([1, 2, 3], []));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmbeddingOllamaServiceTest`
Expected: FAIL — `Class "App\Services\EmbeddingOllamaService" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingOllamaService
{
    /**
     * Gera o embedding (vetor) de um texto via Ollama. Lança RuntimeException em falha —
     * quem chama decide como tratar (diferente de OllamaResumoService, que nunca lança).
     */
    public function embedder(string $texto): array
    {
        $response = Http::timeout(config('ollama.timeout'))
            ->post(config('ollama.url') . '/api/embeddings', [
                'model'  => config('ollama.embedding_model'),
                'prompt' => $texto,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ollama HTTP ' . $response->status() . ' ao gerar embedding: ' . mb_substr($response->body(), 0, 200)
            );
        }

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || empty($embedding)) {
            throw new RuntimeException('Ollama não retornou embedding válido.');
        }

        return $embedding;
    }

    public function similaridadeCosseno(array $a, array $b): float
    {
        $n = min(count($a), count($b));

        if ($n === 0) {
            return 0.0;
        }

        $produtoEscalar = 0.0;
        $normaA = 0.0;
        $normaB = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $produtoEscalar += $a[$i] * $b[$i];
            $normaA += $a[$i] ** 2;
            $normaB += $b[$i] ** 2;
        }

        if ($normaA <= 0.0 || $normaB <= 0.0) {
            return 0.0;
        }

        return $produtoEscalar / (sqrt($normaA) * sqrt($normaB));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EmbeddingOllamaServiceTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/EmbeddingOllamaService.php tests/Unit/EmbeddingOllamaServiceTest.php
git commit -m "feat: serviço de embeddings e similaridade de cosseno via Ollama"
```

---

## Task 6: `IndexacaoBaseConhecimentoService::chunkar()`

**Files:**
- Create: `app/Services/IndexacaoBaseConhecimentoService.php`
- Test: `tests/Unit/IndexacaoBaseConhecimentoServiceChunkarTest.php`

**Interfaces:**
- Produces: `IndexacaoBaseConhecimentoService::chunkar(array $textosPorPagina, int $tamanhoAlvo = 700): array` — retorna lista de `['pagina' => int, 'ordem' => int, 'conteudo' => string]`.

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IndexacaoBaseConhecimentoServiceChunkarTest`
Expected: FAIL — `Class "App\Services\IndexacaoBaseConhecimentoService" not found`.

- [ ] **Step 3: Write the implementation (apenas `chunkar()` por enquanto)**

```php
<?php

namespace App\Services;

class IndexacaoBaseConhecimentoService
{
    public function __construct(private readonly EmbeddingOllamaService $embeddings)
    {
    }

    /**
     * Quebra o texto extraído do PDF (por página) em trechos de ~$tamanhoAlvo
     * caracteres, respeitando limites de frase quando possível, mantendo a
     * página de origem para citação.
     *
     * @param array<int, string> $textosPorPagina
     * @return array<int, array{pagina: int, ordem: int, conteudo: string}>
     */
    public function chunkar(array $textosPorPagina, int $tamanhoAlvo = 700): array
    {
        $chunks = [];
        $ordem = 0;

        foreach ($textosPorPagina as $pagina => $texto) {
            $texto = trim(preg_replace('/\s+/', ' ', (string) $texto));

            if ($texto === '') {
                continue;
            }

            $frases = preg_split('/(?<=[.\?\!])\s+/u', $texto) ?: [$texto];
            $buffer = '';

            foreach ($frases as $frase) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($frase) + 1 > $tamanhoAlvo) {
                    $chunks[] = ['pagina' => (int) $pagina, 'ordem' => $ordem++, 'conteudo' => trim($buffer)];
                    $buffer = '';
                }

                $buffer .= ($buffer === '' ? '' : ' ') . $frase;
            }

            if (trim($buffer) !== '') {
                $chunks[] = ['pagina' => (int) $pagina, 'ordem' => $ordem++, 'conteudo' => trim($buffer)];
            }
        }

        return $chunks;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=IndexacaoBaseConhecimentoServiceChunkarTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IndexacaoBaseConhecimentoService.php tests/Unit/IndexacaoBaseConhecimentoServiceChunkarTest.php
git commit -m "feat: chunking de texto extraído do PDF para indexação"
```

---

## Task 7: `IndexacaoBaseConhecimentoService::indexar()`

**Files:**
- Modify: `app/Services/IndexacaoBaseConhecimentoService.php` (adicionar método `indexar()`)
- Test: `tests/Feature/IndexacaoBaseConhecimentoServiceIndexarTest.php`

**Interfaces:**
- Consumes: `EmbeddingOllamaService::embedder()` (Task 5), `chunkar()` (Task 6), `BaseConhecimentoChunk` (Task 2), `ProgramaDocumentoReferencia` (Task 1).
- Produces: `IndexacaoBaseConhecimentoService::indexar(ProgramaDocumentoReferencia $documento, array $textosPorPagina): void` — lança `RuntimeException` se nada puder ser indexado; quem chama decide o que fazer com o status (o job, na Task 8).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Services\EmbeddingOllamaService;
use App\Services\IndexacaoBaseConhecimentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndexacaoBaseConhecimentoServiceIndexarTest extends TestCase
{
    use RefreshDatabase;

    private function documento(): ProgramaDocumentoReferencia
    {
        $programa = Programa::create(['nome' => 'PPG']);

        return ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'processando',
        ]);
    }

    public function test_indexa_chunks_com_embeddings_gerados(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $service->indexar($documento, [1 => 'A seção Aderência ao Programa é obrigatória.']);

        $chunks = BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->get();
        $this->assertCount(1, $chunks);
        $this->assertSame([0.1, 0.2, 0.3], $chunks->first()->embedding);
        $this->assertSame($documento->programa_id, $chunks->first()->programa_id);
        $this->assertSame('normas', $chunks->first()->tipo);
    }

    public function test_reindexar_substitui_chunks_antigos(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.5]]),
        ]);
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $service->indexar($documento, [1 => 'Conteúdo da primeira versão do documento.']);
        $this->assertSame(1, BaseConhecimentoChunk::count());

        $service->indexar($documento, [1 => 'Conteúdo totalmente novo.', 2 => 'Mais um trecho novo aqui.']);

        $this->assertSame(2, BaseConhecimentoChunk::count());
        $this->assertSame(
            ['Conteúdo totalmente novo.', 'Mais um trecho novo aqui.'],
            BaseConhecimentoChunk::orderBy('ordem')->pluck('conteudo')->all()
        );
    }

    public function test_lanca_excecao_quando_pdf_nao_tem_texto_extraivel(): void
    {
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $this->expectException(\RuntimeException::class);

        $service->indexar($documento, [1 => '   ', 2 => '']);
    }

    public function test_falha_de_um_chunk_nao_impede_os_demais(): void
    {
        $chamadas = 0;
        Http::fake(function () use (&$chamadas) {
            $chamadas++;

            return $chamadas === 1
                ? Http::response('erro', 500)
                : Http::response(['embedding' => [0.9]]);
        });
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $service->indexar($documento, [
            1 => str_repeat('Primeiro trecho bem distinto do documento. ', 20),
            2 => 'Segundo trecho, completamente separado.',
        ]);

        $this->assertGreaterThanOrEqual(1, BaseConhecimentoChunk::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IndexacaoBaseConhecimentoServiceIndexarTest`
Expected: FAIL — `Call to undefined method App\Services\IndexacaoBaseConhecimentoService::indexar()`.

- [ ] **Step 3: Write the implementation**

Adicionar ao final de `app/Services/IndexacaoBaseConhecimentoService.php` (dentro da classe, depois de `chunkar()`):

```php
    /**
     * Quebra, embeda e persiste os chunks de um documento de referência,
     * substituindo os chunks anteriores daquele documento. Lança
     * RuntimeException se nenhum trecho puder ser indexado (PDF sem texto
     * extraível, ou 100% das chamadas de embedding falhando).
     *
     * @param array<int, string> $textosPorPagina
     */
    public function indexar(ProgramaDocumentoReferencia $documento, array $textosPorPagina): void
    {
        $chunks = $this->chunkar($textosPorPagina);

        if (empty($chunks)) {
            throw new \RuntimeException('Nenhum texto extraível foi encontrado no PDF.');
        }

        BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->delete();

        $indexados = 0;

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->embeddings->embedder($chunk['conteudo']);

                BaseConhecimentoChunk::create([
                    'programa_documento_referencia_id' => $documento->id,
                    'programa_id' => $documento->programa_id,
                    'tipo' => $documento->tipo,
                    'pagina' => $chunk['pagina'],
                    'ordem' => $chunk['ordem'],
                    'conteudo' => $chunk['conteudo'],
                    'embedding' => $embedding,
                ]);

                $indexados++;
            } catch (\Throwable $e) {
                continue;
            }
        }

        if ($indexados === 0) {
            throw new \RuntimeException('Nenhum trecho pôde ser indexado (falha ao gerar embeddings).');
        }
    }
```

Adicionar os imports no topo do arquivo:

```php
use App\Models\BaseConhecimentoChunk;
use App\Models\ProgramaDocumentoReferencia;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=IndexacaoBaseConhecimentoServiceIndexarTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IndexacaoBaseConhecimentoService.php tests/Feature/IndexacaoBaseConhecimentoServiceIndexarTest.php
git commit -m "feat: indexação de chunks com embeddings, substituindo versões anteriores"
```

---

## Task 8: `IndexarBaseConhecimentoJob`

**Files:**
- Create: `app/Jobs/IndexarBaseConhecimentoJob.php`
- Test: `tests/Feature/IndexarBaseConhecimentoJobTest.php`

**Interfaces:**
- Consumes: `IndexacaoBaseConhecimentoService::indexar()` (Task 7), `ProgramaDocumentoReferencia` (Task 1).
- Produces: job `IndexarBaseConhecimentoJob::dispatch(ProgramaDocumentoReferencia $documento)`; ao concluir, `status_indexacao` = `concluido`/`falhou` no registro.

Este teste usa o PDF real `docs/dissertacao.pdf` (já no repositório, 26 páginas) como conteúdo do arquivo de referência, copiado para o disco fake — assim o parsing real do `smalot/pdfparser` é exercitado sem depender de um PDF fabricado à mão.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Jobs\IndexarBaseConhecimentoJob;
use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Services\IndexacaoBaseConhecimentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndexarBaseConhecimentoJobTest extends TestCase
{
    use RefreshDatabase;

    private function documentoComPdfReal(): ProgramaDocumentoReferencia
    {
        Storage::fake('local');
        $programa = Programa::create(['nome' => 'PPG']);
        $caminho = 'normas-referencia/' . $programa->id . '/template.pdf';
        Storage::disk('local')->put($caminho, file_get_contents(base_path('docs/dissertacao.pdf')));

        return ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'template',
            'arquivo_path' => $caminho,
            'arquivo_nome_original' => 'dissertacao.pdf',
            'status_indexacao' => 'pendente',
        ]);
    }

    public function test_job_indexa_pdf_real_e_marca_concluido(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);
        $documento = $this->documentoComPdfReal();

        (new IndexarBaseConhecimentoJob($documento))->handle(app(IndexacaoBaseConhecimentoService::class));

        $documento->refresh();
        $this->assertSame('concluido', $documento->status_indexacao);
        $this->assertNotNull($documento->indexado_em);
        $this->assertNull($documento->erro_indexacao);
        $this->assertGreaterThan(0, BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->count());
    }

    public function test_job_marca_falhou_quando_ollama_esta_indisponivel(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response('indisponível', 500),
        ]);
        $documento = $this->documentoComPdfReal();

        (new IndexarBaseConhecimentoJob($documento))->handle(app(IndexacaoBaseConhecimentoService::class));

        $documento->refresh();
        $this->assertSame('falhou', $documento->status_indexacao);
        $this->assertNotNull($documento->erro_indexacao);
        $this->assertSame(0, BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IndexarBaseConhecimentoJobTest`
Expected: FAIL — `Class "App\Jobs\IndexarBaseConhecimentoJob" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Jobs;

use App\Models\ProgramaDocumentoReferencia;
use App\Services\IndexacaoBaseConhecimentoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class IndexarBaseConhecimentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(private readonly ProgramaDocumentoReferencia $documento)
    {
    }

    public function handle(IndexacaoBaseConhecimentoService $service): void
    {
        $this->documento->update(['status_indexacao' => 'processando']);

        try {
            $caminhoPdf = Storage::disk('local')->path($this->documento->arquivo_path);
            $paginas = (new Parser())->parseFile($caminhoPdf)->getPages();

            $textosPorPagina = [];
            foreach ($paginas as $i => $pagina) {
                $textosPorPagina[$i + 1] = trim($pagina->getText());
            }

            $service->indexar($this->documento, $textosPorPagina);

            $this->documento->update([
                'status_indexacao' => 'concluido',
                'indexado_em' => now(),
                'erro_indexacao' => null,
            ]);
        } catch (Throwable $e) {
            $this->documento->update([
                'status_indexacao' => 'falhou',
                'erro_indexacao' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=IndexarBaseConhecimentoJobTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/IndexarBaseConhecimentoJob.php tests/Feature/IndexarBaseConhecimentoJobTest.php
git commit -m "feat: job de indexação assíncrona dos documentos de referência"
```

---

## Task 9: Módulo da Secretaria — upload de documentos de referência

**Files:**
- Create: `app/Http/Controllers/ProgramaDocumentoReferenciaController.php`
- Create: `resources/views/programas/documentos-referencia.blade.php`
- Modify: `routes/web.php:68` (adicionar rotas logo após `programa-cursos.regras`, dentro do mesmo grupo)
- Modify: `resources/views/layouts/app.blade.php:255` (adicionar link no menu, logo após o link "Cursos")
- Test: `tests/Feature/ProgramaDocumentoReferenciaControllerTest.php`

**Interfaces:**
- Consumes: `ContextoSecretaria::exigirPrograma()` (já existe), `IndexarBaseConhecimentoJob::dispatch()` (Task 8), `ProgramaDocumentoReferencia` (Task 1).
- Produces: rotas `programa-documentos-referencia.index` (GET) e `programa-documentos-referencia.store` (POST).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Jobs\IndexarBaseConhecimentoJob;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramaDocumentoReferenciaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function secretariaComPrograma(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        return [$sec, $programa];
    }

    public function test_secretaria_envia_documento_de_normas_e_despacha_indexacao(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('normas.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('programa-documentos-referencia.index'));

        $documento = ProgramaDocumentoReferencia::firstOrFail();
        $this->assertSame('normas', $documento->tipo);
        $this->assertSame('pendente', $documento->status_indexacao);
        Storage::disk('local')->assertExists($documento->arquivo_path);
        Queue::assertPushed(IndexarBaseConhecimentoJob::class);
    }

    public function test_reenviar_documento_substitui_o_anterior_e_apaga_arquivo_antigo(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$sec, $programa] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
        ]);
        $primeiro = ProgramaDocumentoReferencia::firstOrFail();
        $caminhoAntigo = $primeiro->arquivo_path;

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame(1, ProgramaDocumentoReferencia::where('programa_id', $programa->id)->where('tipo', 'normas')->count());
        Storage::disk('local')->assertMissing($caminhoAntigo);
    }

    public function test_arquivo_nao_pdf_e_recusado(): void
    {
        Storage::fake('local');
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('normas.docx', 100, 'application/msword'),
        ])->assertSessionHasErrors('arquivo');
        $this->assertDatabaseCount('programa_documentos_referencia', 0);
    }

    public function test_docente_nao_acessa_upload_de_documentos_de_referencia(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('programa-documentos-referencia.index'))->assertStatus(403);
    }

    public function test_tela_lista_normas_e_template_com_status(): void
    {
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->get(route('programa-documentos-referencia.index'))
            ->assertOk()
            ->assertSee('Normas e Procedimentos')
            ->assertSee('Template')
            ->assertSee('Não enviado');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProgramaDocumentoReferenciaControllerTest`
Expected: FAIL — rota `programa-documentos-referencia.store` não existe.

- [ ] **Step 3: Add the routes**

Em `routes/web.php`, dentro do grupo que já contém `programa-cursos.*` (logo após a linha de `programa-cursos.regras`, antes do `});` de fechamento do grupo):

```php
    Route::get('/programa/documentos-referencia', [ProgramaDocumentoReferenciaController::class, 'index'])
        ->name('programa-documentos-referencia.index');
    Route::post('/programa/documentos-referencia', [ProgramaDocumentoReferenciaController::class, 'store'])
        ->name('programa-documentos-referencia.store');
```

Adicionar o `use` no topo do arquivo, junto aos demais `use App\Http\Controllers\...`:

```php
use App\Http\Controllers\ProgramaDocumentoReferenciaController;
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\IndexarBaseConhecimentoJob;
use App\Models\ProgramaDocumentoReferencia;
use App\Services\ContextoSecretaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProgramaDocumentoReferenciaController extends Controller
{
    public function __construct(private readonly ContextoSecretaria $contexto)
    {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma(auth()->user());

        $documentos = ProgramaDocumentoReferencia::where('programa_id', $programa->id)
            ->get()
            ->keyBy('tipo');

        return view('programas.documentos-referencia', compact('programa', 'documentos'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());

        $data = $request->validate([
            'tipo' => ['required', Rule::in(['normas', 'template'])],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:30720'],
        ], [
            'tipo.required' => 'Tipo de documento inválido.',
            'arquivo.required' => 'Selecione um arquivo PDF.',
            'arquivo.mimes' => 'O arquivo deve ser um PDF.',
            'arquivo.max' => 'O PDF não pode ultrapassar 30 MB.',
        ]);

        $anterior = ProgramaDocumentoReferencia::where('programa_id', $programa->id)
            ->where('tipo', $data['tipo'])
            ->first();

        if ($anterior) {
            Storage::disk('local')->delete($anterior->arquivo_path);
        }

        $arquivo = $request->file('arquivo');
        $destino = "normas-referencia/{$programa->id}/{$data['tipo']}.pdf";

        Storage::disk('local')->put($destino, file_get_contents($arquivo->path()));
        $caminhoFisico = Storage::disk('local')->path($destino);

        $documento = ProgramaDocumentoReferencia::updateOrCreate(
            ['programa_id' => $programa->id, 'tipo' => $data['tipo']],
            [
                'arquivo_path' => $destino,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'tamanho' => filesize($caminhoFisico),
                'hash_sha256' => hash_file('sha256', $caminhoFisico),
                'enviado_por' => auth()->id(),
                'enviado_em' => now(),
                'status_indexacao' => 'pendente',
                'indexado_em' => null,
                'erro_indexacao' => null,
            ]
        );

        IndexarBaseConhecimentoJob::dispatch($documento);

        $rotulo = $data['tipo'] === 'normas' ? 'Normas e Procedimentos' : 'Template';

        return redirect()->route('programa-documentos-referencia.index')
            ->with('status', "{$rotulo} enviado. A indexação será processada em segundo plano.");
    }
}
```

- [ ] **Step 5: Write the view**

`resources/views/programas/documentos-referencia.blade.php`:

```blade
<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Base de Conhecimento (IA)</h1>
            <p class="text-muted mb-0">
                {{ $programa->nome }} — envie o manual de Normas e Procedimentos e o Template para habilitar a
                análise de conformidade do manuscrito por IA. O Template é opcional.
            </p>
        </div>

        <div class="row g-4">
            @foreach ([
                'normas' => ['titulo' => 'Normas e Procedimentos', 'obrigatorio' => true],
                'template' => ['titulo' => 'Template', 'obrigatorio' => false],
            ] as $tipo => $info)
                @php $doc = $documentos->get($tipo); @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="h6 fw-bold mb-0">
                                    {{ $info['titulo'] }}
                                    @if ($info['obrigatorio'])
                                        <span class="badge text-bg-secondary ms-1">obrigatório</span>
                                    @endif
                                </h2>
                                @php
                                    $statusCor = match($doc?->status_indexacao) {
                                        'concluido' => 'success',
                                        'processando', 'pendente' => 'warning',
                                        'falhou' => 'danger',
                                        default => 'secondary',
                                    };
                                    $statusLabel = match($doc?->status_indexacao) {
                                        'concluido' => 'Indexado',
                                        'processando' => 'Indexando…',
                                        'pendente' => 'Pendente',
                                        'falhou' => 'Falhou',
                                        default => 'Não enviado',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $statusCor }}">{{ $statusLabel }}</span>
                            </div>

                            @if ($doc)
                                <p class="small text-muted mb-1">{{ $doc->arquivo_nome_original }}</p>
                                @if ($doc->status_indexacao === 'concluido')
                                    <p class="small text-muted mb-2">Indexado em {{ $doc->indexado_em?->format('d/m/Y H:i') }}</p>
                                @elseif ($doc->status_indexacao === 'falhou')
                                    <p class="small text-danger mb-2">{{ $doc->erro_indexacao }}</p>
                                @endif
                            @else
                                <p class="small text-muted mb-2">Nenhum arquivo enviado ainda.</p>
                            @endif

                            <form method="POST" action="{{ route('programa-documentos-referencia.store') }}" enctype="multipart/form-data" class="mt-2">
                                @csrf
                                <input type="hidden" name="tipo" value="{{ $tipo }}">
                                <div class="input-group">
                                    <input type="file" name="arquivo" accept="application/pdf" class="form-control" required>
                                    <button type="submit" class="btn btn-primary">
                                        {{ $doc ? 'Substituir' : 'Enviar' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add the menu link**

Em `resources/views/layouts/app.blade.php`, logo após o bloco do link "Cursos" (que termina em `</a>` seguido de linha em branco, antes de `@php($programaMenu = ...)`), adicionar:

```blade
                            <a href="{{ route('programa-documentos-referencia.index') }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                                      {{ request()->routeIs('programa-documentos-referencia.*') ? 'bg-gray-200 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
                                <i class="bi bi-robot" style="font-size: 15px; flex-shrink:0;"></i>
                                Base de Conhecimento (IA)
                            </a>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=ProgramaDocumentoReferenciaControllerTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ProgramaDocumentoReferenciaController.php resources/views/programas/documentos-referencia.blade.php routes/web.php resources/views/layouts/app.blade.php tests/Feature/ProgramaDocumentoReferenciaControllerTest.php
git commit -m "feat: tela da Secretaria para upload de Normas e Template (base de conhecimento do RAG)"
```

---

## Task 10: `ConformidadeNormasService`

**Files:**
- Create: `app/Services/ConformidadeNormasService.php`
- Test: `tests/Feature/ConformidadeNormasServiceTest.php`

**Interfaces:**
- Consumes: `EmbeddingOllamaService` (Task 5), `BaseConhecimentoChunk` (Task 2), `DocumentoConformidadeRag` (Task 3), `DocumentoAnalise` (existente).
- Produces: `ConformidadeNormasService::analisar(DocumentoAnalise $analise, int $programaId, array $textosPorPagina): void` — nunca lança exceção; grava um `DocumentoConformidadeRag` por verificação (`aderencia`, `ods`, `citacao_apa`, `uso_ia`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\BaseConhecimentoChunk;
use App\Models\DocumentoAnalise;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoConformidadeRag;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\ConformidadeNormasService;
use App\Services\EmbeddingOllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConformidadeNormasServiceTest extends TestCase
{
    use RefreshDatabase;

    private function analiseComChunks(): array
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $documentoRef = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'concluido',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documentoRef->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 10,
            'ordem' => 0,
            'conteudo' => 'A seção Aderência deve apresentar o texto-base obrigatório sobre a relação do trabalho com o programa.',
            'embedding' => [1, 0, 0],
        ]);

        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo_documento' => 'qualificacao',
            'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf',
            'arquivo_pdf_nome_original' => 'a.pdf',
            'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);

        return [$analise, $programa->id];
    }

    public function test_grava_as_quatro_verificacoes_quando_ollama_responde_bem(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'conforme',
                'justificativa' => 'Atende ao exigido.',
                'trecho_norma_citado' => 'A seção Aderência deve...',
            ])]),
        ]);
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programaId, [1 => 'Aderência ao Programa: este trabalho se relaciona com...']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'concluida' && $r->veredito === 'conforme'));
    }

    public function test_uma_verificacao_falhando_nao_impede_as_demais(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'embeddings')) {
                return Http::response(['embedding' => [1, 0, 0]]);
            }

            $body = $request->data();
            if (str_contains($body['prompt'] ?? '', 'Uso de Inteligência Artificial')) {
                return Http::response('erro interno', 500);
            }

            return Http::response(['response' => json_encode([
                'veredito' => 'parcial',
                'justificativa' => 'Parcialmente atendido.',
                'trecho_norma_citado' => 'trecho',
            ])]);
        });
        [$analise, $programaId] = $this->analiseComChunks();
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programaId, [1 => 'texto qualquer do manuscrito']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get()->keyBy('verificacao');
        $this->assertSame('falhou', $registros['uso_ia']->status);
        $this->assertSame('concluida', $registros['aderencia']->status);
        $this->assertSame('concluida', $registros['ods']->status);
        $this->assertSame('concluida', $registros['citacao_apa']->status);
    }

    public function test_sem_chunks_indexados_marca_verificacao_como_falhou(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
        ]);
        $programa = Programa::create(['nome' => 'PPG Sem Base']);
        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf', 'arquivo_pdf_nome_original' => 'a.pdf', 'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);
        $service = app(ConformidadeNormasService::class);

        $service->analisar($analise, $programa->id, [1 => 'texto qualquer']);

        $registros = DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get();
        $this->assertCount(4, $registros);
        $this->assertTrue($registros->every(fn ($r) => $r->status === 'falhou'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ConformidadeNormasServiceTest`
Expected: FAIL — `Class "App\Services\ConformidadeNormasService" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services;

use App\Models\BaseConhecimentoChunk;
use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ConformidadeNormasService
{
    private const VERIFICACOES = [
        'aderencia' => [
            'titulo' => 'Seção "Aderência"',
            'pergunta' => 'Qual o conteúdo obrigatório da seção Aderência ao Programa na dissertação?',
            'termos_busca' => ['aderência ao programa', 'aderência'],
        ],
        'ods' => [
            'titulo' => 'ODS do Trabalho',
            'pergunta' => 'Como o trabalho deve declarar os Objetivos de Desenvolvimento Sustentável (ODS) relacionados à pesquisa?',
            'termos_busca' => ['ods do trabalho', 'objetivos de desenvolvimento sustentável'],
        ],
        'citacao_apa' => [
            'titulo' => 'Padrão de citação (APA)',
            'pergunta' => 'Qual o padrão normativo adotado para citações e organização da lista de referências bibliográficas?',
            'termos_busca' => ['referências bibliográficas', 'referências'],
        ],
        'uso_ia' => [
            'titulo' => 'Uso de Inteligência Artificial',
            'pergunta' => 'Quais são as boas práticas, limites e exigências de declaração do uso de Inteligência Artificial no trabalho?',
            'termos_busca' => ['boas práticas, limites e uso responsável de inteligência artificial', 'uso de inteligência artificial'],
        ],
    ];

    public function __construct(private readonly EmbeddingOllamaService $embeddings)
    {
    }

    /**
     * Roda as 4 verificações fixas para o manuscrito. Nunca lança exceção —
     * cada verificação grava seu próprio status em documento_conformidade_rag.
     *
     * @param array<int, string> $textosPorPagina
     */
    public function analisar(DocumentoAnalise $analise, int $programaId, array $textosPorPagina): void
    {
        foreach (self::VERIFICACOES as $chave => $definicao) {
            $registro = DocumentoConformidadeRag::firstOrCreate(
                ['documento_analise_id' => $analise->id, 'verificacao' => $chave],
                ['status' => 'processando'],
            );

            try {
                $trechosNorma = $this->buscarTrechosNorma($programaId, $definicao['pergunta']);

                if ($trechosNorma === '') {
                    $registro->update([
                        'status' => 'falhou',
                        'erro' => 'Nenhum trecho relevante encontrado na base de conhecimento do programa.',
                    ]);
                    continue;
                }

                $trechoManuscrito = $this->extrairTrecho($textosPorPagina, $definicao['termos_busca']);
                $prompt = $this->construirPrompt($definicao['titulo'], $trechosNorma, $trechoManuscrito);
                $resposta = $this->chamarOllama($prompt);
                $json = $this->extrairJson($resposta);

                $registro->update([
                    'status' => 'concluida',
                    'veredito' => $json['veredito'] ?? null,
                    'justificativa' => $json['justificativa'] ?? null,
                    'trecho_norma_citado' => $json['trecho_norma_citado'] ?? null,
                ]);
            } catch (Throwable $e) {
                $registro->update([
                    'status' => 'falhou',
                    'erro' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }
    }

    private function buscarTrechosNorma(int $programaId, string $pergunta, int $topK = 5): string
    {
        $vetorPergunta = $this->embeddings->embedder($pergunta);

        $chunks = BaseConhecimentoChunk::where('programa_id', $programaId)->get();

        if ($chunks->isEmpty()) {
            return '';
        }

        return $chunks
            ->map(fn (BaseConhecimentoChunk $chunk) => [
                'chunk' => $chunk,
                'similaridade' => $this->embeddings->similaridadeCosseno($vetorPergunta, $chunk->embedding ?? []),
            ])
            ->sortByDesc('similaridade')
            ->take($topK)
            ->map(fn (array $item) => "[p. {$item['chunk']->pagina}] {$item['chunk']->conteudo}")
            ->implode("\n\n");
    }

    private function extrairTrecho(array $textosPorPagina, array $termos, int $maxChars = 3000): string
    {
        foreach ($textosPorPagina as $numeroPagina => $textoPagina) {
            $linhas = preg_split('/\R+/', (string) $textoPagina) ?: [];

            foreach ($linhas as $indice => $linha) {
                $linhaNorm = $this->normalizar(trim($linha));
                $bateu = false;

                foreach ($termos as $termo) {
                    if (str_contains($linhaNorm, $this->normalizar($termo))) {
                        $bateu = true;
                        break;
                    }
                }

                if (! $bateu) {
                    continue;
                }

                $conteudo = implode("\n", array_slice($linhas, $indice));

                if (mb_strlen($conteudo) < 300 && isset($textosPorPagina[$numeroPagina + 1])) {
                    $conteudo .= "\n" . $textosPorPagina[$numeroPagina + 1];
                }

                $conteudo = trim($conteudo);

                if (mb_strlen($conteudo) > 30) {
                    return mb_substr($conteudo, 0, $maxChars);
                }
            }
        }

        return '(Trecho não localizado no manuscrito.)';
    }

    private function construirPrompt(string $titulo, string $trechosNorma, string $trechoManuscrito): string
    {
        return <<<PROMPT
Você é um avaliador de conformidade acadêmica. Avalie se o trecho do MANUSCRITO abaixo atende à norma do programa sobre "{$titulo}", usando como referência exclusivamente os TRECHOS DA NORMA citados. Responda APENAS com o JSON no formato especificado, sem nenhum texto antes ou depois.

=== TRECHOS DA NORMA ===
{$trechosNorma}
=== FIM DOS TRECHOS DA NORMA ===

=== TRECHO DO MANUSCRITO ===
{$trechoManuscrito}
=== FIM DO TRECHO DO MANUSCRITO ===

Formato obrigatório da resposta:
{
  "veredito": "conforme|parcial|nao_conforme",
  "justificativa": "explicação breve e objetiva, em português",
  "trecho_norma_citado": "trecho literal da norma que embasa o veredito"
}

Critérios:
- "conforme": o manuscrito atende integralmente ao que a norma exige para este ponto.
- "parcial": o manuscrito atende parcialmente, ou o conteúdo é ambíguo.
- "nao_conforme": o manuscrito não atende, ou o trecho não foi localizado no manuscrito.
PROMPT;
    }

    private function chamarOllama(string $prompt): string
    {
        $response = Http::timeout(config('ollama.timeout'))
            ->post(config('ollama.url') . '/api/generate', [
                'model' => config('ollama.model'),
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ollama HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200)
            );
        }

        $texto = $response->json('response') ?? '';

        if (empty($texto)) {
            throw new RuntimeException('Ollama retornou resposta vazia.');
        }

        return $texto;
    }

    private function extrairJson(string $texto): array
    {
        $decoded = json_decode($texto, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/u', $texto, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Resposta do Ollama não é JSON válido: ' . mb_substr($texto, 0, 200));
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        $acentos = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $simples = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];

        return trim(preg_replace('/\s+/', ' ', str_replace($acentos, $simples, $texto)));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ConformidadeNormasServiceTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/ConformidadeNormasService.php tests/Feature/ConformidadeNormasServiceTest.php
git commit -m "feat: serviço de conformidade RAG com as 4 verificações fixas"
```

---

## Task 11: `AnalisarConformidadeRagJob`

**Files:**
- Create: `app/Jobs/AnalisarConformidadeRagJob.php`
- Test: `tests/Feature/AnalisarConformidadeRagJobTest.php`

**Interfaces:**
- Consumes: `ConformidadeNormasService::analisar()` (Task 10).
- Produces: job `AnalisarConformidadeRagJob::dispatch(DocumentoAnalise $analise)`.

Mesma técnica de teste da Task 8: usa `docs/dissertacao.pdf` real como o "manuscrito" para exercitar o parsing de verdade.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\BaseConhecimentoChunk;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\ConformidadeNormasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalisarConformidadeRagJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_extrai_texto_do_pdf_real_e_grava_as_quatro_verificacoes(): void
    {
        Storage::fake('local');
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
            '*/api/generate' => Http::response(['response' => json_encode([
                'veredito' => 'parcial',
                'justificativa' => 'Avaliação de teste.',
                'trecho_norma_citado' => 'trecho',
            ])]),
        ]);

        $programa = Programa::create(['nome' => 'PPG']);
        $documentoRef = ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documentoRef->id, 'programa_id' => $programa->id,
            'tipo' => 'normas', 'pagina' => 1, 'ordem' => 0,
            'conteudo' => 'Trecho de norma sobre aderência, ODS, citação e uso de IA.', 'embedding' => [1, 0, 0],
        ]);

        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $caminhoPdf = 'trabalhos/' . $trabalho->id . '/manuscrito.pdf';
        Storage::disk('local')->put($caminhoPdf, file_get_contents(base_path('docs/dissertacao.pdf')));
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => $caminhoPdf, 'arquivo_pdf_nome_original' => 'manuscrito.pdf', 'status_analise' => 'concluida',
        ]);
        $analise = DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);

        (new AnalisarConformidadeRagJob($analise))->handle(app(ConformidadeNormasService::class));

        $this->assertCount(4, DocumentoConformidadeRag::where('documento_analise_id', $analise->id)->get());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AnalisarConformidadeRagJobTest`
Expected: FAIL — `Class "App\Jobs\AnalisarConformidadeRagJob" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Jobs;

use App\Models\DocumentoAnalise;
use App\Services\ConformidadeNormasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class AnalisarConformidadeRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(private readonly DocumentoAnalise $analise)
    {
    }

    public function handle(ConformidadeNormasService $service): void
    {
        $documento = $this->analise->trabalhoDocumento;
        $documento->load('trabalhoAcademico');

        $programaId = $documento->trabalhoAcademico?->programa_id;

        if (! $programaId) {
            return;
        }

        $caminhoPdf = Storage::disk('local')->path($documento->arquivo_pdf_path);
        $paginas = (new Parser())->parseFile($caminhoPdf)->getPages();

        $textosPorPagina = [];
        foreach ($paginas as $i => $pagina) {
            $textosPorPagina[$i + 1] = trim($pagina->getText());
        }

        $service->analisar($this->analise, $programaId, $textosPorPagina);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AnalisarConformidadeRagJobTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/AnalisarConformidadeRagJob.php tests/Feature/AnalisarConformidadeRagJobTest.php
git commit -m "feat: job que extrai o manuscrito e roda a análise de conformidade RAG"
```

---

## Task 12: Disparo automático ao enviar o manuscrito

**Files:**
- Modify: `app/Http/Controllers/TrabalhoDocumentoController.php:147` (logo após `$analise = $this->analiseService->analisar($documento);`)
- Test: `tests/Feature/EnvioManuscritoDisparaConformidadeRagTest.php`

**Interfaces:**
- Consumes: `AnalisarConformidadeRagJob::dispatch()` (Task 11), `ProgramaDocumentoReferencia` (Task 1).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnvioManuscritoDisparaConformidadeRagTest extends TestCase
{
    use RefreshDatabase;

    private function discenteComBancaLiberada(): array
    {
        $programa = Programa::create(['nome' => 'PPG']);
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'orcid_url' => 'https://orcid.org/0000-0002-1825-0097', 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'cadastrada',
            'libera_envio_trabalho' => true,
        ]);

        return [$discente, $programa];
    }

    public function test_despacha_job_quando_programa_tem_normas_indexadas(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente, $programa] = $this->discenteComBancaLiberada();
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'concluido',
        ]);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->create('trabalho.pdf', 100, 'application/pdf'),
        ]);

        Queue::assertPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_nao_despacha_job_quando_programa_nao_tem_normas_indexadas(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente] = $this->discenteComBancaLiberada();

        $response = $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->create('trabalho.pdf', 100, 'application/pdf'),
        ]);

        // O envio do manuscrito continua funcionando normalmente mesmo sem RAG disponível.
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('trabalho_documentos', 1);
        Queue::assertNotPushed(AnalisarConformidadeRagJob::class);
    }

    public function test_nao_despacha_job_quando_normas_ainda_esta_processando(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$discente, $programa] = $this->discenteComBancaLiberada();
        ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id, 'tipo' => 'normas',
            'arquivo_path' => 'x', 'arquivo_nome_original' => 'normas.pdf', 'status_indexacao' => 'processando',
        ]);

        $response = $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->create('trabalho.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('trabalho_documentos', 1);
        Queue::assertNotPushed(AnalisarConformidadeRagJob::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EnvioManuscritoDisparaConformidadeRagTest`
Expected: FAIL — `Queue::assertPushed` falha porque o job nunca é despachado.

- [ ] **Step 3: Write the implementation**

Em `app/Http/Controllers/TrabalhoDocumentoController.php`, adicionar o import no topo:

```php
use App\Jobs\AnalisarConformidadeRagJob;
use App\Models\ProgramaDocumentoReferencia;
```

E, logo após a linha `$analise = $this->analiseService->analisar($documento);` (dentro de `store()`, antes do `return redirect()`), adicionar:

```php
        $normasIndexadas = ProgramaDocumentoReferencia::where('programa_id', $trabalho->programa_id)
            ->where('tipo', 'normas')
            ->where('status_indexacao', 'concluido')
            ->exists();

        if ($normasIndexadas) {
            AnalisarConformidadeRagJob::dispatch($analise);
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EnvioManuscritoDisparaConformidadeRagTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: todos os testes passam (o suite completo do projeto, não só os novos).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TrabalhoDocumentoController.php tests/Feature/EnvioManuscritoDisparaConformidadeRagTest.php
git commit -m "feat: dispara análise de conformidade RAG ao enviar o manuscrito, quando o programa tem normas indexadas"
```

---

## Task 13: Exibir o resultado na tela de análise do discente

**Files:**
- Modify: `app/Http/Controllers/TrabalhoDocumentoController.php:163` (adicionar `'conformidadeRag'` ao `$analise->load([...])` em `analise()`)
- Modify: `resources/views/meu-trabalho/analise.blade.php` (nova seção, após o card "Avaliação do Resumo")
- Test: `tests/Feature/AnaliseConformidadeRagViewTest.php`

**Interfaces:**
- Consumes: `DocumentoAnalise::conformidadeRag` (Task 3).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnaliseConformidadeRagViewTest extends TestCase
{
    use RefreshDatabase;

    private function analiseDoDiscente(User $discente, Programa $programa): DocumentoAnalise
    {
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao', 'versao' => 1,
            'arquivo_pdf_path' => 'trabalhos/1/a.pdf', 'arquivo_pdf_nome_original' => 'a.pdf', 'status_analise' => 'concluida',
        ]);

        return DocumentoAnalise::create(['trabalho_documento_id' => $documento->id]);
    }

    public function test_exibe_veredito_e_justificativa_das_verificacoes(): void
    {
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => 'PPG']);
        $analise = $this->analiseDoDiscente($discente, $programa);

        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id, 'verificacao' => 'aderencia', 'status' => 'concluida',
            'veredito' => 'conforme', 'justificativa' => 'A seção está presente e completa.',
            'trecho_norma_citado' => 'A seção Aderência deve conter...',
        ]);
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id, 'verificacao' => 'ods', 'status' => 'concluida',
            'veredito' => 'nao_conforme', 'justificativa' => 'ODS não declarado.',
        ]);
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id, 'verificacao' => 'citacao_apa', 'status' => 'concluida',
            'veredito' => 'conforme', 'justificativa' => 'Referências em APA.',
        ]);
        DocumentoConformidadeRag::create([
            'documento_analise_id' => $analise->id, 'verificacao' => 'uso_ia', 'status' => 'falhou',
            'erro' => 'Timeout ao consultar o Ollama.',
        ]);

        $this->actingAs($discente)->get(route('meu-trabalho.analise', $analise))
            ->assertOk()
            ->assertSee('Conformidade com as Normas')
            ->assertSee('A seção está presente e completa.')
            ->assertSee('ODS não declarado.')
            ->assertSee('Conforme')
            ->assertSee('Não conforme');
    }

    public function test_nao_exibe_secao_quando_nao_ha_analise_de_conformidade(): void
    {
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => 'PPG']);
        $analise = $this->analiseDoDiscente($discente, $programa);

        $this->actingAs($discente)->get(route('meu-trabalho.analise', $analise))
            ->assertOk()
            ->assertDontSee('Conformidade com as Normas');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AnaliseConformidadeRagViewTest`
Expected: FAIL — a seção "Conformidade com as Normas" ainda não existe na view.

- [ ] **Step 3: Update the controller's eager load**

Em `app/Http/Controllers/TrabalhoDocumentoController.php`, no método `analise()`, trocar:

```php
        $analise->load(['itens', 'ollama', 'trabalhoDocumento']);
```

por:

```php
        $analise->load(['itens', 'ollama', 'trabalhoDocumento', 'conformidadeRag']);
```

- [ ] **Step 4: Add the section to the view**

Em `resources/views/meu-trabalho/analise.blade.php`, adicionar logo após o `</div>` de fechamento do card "Avaliação do Resumo" (antes do comentário `{{-- ── Itens detalhados (colapsável) ────────────────────────────── --}}`):

```blade
            {{-- ── Conformidade com as Normas (IA) ─────────────────────────────── --}}
            @php $conformidades = $analise->conformidadeRag ?? collect(); @endphp
            @if ($conformidades->isNotEmpty())
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h2 class="h5 fw-bold text-dark mb-0">
                                Conformidade com as Normas
                                <span class="badge text-bg-secondary fw-normal ms-2" style="font-size: 11px;">IA</span>
                            </h2>
                            <p class="text-muted small mb-0">
                                Avaliação automática à luz do manual de normas do programa. Não bloqueia o envio à banca.
                            </p>
                        </div>
                        <div class="card-body">
                            @php
                                $titulos = [
                                    'aderencia' => 'Seção "Aderência"',
                                    'ods' => 'ODS do Trabalho',
                                    'citacao_apa' => 'Padrão de citação (APA)',
                                    'uso_ia' => 'Uso de Inteligência Artificial',
                                ];
                                $ordemChaves = array_keys($titulos);
                                $ordenadas = $conformidades->sortBy(fn ($c) => array_search($c->verificacao, $ordemChaves) ?: 99);
                                $processando = $conformidades->contains(fn ($c) => $c->status === 'processando');
                            @endphp

                            @if ($processando)
                                <div class="alert alert-info small mb-3">
                                    <i class="bi bi-hourglass-split me-2"></i>
                                    Análise em processamento. Recarregue a página em instantes.
                                </div>
                            @endif

                            <div class="row g-3">
                                @foreach ($ordenadas as $c)
                                    @php
                                        $cor = match($c->veredito) {
                                            'conforme' => 'success',
                                            'parcial' => 'warning',
                                            'nao_conforme' => 'danger',
                                            default => 'secondary',
                                        };
                                        $label = match($c->veredito) {
                                            'conforme' => 'Conforme',
                                            'parcial' => 'Parcial',
                                            'nao_conforme' => 'Não conforme',
                                            default => $c->status === 'falhou' ? 'Não avaliado' : 'Processando…',
                                        };
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 h-100">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fw-semibold small">{{ $titulos[$c->verificacao] ?? $c->verificacao }}</span>
                                                <span class="badge text-bg-{{ $cor }}">{{ $label }}</span>
                                            </div>
                                            @if ($c->justificativa)
                                                <p class="small text-dark mb-1">{{ $c->justificativa }}</p>
                                            @endif
                                            @if ($c->trecho_norma_citado)
                                                <p class="small text-muted fst-italic mb-0">"{{ $c->trecho_norma_citado }}"</p>
                                            @endif
                                            @if ($c->status === 'falhou' && $c->erro)
                                                <p class="small text-danger mb-0">{{ $c->erro }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AnaliseConformidadeRagViewTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: todos os testes do projeto passam, incluindo os 13 grupos de testes criados neste plano.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TrabalhoDocumentoController.php resources/views/meu-trabalho/analise.blade.php tests/Feature/AnaliseConformidadeRagViewTest.php
git commit -m "feat: exibe o resultado da conformidade RAG na tela de análise do discente"
```

---

## Depois de implementado (não é código, é operação)

- No ambiente onde o worker da fila roda, executar `ollama pull nomic-embed-text` (modelo de embedding, ~274MB). Sem isso, `IndexarBaseConhecimentoJob` e `AnalisarConformidadeRagJob` falham com erro de HTTP do Ollama — comportamento esperado e já tratado (status `falhou` com mensagem), mas a funcionalidade fica inoperante até o modelo ser baixado.
- A Secretaria do PPGMSB precisa enviar `docs/NormasPPGMSB.pdf` (renomeado/copiado, se quiser) pela nova tela `/programa/documentos-referencia` para que a análise comece a rodar de fato — nada acontece automaticamente com o arquivo já presente no repositório, por design (cada programa sobe o seu).
