# Programa completo, cursos e importação de discentes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cada Secretaria administra um único programa (ligado a uma instituição com logotipo), organizado em cursos nível × modalidade com prazos e regras de banca próprias; discentes podem ser importados por CSV; nenhum ato de banca avança sem ORCID do discente.

**Architecture:** Um serviço `ContextoSecretaria` resolve o programa da Secretaria logada e centraliza o isolamento. Cursos (`programa_cursos`) e suas regras (`curso_regras_banca`) viram a fonte das regras de composição, avaliadas por `VerificacaoBancaService`, usado no cadastro de banca e na homologação. A importação CSV é um serviço puro de análise (`ImportacaoDiscentesService`) + um serviço de criação (`CadastroDiscenteService`), com prévia guardada em cache.

**Tech Stack:** PHP 8.2 · Laravel 12 · MySQL 8 (testes em SQLite `:memory:`) · Blade + Bootstrap 5.3 · PHPUnit (`php artisan test`).

**Spec:** `docs/superpowers/specs/2026-09-30-programa-cursos-importacao-design.md`

## Global Constraints

- Nomes em português (classes, rotas, variáveis); rotas nomeadas `recurso.acao`.
- Toda rota nova dentro de `middleware(['auth', 'verified'])`.
- Autorização por `users.categoria` no controller (sem gates/policies); Secretaria = `sec`; demais recebem 403.
- Arquivos (logotipos) em `storage/app/private/logos/` (disco `local`), servidos por rota autenticada; nunca `storage/app/public`.
- Logotipo: PNG, JPG ou WebP, até 2 MB; SVG recusado.
- Cada usuário Secretaria tem no máximo um `programa_usuarios` ativo com `categoria_no_programa = secretaria`.
- `programas.instituicao_id` obrigatório no formulário.
- Cursos: `nivel` ∈ {`mestrado`,`doutorado`}, `modalidade` ∈ {`academico`,`profissional`}; identificador CSV `{nivel}_{modalidade}`.
- Padrões: mestrado prazos 18/24 meses, regra 2/5/1/1 (mín. aval./máx. aval./mín. internos/mín. externos à instituição); doutorado 30/48, regra 5/5/2/2.
- `max_avaliadores` ≤ 5; `min_internos + min_externos_instituicao` ≤ `max_avaliadores`.
- CSV: colunas `nome, email, matricula, curso, data_ingresso, lattes` obrigatórias; `linha_pesquisa, telefone` opcionais; sem ORCID; `;` ou `,`; BOM e Latin-1 aceitos; 2 MB; 1.000 linhas; prévia em cache 30 min.
- Lattes: `^https?://lattes\.cnpq\.br/[0-9]+$`; ORCID: `^https?://orcid\.org/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$`.
- Não usar `enum` em colunas novas (SQLite de teste); usar `string` e validar na aplicação.
- Preservar alterações locais não commitadas; cada commit inclui só os arquivos da tarefa.
- Baseline atual: `php artisan test --compact` → 85 testes passando.

## Review Focus

1. CSV exportado pelo Excel pt-BR: separador `;`, Windows-1252, BOM, e-mail com maiúsculas/espaços, datas `1/3/2024` e linhas finais vazias → deve ser lido sem erro falso (testes na Task 10).
2. Secretaria clica duas vezes em "Confirmar importação" ou volta e reenvia → nenhum discente duplicado (cache consumido com `Cache::pull`; teste na Task 10).
3. Programa legado sem instituição e/ou sem cursos (dados antigos) → telas de banca e homologação não quebram; usam regras padrão por nível (testes na Task 6).
4. Arquivo disfarçado de imagem (SVG/HTML renomeado para `.png`) → recusado; logotipo servido com `Content-Type` de imagem e `X-Content-Type-Options: nosniff` (teste na Task 3).
5. Curso inativado com discentes vinculados → vínculos existentes continuam válidos e com regras; o curso inativo não é aceito em novo cadastro/CSV (testes nas Tasks 5 e 10).

---

## File Structure

| Arquivo | Responsabilidade |
| --- | --- |
| `app/Services/ContextoSecretaria.php` (novo) | Resolver o programa da Secretaria e garantir isolamento (403). |
| `app/Services/CursoProgramaService.php` (novo) | Criar curso com regras padrão; sincronizar `oferta_*`. |
| `app/Services/VerificacaoBancaService.php` (novo) | Regras de ORCID + composição por curso/etapa. |
| `app/Services/CadastroDiscenteService.php` (novo) | Criar usuário + perfil + vínculo + trabalho de um discente. |
| `app/Services/ImportacaoDiscentesService.php` (novo) | Ler/validar CSV e classificar linhas. |
| `app/Models/ProgramaCurso.php`, `app/Models/CursoRegraBanca.php` (novos) | Cursos e regras. |
| `app/Http/Controllers/ProgramaCursoController.php` (novo) | CRUD de cursos e regras. |
| `app/Http/Controllers/ImportacaoDiscenteController.php` (novo) | Envio, prévia e confirmação do CSV. |
| `app/Http/Controllers/LogoController.php` (novo) | Servir logotipos de instituição e programa. |
| Migrations `2026_09_30_1000xx_*` (novas) | Esquema e backfill. |
| Controllers existentes | `Instituicao`, `Programa`, `Banca`, `HomologacaoBanca`, `Usuario`, `Profile`, `TrabalhoDocumento`, `Dashboard`, `AreaConcentracao`, `LinhaPesquisa`, `Orientacao`, `Barema`, `Avaliacao` (ata). |
| Testes novos em `tests/Feature/` | Um arquivo por tarefa (nomes em cada tarefa). |

---

### Task 1: Esquema — programa completo, cursos, regras e backfill

**Files:**
- Create: `database/migrations/2026_09_30_100000_add_dados_completos_to_programas_table.php`
- Create: `database/migrations/2026_09_30_100100_create_programa_cursos_table.php`
- Create: `database/migrations/2026_09_30_100200_create_curso_regras_banca_table.php`
- Create: `database/migrations/2026_09_30_100300_add_programa_curso_to_discente_programa_vinculos_table.php`
- Create: `database/migrations/2026_09_30_100400_vincular_programa_instituicao_secretarias.php`
- Create: `app/Models/ProgramaCurso.php`, `app/Models/CursoRegraBanca.php`
- Modify: `app/Models/Programa.php`, `app/Models/Instituicao.php`, `app/Models/DiscenteProgramaVinculo.php`
- Test: `tests/Feature/EsquemaProgramaCursoTest.php`

**Interfaces:**
- Produces: `ProgramaCurso` (`programa_id, nivel, modalidade, prazo_qualificacao_meses, prazo_defesa_meses, ativo`; `nome` e `identificador` accessors; `regrasBanca()`, `regraPara(string $etapa): ?CursoRegraBanca`; `ProgramaCurso::PADROES`), `CursoRegraBanca` (`programa_curso_id, etapa, min_avaliadores, max_avaliadores, min_internos, min_externos_instituicao`), `Programa::cursos()`, `Programa::instituicao()`, `Programa::coordenador()`, `Programa::viceCoordenador()`, `Instituicao::programas()`, `DiscenteProgramaVinculo::programaCurso()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EsquemaProgramaCursoTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_expoe_nome_identificador_e_regra_por_etapa(): void
    {
        $programa = Programa::create(['nome' => 'PPG Teste', 'sigla' => 'PPGT']);
        $curso = ProgramaCurso::create([
            'programa_id' => $programa->id,
            'nivel' => 'doutorado',
            'modalidade' => 'profissional',
            'prazo_qualificacao_meses' => 30,
            'prazo_defesa_meses' => 48,
            'ativo' => true,
        ]);
        $curso->regrasBanca()->create([
            'etapa' => 'defesa',
            'min_avaliadores' => 5,
            'max_avaliadores' => 5,
            'min_internos' => 2,
            'min_externos_instituicao' => 2,
        ]);

        $this->assertSame('Doutorado Profissional', $curso->nome);
        $this->assertSame('doutorado_profissional', $curso->identificador);
        $this->assertSame(2, $curso->regraPara('defesa')->min_internos);
        $this->assertNull($curso->regraPara('qualificacao'));
        $this->assertTrue($programa->cursos()->whereKey($curso->id)->exists());
    }

    public function test_vinculo_de_discente_referencia_curso(): void
    {
        $programa = Programa::create(['nome' => 'PPG Teste']);
        $curso = ProgramaCurso::create([
            'programa_id' => $programa->id, 'nivel' => 'mestrado', 'modalidade' => 'academico',
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => true,
        ]);
        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'programa_curso_id' => $curso->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);

        $this->assertTrue($vinculo->programaCurso->is($curso));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/EsquemaProgramaCursoTest.php`
Expected: FAIL — `Class "App\Models\ProgramaCurso" not found`.

- [ ] **Step 3: Write migrations**

`2026_09_30_100000_add_dados_completos_to_programas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->foreignId('instituicao_id')->nullable()->after('id')->constrained('instituicoes')->nullOnDelete();
            $table->unsignedTinyInteger('conceito_capes')->nullable()->after('area_avaliacao');
            $table->unsignedSmallInteger('ano_inicio')->nullable()->after('conceito_capes');
            $table->foreignId('coordenador_id')->nullable()->after('ano_inicio')->constrained('users')->nullOnDelete();
            $table->foreignId('vice_coordenador_id')->nullable()->after('coordenador_id')->constrained('users')->nullOnDelete();
            $table->string('logo_path')->nullable()->after('descricao');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instituicao_id');
            $table->dropConstrainedForeignId('coordenador_id');
            $table->dropConstrainedForeignId('vice_coordenador_id');
            $table->dropColumn(['conceito_capes', 'ano_inicio', 'logo_path']);
        });
    }
};
```

`2026_09_30_100100_create_programa_cursos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nivel', 20);
            $table->string('modalidade', 20);
            $table->unsignedSmallInteger('prazo_qualificacao_meses');
            $table->unsignedSmallInteger('prazo_defesa_meses');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['programa_id', 'nivel', 'modalidade'], 'programa_cursos_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_cursos');
    }
};
```

`2026_09_30_100200_create_curso_regras_banca_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_regras_banca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_curso_id')->constrained('programa_cursos')->cascadeOnDelete();
            $table->string('etapa', 20);
            $table->unsignedTinyInteger('min_avaliadores');
            $table->unsignedTinyInteger('max_avaliadores');
            $table->unsignedTinyInteger('min_internos')->default(0);
            $table->unsignedTinyInteger('min_externos_instituicao')->default(0);
            $table->timestamps();
            $table->unique(['programa_curso_id', 'etapa'], 'curso_regras_banca_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_regras_banca');
    }
};
```

`2026_09_30_100300_add_programa_curso_to_discente_programa_vinculos_table.php` (esquema + backfill; valores padrão inline para não depender de models):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PADROES = [
        'mestrado' => ['prazos' => [18, 24], 'regra' => [2, 5, 1, 1]],
        'doutorado' => ['prazos' => [30, 48], 'regra' => [5, 5, 2, 2]],
    ];

    public function up(): void
    {
        Schema::table('discente_programa_vinculos', function (Blueprint $table) {
            $table->foreignId('programa_curso_id')->nullable()->after('programa_id')
                ->constrained('programa_cursos')->nullOnDelete();
        });

        foreach (DB::table('programas')->get() as $programa) {
            $niveis = DB::table('discente_programa_vinculos')
                ->where('programa_id', $programa->id)
                ->distinct()
                ->pluck('nivel')
                ->all();
            if ($programa->oferta_mestrado) {
                $niveis[] = 'mestrado';
            }
            if ($programa->oferta_doutorado) {
                $niveis[] = 'doutorado';
            }

            foreach (array_unique($niveis) as $nivel) {
                if (! isset(self::PADROES[$nivel])) {
                    continue;
                }
                [$pq, $pd] = self::PADROES[$nivel]['prazos'];
                $cursoId = DB::table('programa_cursos')->insertGetId([
                    'programa_id' => $programa->id,
                    'nivel' => $nivel,
                    'modalidade' => 'academico',
                    'prazo_qualificacao_meses' => $pq,
                    'prazo_defesa_meses' => $pd,
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                [$min, $max, $int, $ext] = self::PADROES[$nivel]['regra'];
                foreach (['qualificacao', 'defesa'] as $etapa) {
                    DB::table('curso_regras_banca')->insert([
                        'programa_curso_id' => $cursoId,
                        'etapa' => $etapa,
                        'min_avaliadores' => $min,
                        'max_avaliadores' => $max,
                        'min_internos' => $int,
                        'min_externos_instituicao' => $ext,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('discente_programa_vinculos')
                    ->where('programa_id', $programa->id)
                    ->where('nivel', $nivel)
                    ->update(['programa_curso_id' => $cursoId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('discente_programa_vinculos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programa_curso_id');
        });
    }
};
```

`2026_09_30_100400_vincular_programa_instituicao_secretarias.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('programas')->count() !== 1) {
            return;
        }
        $programaId = DB::table('programas')->value('id');

        if (DB::table('instituicoes')->count() === 1) {
            DB::table('programas')->where('id', $programaId)->whereNull('instituicao_id')
                ->update(['instituicao_id' => DB::table('instituicoes')->value('id')]);
        }

        $secretariasComPrograma = DB::table('programa_usuarios')
            ->where('categoria_no_programa', 'secretaria')
            ->where('ativo', true)
            ->pluck('user_id');

        DB::table('users')->where('categoria', 'sec')
            ->whereNotIn('id', $secretariasComPrograma)
            ->pluck('id')
            ->each(fn ($userId) => DB::table('programa_usuarios')->insert([
                'programa_id' => $programaId,
                'user_id' => $userId,
                'categoria_no_programa' => 'secretaria',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        // Migração de dados: não desfaz vínculos.
    }
};
```

- [ ] **Step 4: Write models and relations**

`app/Models/ProgramaCurso.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaCurso extends Model
{
    public const NIVEIS = ['mestrado', 'doutorado'];
    public const MODALIDADES = ['academico', 'profissional'];
    public const ETAPAS = ['qualificacao', 'defesa'];

    /** Prazos (meses) e regra de banca [min, max, internos, externos à instituição] por nível. */
    public const PADROES = [
        'mestrado' => ['prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'regra' => [2, 5, 1, 1]],
        'doutorado' => ['prazo_qualificacao_meses' => 30, 'prazo_defesa_meses' => 48, 'regra' => [5, 5, 2, 2]],
    ];

    protected $table = 'programa_cursos';

    protected $fillable = [
        'programa_id',
        'nivel',
        'modalidade',
        'prazo_qualificacao_meses',
        'prazo_defesa_meses',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'prazo_qualificacao_meses' => 'integer',
            'prazo_defesa_meses' => 'integer',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function regrasBanca(): HasMany
    {
        return $this->hasMany(CursoRegraBanca::class);
    }

    public function regraPara(string $etapa): ?CursoRegraBanca
    {
        return $this->regrasBanca->firstWhere('etapa', $etapa);
    }

    protected function nome(): Attribute
    {
        return Attribute::get(fn () => ucfirst($this->nivel) . ' ' . ($this->modalidade === 'academico' ? 'Acadêmico' : 'Profissional'));
    }

    protected function identificador(): Attribute
    {
        return Attribute::get(fn () => $this->nivel . '_' . $this->modalidade);
    }
}
```

`app/Models/CursoRegraBanca.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoRegraBanca extends Model
{
    protected $table = 'curso_regras_banca';

    protected $fillable = [
        'programa_curso_id',
        'etapa',
        'min_avaliadores',
        'max_avaliadores',
        'min_internos',
        'min_externos_instituicao',
    ];

    protected function casts(): array
    {
        return [
            'min_avaliadores' => 'integer',
            'max_avaliadores' => 'integer',
            'min_internos' => 'integer',
            'min_externos_instituicao' => 'integer',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(ProgramaCurso::class, 'programa_curso_id');
    }
}
```

In `app/Models/Programa.php`: add to `$fillable` `'instituicao_id', 'conceito_capes', 'ano_inicio', 'coordenador_id', 'vice_coordenador_id', 'logo_path'`; add `use Illuminate\Database\Eloquent\Relations\BelongsTo;` and:

```php
    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class);
    }

    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordenador_id');
    }

    public function viceCoordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vice_coordenador_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(ProgramaCurso::class)->orderBy('nivel')->orderBy('modalidade');
    }
```

In `app/Models/Instituicao.php` add `use Illuminate\Database\Eloquent\Relations\HasMany;` and:

```php
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }
```

In `app/Models/DiscenteProgramaVinculo.php`: add `'programa_curso_id'` to `$fillable` and:

```php
    public function programaCurso(): BelongsTo
    {
        return $this->belongsTo(ProgramaCurso::class);
    }
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact tests/Feature/EsquemaProgramaCursoTest.php` → PASS.
Run: `php artisan test --compact` → all pass (85 + 2).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_30_1000* app/Models/ProgramaCurso.php app/Models/CursoRegraBanca.php app/Models/Programa.php app/Models/Instituicao.php app/Models/DiscenteProgramaVinculo.php tests/Feature/EsquemaProgramaCursoTest.php
git commit -m "feat: estruturar cursos do programa e regras de banca"
```

---

### Task 2: ContextoSecretaria — programa da Secretaria logada

**Files:**
- Create: `app/Services/ContextoSecretaria.php`
- Test: `tests/Feature/ContextoSecretariaTest.php`

**Interfaces:**
- Produces: `ContextoSecretaria::programa(User $user): ?Programa`; `ContextoSecretaria::exigirPrograma(User $user): Programa` (403 sem programa); `ContextoSecretaria::garantirPrograma(User $user, ?int $programaId): void` (403 quando diferente); `ContextoSecretaria::vincular(User $user, Programa $programa): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\ProgramaUsuario;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContextoSecretariaTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_programa_da_secretaria(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'A']);
        Programa::create(['nome' => 'B']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->assertTrue(app(ContextoSecretaria::class)->programa($sec)->is($programa));
    }

    public function test_garantir_programa_recusa_outro_programa(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'A']);
        $outro = Programa::create(['nome' => 'B']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->expectException(HttpException::class);
        app(ContextoSecretaria::class)->garantirPrograma($sec, $outro->id);
    }

    public function test_vincular_nao_cria_segundo_programa(): void
    {
        $sec = User::factory()->secretaria()->create();
        $a = Programa::create(['nome' => 'A']);
        $b = Programa::create(['nome' => 'B']);
        $contexto = app(ContextoSecretaria::class);
        $contexto->vincular($sec, $a);

        $this->expectException(HttpException::class);
        $contexto->vincular($sec, $b);
    }

    public function test_exigir_programa_sem_vinculo_retorna_403(): void
    {
        $sec = User::factory()->secretaria()->create();

        $this->expectException(HttpException::class);
        app(ContextoSecretaria::class)->exigirPrograma($sec);
    }

    public function test_docente_nao_tem_programa_de_secretaria(): void
    {
        $docente = User::factory()->docente()->create();
        $programa = Programa::create(['nome' => 'A']);
        ProgramaUsuario::create([
            'programa_id' => $programa->id, 'user_id' => $docente->id,
            'categoria_no_programa' => 'secretaria', 'ativo' => true,
        ]);

        $this->assertNull(app(ContextoSecretaria::class)->programa($docente));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/ContextoSecretariaTest.php`
Expected: FAIL — class `App\Services\ContextoSecretaria` not found.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Services;

use App\Models\Programa;
use App\Models\ProgramaUsuario;
use App\Models\User;

class ContextoSecretaria
{
    public function programa(User $user): ?Programa
    {
        if ($user->categoria !== 'sec') {
            return null;
        }

        return ProgramaUsuario::query()
            ->where('user_id', $user->id)
            ->where('categoria_no_programa', 'secretaria')
            ->where('ativo', true)
            ->with('programa')
            ->first()
            ?->programa;
    }

    public function exigirPrograma(User $user): Programa
    {
        $programa = $this->programa($user);
        abort_unless($programa, 403, 'Cadastre o programa da Secretaria antes de continuar.');

        return $programa;
    }

    public function garantirPrograma(User $user, ?int $programaId): void
    {
        abort_unless($programaId !== null && $this->programa($user)?->id === $programaId, 403);
    }

    public function vincular(User $user, Programa $programa): void
    {
        $atual = $this->programa($user);
        abort_if($atual && ! $atual->is($programa), 403, 'Esta Secretaria já administra outro programa.');

        ProgramaUsuario::firstOrCreate(
            ['programa_id' => $programa->id, 'user_id' => $user->id, 'categoria_no_programa' => 'secretaria'],
            ['ativo' => true],
        );
    }
}
```

- [ ] **Step 4: Run tests** — `php artisan test --compact tests/Feature/ContextoSecretariaTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ContextoSecretaria.php tests/Feature/ContextoSecretariaTest.php
git commit -m "feat: resolver programa da secretaria logada"
```

---

### Task 3: Instituição por programa, com logotipo obrigatório

**Files:**
- Modify: `app/Http/Controllers/InstituicaoController.php` (reescrita)
- Create: `app/Http/Controllers/LogoController.php`
- Modify: `app/Models/Instituicao.php`, `app/Models/Programa.php` (métodos `logoUrl()`)
- Modify: `routes/web.php` (bloco `/instituicao`)
- Create: `resources/views/instituicoes/create.blade.php`
- Modify: `resources/views/instituicoes/edit.blade.php` (form `enctype`, campo `logo`, preview, `logoUrl()`)
- Test: `tests/Feature/InstituicaoLogoTest.php`

**Interfaces:**
- Consumes: `ContextoSecretaria::programa()`, `Programa::instituicao()`.
- Produces: rotas `instituicoes.create` (GET `/instituicoes/create`), `instituicoes.store` (POST `/instituicoes`), `instituicao.edit` (GET `/instituicao`), `instituicao.update` (PUT `/instituicao`), `instituicoes.logo` (GET `/instituicoes/{instituicao}/logo`), `programas.logo` (GET `/programas/{programa}/logo`); `Instituicao::logoUrl(): string`; `Programa::logoUrl(): ?string`; helper `LogoController::armazenar(UploadedFile $arquivo, ?string $anterior): string`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstituicaoLogoTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge(['nome' => 'Universidade do Estado da Bahia', 'sigla' => 'UNEB'], $extra);
    }

    public function test_cadastro_de_instituicao_exige_logotipo(): void
    {
        $sec = User::factory()->secretaria()->create();

        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados())
            ->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('instituicoes', 0);
    }

    public function test_cadastro_de_instituicao_com_logotipo_armazena_em_privado(): void
    {
        Storage::fake('local');
        $sec = User::factory()->secretaria()->create();

        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]))->assertRedirect(route('programas.create'));

        $instituicao = Instituicao::firstOrFail();
        $this->assertStringStartsWith('logos/', $instituicao->logo_path);
        Storage::disk('local')->assertExists($instituicao->logo_path);
    }

    public function test_svg_e_arquivo_disfarcado_sao_recusados(): void
    {
        $sec = User::factory()->secretaria()->create();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $disfarcado = UploadedFile::fake()->createWithContent('logo.png', '<html><script>alert(1)</script></html>');

        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados(['logo' => $svg]))
            ->assertSessionHasErrors('logo');
        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados(['logo' => $disfarcado]))
            ->assertSessionHasErrors('logo');
    }

    public function test_logo_e_servido_com_nosniff(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('logos/x.png', UploadedFile::fake()->image('x.png')->getContent());
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/x.png', 'ativo' => true]);

        $this->actingAs(User::factory()->docente()->create())
            ->get(route('instituicoes.logo', $instituicao))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_secretaria_edita_somente_instituicao_do_seu_programa(): void
    {
        Storage::fake('local');
        $sec = User::factory()->secretaria()->create();
        $minha = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
        $outra = Instituicao::create(['nome' => 'UFBA', 'sigla' => 'UFBA', 'logo_path' => 'logos/b.png', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG', 'instituicao_id' => $minha->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->put(route('instituicao.update'), ['nome' => 'UNEB Editada', 'sigla' => 'UNEB'])
            ->assertRedirect();

        $this->assertSame('UNEB Editada', $minha->fresh()->nome);
        $this->assertSame('UFBA', $outra->fresh()->nome);
    }

    public function test_edicao_sem_novo_logo_mantem_o_atual(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
        app(ContextoSecretaria::class)->vincular($sec, Programa::create(['nome' => 'PPG', 'instituicao_id' => $inst->id]));

        $this->actingAs($sec)->put(route('instituicao.update'), ['nome' => 'UNEB', 'sigla' => 'UNEB']);

        $this->assertSame('logos/a.png', $inst->fresh()->logo_path);
    }

    public function test_discente_recebe_403(): void
    {
        $this->actingAs(User::factory()->discente()->create())
            ->get(route('instituicoes.create'))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/InstituicaoLogoTest.php`
Expected: FAIL — route `instituicoes.store` not defined.

- [ ] **Step 3: Implement `LogoController`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Models\Programa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogoController extends Controller
{
    public const REGRAS = ['file', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048'];

    public static function armazenar(UploadedFile $arquivo, ?string $anterior): string
    {
        $caminho = $arquivo->store('logos', 'local');

        if ($anterior && str_starts_with($anterior, 'logos/')) {
            Storage::disk('local')->delete($anterior);
        }

        return $caminho;
    }

    public function instituicao(Instituicao $instituicao): StreamedResponse
    {
        return $this->responder($instituicao->logo_path);
    }

    public function programa(Programa $programa): StreamedResponse
    {
        return $this->responder($programa->logo_path);
    }

    private function responder(?string $caminho): StreamedResponse
    {
        abort_unless($caminho && str_starts_with($caminho, 'logos/') && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
```

- [ ] **Step 4: Reescrever `InstituicaoController`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Services\ContextoSecretaria;
use Illuminate\Http\Request;

class InstituicaoController extends Controller
{
    public function __construct(private readonly ContextoSecretaria $contexto)
    {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    private function regras(bool $logoObrigatorio): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:50'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:20'],
            'site' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'logo' => [$logoObrigatorio ? 'required' : 'nullable', ...LogoController::REGRAS],
        ];
    }

    private const MENSAGENS = [
        'logo.required' => 'Envie o logotipo da instituição.',
        'logo.mimes' => 'O logotipo deve ser PNG, JPG ou WebP.',
        'logo.mimetypes' => 'O logotipo deve ser PNG, JPG ou WebP.',
        'logo.image' => 'O logotipo deve ser uma imagem PNG, JPG ou WebP.',
        'logo.max' => 'O logotipo deve ter no máximo 2 MB.',
    ];

    public function create()
    {
        $this->autorizarSecretaria();

        if ($this->contexto->programa(auth()->user())?->instituicao) {
            return redirect()->route('instituicao.edit');
        }

        return view('instituicoes.create');
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        abort_if($this->contexto->programa($request->user())?->instituicao_id, 403);

        $dados = $request->validate($this->regras(true), self::MENSAGENS);
        $dados['logo_path'] = LogoController::armazenar($request->file('logo'), null);
        $dados['ativo'] = true;
        unset($dados['logo']);

        $instituicao = Instituicao::create($dados);

        $programa = $this->contexto->programa($request->user());
        if ($programa) {
            $programa->update(['instituicao_id' => $instituicao->id]);

            return redirect()->route('instituicao.edit')->with('status', 'Instituição cadastrada.');
        }

        return redirect()->route('programas.create')
            ->with('status', 'Instituição cadastrada. Agora cadastre o programa.');
    }

    public function edit()
    {
        $this->autorizarSecretaria();
        $instituicao = $this->contexto->programa(auth()->user())?->instituicao;

        if (! $instituicao) {
            return redirect()->route('instituicoes.create');
        }

        return view('instituicoes.edit', compact('instituicao'));
    }

    public function update(Request $request)
    {
        $this->autorizarSecretaria();
        $instituicao = $this->contexto->exigirPrograma($request->user())->instituicao;
        abort_unless($instituicao, 404);

        $dados = $request->validate($this->regras(false), self::MENSAGENS);
        if ($request->hasFile('logo')) {
            $dados['logo_path'] = LogoController::armazenar($request->file('logo'), $instituicao->logo_path);
        }
        unset($dados['logo']);

        $instituicao->update($dados);

        return redirect()->route('instituicao.edit')->with('status', 'Instituição atualizada com sucesso.');
    }
}
```

- [ ] **Step 5: Model helpers**

`Instituicao`:

```php
    public function logoUrl(): string
    {
        if ($this->logo_path && str_starts_with($this->logo_path, 'logos/')) {
            return route('instituicoes.logo', $this);
        }

        return asset($this->logo_path ?: 'images/logoUneb.png');
    }
```

`Programa`:

```php
    public function logoUrl(): ?string
    {
        return $this->logo_path && str_starts_with($this->logo_path, 'logos/')
            ? route('programas.logo', $this)
            : null;
    }
```

- [ ] **Step 6: Rotas** — substituir o bloco `/instituicao` em `routes/web.php` por:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/instituicoes/create', [InstituicaoController::class, 'create'])->name('instituicoes.create');
    Route::post('/instituicoes', [InstituicaoController::class, 'store'])->name('instituicoes.store');
    Route::get('/instituicao', [InstituicaoController::class, 'edit'])->name('instituicao.edit');
    Route::put('/instituicao', [InstituicaoController::class, 'update'])->name('instituicao.update');
    Route::get('/instituicoes/{instituicao}/logo', [LogoController::class, 'instituicao'])->name('instituicoes.logo');
    Route::get('/programas/{programa}/logo', [LogoController::class, 'programa'])->name('programas.logo');
});
```

(Adicionar `use App\Http\Controllers\LogoController;`. Declarar este grupo **antes** de `Route::resource('programas', ...)` para `/programas/{programa}/logo` não colidir.)

- [ ] **Step 7: Views**

`instituicoes/edit.blade.php`: trocar `<form method="POST" action="{{ route('instituicao.update') }}">` por `<form method="POST" action="{{ route('instituicao.update') }}" enctype="multipart/form-data">`; trocar o `@php $logoPath ... @endphp` + `asset($logoPath)` por `<img id="logo-preview" src="{{ $instituicao->logoUrl() }}" ...>`; tornar `sigla` obrigatória (`required`); acrescentar antes do botão salvar:

```blade
<div class="col-12">
    <label for="logo" class="form-label fw-semibold">Logotipo (PNG, JPG ou WebP, até 2 MB)</label>
    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
           class="form-control @error('logo') is-invalid @enderror"
           onchange="if (this.files[0]) document.getElementById('logo-preview').src = URL.createObjectURL(this.files[0])">
    <div class="form-text">Deixe em branco para manter o logotipo atual.</div>
    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

`instituicoes/create.blade.php`: copiar `edit.blade.php`, com `action="{{ route('instituicoes.store') }}"`, sem `@method('PUT')`, valores `old(...)` apenas, preview `<img id="logo-preview" src="" class="img-fluid d-none" ...>` (o `onchange` também remove `d-none`), campo `logo` com `required` e texto "Obrigatório".

- [ ] **Step 8: Run tests** — `php artisan test --compact tests/Feature/InstituicaoLogoTest.php` → PASS; `php artisan test --compact` → all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/InstituicaoController.php app/Http/Controllers/LogoController.php app/Models/Instituicao.php app/Models/Programa.php routes/web.php resources/views/instituicoes tests/Feature/InstituicaoLogoTest.php
git commit -m "feat: cadastrar instituicao do programa com logotipo"
```

---

### Task 4: Programa único por Secretaria, com dados completos

**Files:**
- Modify: `app/Http/Controllers/ProgramaController.php`
- Modify: `app/Http/Controllers/DashboardController.php` (ramo `sec`)
- Modify: `resources/views/programas/create.blade.php`, `resources/views/programas/edit.blade.php`
- Test: `tests/Feature/ProgramaSecretariaTest.php`

**Interfaces:**
- Consumes: `ContextoSecretaria`, `LogoController::REGRAS`, `LogoController::armazenar()`, `Programa::cursos()`.
- Produces: `programas.index` redireciona para `programas.edit` do programa da Secretaria (ou `programas.create`); `programas.create/store` só para Secretaria sem programa.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaSecretariaTest extends TestCase
{
    use RefreshDatabase;

    private function instituicao(): Instituicao
    {
        return Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
    }

    public function test_secretaria_sem_programa_cria_programa_vinculado(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();

        $this->actingAs($sec)->post(route('programas.store'), [
            'nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $inst->id,
            'conceito_capes' => 4, 'ano_inicio' => 2012,
        ])->assertRedirect();

        $programa = Programa::firstOrFail();
        $this->assertSame($inst->id, $programa->instituicao_id);
        $this->assertTrue(app(ContextoSecretaria::class)->programa($sec)->is($programa));
    }

    public function test_programa_exige_instituicao(): void
    {
        $sec = User::factory()->secretaria()->create();

        $this->actingAs($sec)->post(route('programas.store'), ['nome' => 'PPG'])
            ->assertSessionHasErrors('instituicao_id');
    }

    public function test_secretaria_com_programa_nao_cria_outro(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();
        $programa = Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->get(route('programas.create'))->assertRedirect(route('programas.edit', $programa));
        $this->actingAs($sec)->post(route('programas.store'), ['nome' => 'B', 'instituicao_id' => $inst->id])
            ->assertForbidden();
        $this->assertDatabaseCount('programas', 1);
    }

    public function test_secretaria_nao_edita_programa_de_outra(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();
        app(ContextoSecretaria::class)->vincular($sec, Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]));
        $outro = Programa::create(['nome' => 'B', 'instituicao_id' => $inst->id]);

        $this->actingAs($sec)->get(route('programas.edit', $outro))->assertForbidden();
        $this->actingAs($sec)->put(route('programas.update', $outro), ['nome' => 'X', 'instituicao_id' => $inst->id])
            ->assertForbidden();
    }

    public function test_coordenador_deve_ser_docente_permanente_do_programa_e_diferente_do_vice(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();
        $programa = Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $permanente = User::factory()->docente()->create();
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => DocenteProfile::create(['user_id' => $permanente->id, 'ativo' => true])->id,
            'tipo_vinculo' => 'interno', 'categoria_docente' => 'permanente', 'ativo' => true,
        ]);
        $estranho = User::factory()->docente()->create();

        $base = ['nome' => 'A', 'instituicao_id' => $inst->id];
        $this->actingAs($sec)->put(route('programas.update', $programa), $base + ['coordenador_id' => $estranho->id])
            ->assertSessionHasErrors('coordenador_id');
        $this->actingAs($sec)->put(route('programas.update', $programa), $base + [
            'coordenador_id' => $permanente->id, 'vice_coordenador_id' => $permanente->id,
        ])->assertSessionHasErrors('vice_coordenador_id');
        $this->actingAs($sec)->put(route('programas.update', $programa), $base + ['coordenador_id' => $permanente->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($permanente->id, $programa->fresh()->coordenador_id);
    }

    public function test_conceito_capes_fora_de_1_a_7_e_recusado(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();

        $this->actingAs($sec)->post(route('programas.store'), [
            'nome' => 'A', 'instituicao_id' => $inst->id, 'conceito_capes' => 8,
        ])->assertSessionHasErrors('conceito_capes');
    }

    public function test_dashboard_leva_secretaria_sem_programa_ao_cadastro(): void
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);

        $this->actingAs($sec)->get(route('dashboard'))->assertRedirect(route('programas.create'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/ProgramaSecretariaTest.php` → FAIL (programa criado sem vínculo; sem validação de `instituicao_id`).

- [ ] **Step 3: Reescrever `ProgramaController`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Services\ContextoSecretaria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramaController extends Controller
{
    public function __construct(private readonly ContextoSecretaria $contexto)
    {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    private function regras(?Programa $programa): array
    {
        $coordenadorPermanente = function (string $atributo, mixed $valor, Closure $falha) use ($programa) {
            $valido = $programa && DocenteProgramaVinculo::query()
                ->where('programa_id', $programa->id)
                ->where('categoria_docente', 'permanente')
                ->where('ativo', true)
                ->whereHas('docenteProfile', fn ($q) => $q->where('user_id', $valor))
                ->exists();
            if (! $valido) {
                $falha('Escolha um docente permanente ativo vinculado ao programa.');
            }
        };

        return [
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'instituicao_id' => ['required', 'exists:instituicoes,id'],
            'codigo_capes' => ['nullable', 'string', 'max:50'],
            'area_avaliacao' => ['nullable', 'string', 'max:255'],
            'conceito_capes' => ['nullable', 'integer', 'between:1,7'],
            'ano_inicio' => ['nullable', 'integer', 'digits:4', 'min:1950', 'max:' . now()->year],
            'coordenador_id' => ['nullable', 'integer', $coordenadorPermanente],
            'vice_coordenador_id' => ['nullable', 'integer', 'different:coordenador_id', $coordenadorPermanente],
            'email' => ['nullable', 'email', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'descricao' => ['nullable', 'string'],
            'logo' => ['nullable', ...LogoController::REGRAS],
        ];
    }

    private const MENSAGENS = [
        'instituicao_id.required' => 'Selecione a instituição do programa.',
        'conceito_capes.between' => 'O conceito CAPES deve estar entre 1 e 7.',
        'vice_coordenador_id.different' => 'O vice-coordenador deve ser diferente do coordenador.',
    ];

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->programa(auth()->user());

        return $programa
            ? redirect()->route('programas.edit', $programa)
            : redirect()->route('programas.create');
    }

    public function create()
    {
        $this->autorizarSecretaria();
        if ($programa = $this->contexto->programa(auth()->user())) {
            return redirect()->route('programas.edit', $programa);
        }

        $instituicoes = Instituicao::where('ativo', true)->orderBy('nome')->get();

        return view('programas.create', compact('instituicoes'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        abort_if($this->contexto->programa($request->user()), 403, 'Esta Secretaria já administra um programa.');

        $dados = collect($request->validate($this->regras(null), self::MENSAGENS))
            ->except(['logo', 'coordenador_id', 'vice_coordenador_id'])
            ->all();
        if ($request->hasFile('logo')) {
            $dados['logo_path'] = LogoController::armazenar($request->file('logo'), null);
        }
        $dados['ativo'] = true;

        $programa = DB::transaction(function () use ($dados, $request) {
            $programa = Programa::create($dados);
            $this->contexto->vincular($request->user(), $programa);

            return $programa;
        });

        return redirect()->route('programas.edit', $programa)
            ->with('status', 'Programa cadastrado. Cadastre agora os cursos do programa.');
    }

    public function edit(Programa $programa)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma(auth()->user(), $programa->id);

        $instituicoes = Instituicao::where('ativo', true)->orderBy('nome')->get();
        $docentesPermanentes = DocenteProgramaVinculo::with('docenteProfile.user')
            ->where('programa_id', $programa->id)
            ->where('categoria_docente', 'permanente')
            ->where('ativo', true)
            ->get()
            ->map(fn ($v) => $v->docenteProfile?->user)
            ->filter()
            ->sortBy('name')
            ->values();
        $programa->load('cursos');

        return view('programas.edit', compact('programa', 'instituicoes', 'docentesPermanentes'));
    }

    public function update(Request $request, Programa $programa)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma($request->user(), $programa->id);

        $dados = collect($request->validate($this->regras($programa), self::MENSAGENS))->except('logo')->all();
        if ($request->hasFile('logo')) {
            $dados['logo_path'] = LogoController::armazenar($request->file('logo'), $programa->logo_path);
        }

        $programa->update($dados);

        return redirect()->route('programas.edit', $programa)->with('status', 'Programa atualizado com sucesso.');
    }

    public function destroy(Programa $programa)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma(auth()->user(), $programa->id);

        return redirect()->route('programas.edit', $programa)
            ->with('status', 'O programa da Secretaria não pode ser removido.');
    }
}
```

- [ ] **Step 4: Dashboard** — em `DashboardController::index`, antes do `match`, acrescentar:

```php
        if ($user->categoria === 'sec' && ! app(\App\Services\ContextoSecretaria::class)->programa($user)) {
            return redirect()->route('programas.create');
        }
```

- [ ] **Step 5: Views**
- `programas/create.blade.php`: `<form ... enctype="multipart/form-data">`; remover os checkboxes `oferta_mestrado`/`oferta_doutorado`; acrescentar select `instituicao_id` (`@foreach ($instituicoes as $inst) <option value="{{ $inst->id }}" @selected(old('instituicao_id') == $inst->id)>{{ $inst->sigla }} — {{ $inst->nome }}</option>`) com link "Cadastrar nova instituição" para `route('instituicoes.create')`; campos `conceito_capes` (select 1–7 + vazio), `ano_inicio` (number), `logo` (file, `accept="image/png,image/jpeg,image/webp"`).
- `programas/edit.blade.php`: os mesmos campos + selects `coordenador_id` e `vice_coordenador_id` alimentados por `$docentesPermanentes` (`<option value="{{ $u->id }}" @selected(old('coordenador_id', $programa->coordenador_id) == $u->id)>{{ $u->name }}</option>`), preview do logo (`$programa->logoUrl()`), e um cartão "Cursos" listando `$programa->cursos` (nome, prazos, ativo) com link para `route('programa-cursos.index')` (criado na Task 5 — até lá, o link fica comentado ou o teste da Task 5 o valida). Remover o botão de exclusão do programa.

- [ ] **Step 6: Run tests** — `php artisan test --compact tests/Feature/ProgramaSecretariaTest.php` → PASS; `php artisan test --compact` → all pass (ajuste testes existentes que fazem `post(route('programas.store'))` sem `instituicao_id`, se houver: `grep -rn "programas.store" tests`).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProgramaController.php app/Http/Controllers/DashboardController.php resources/views/programas tests/Feature/ProgramaSecretariaTest.php
git commit -m "feat: restringir secretaria a um programa com dados completos"
```

---

### Task 5: Cursos do programa e regras de banca

**Files:**
- Create: `app/Services/CursoProgramaService.php`
- Create: `app/Http/Controllers/ProgramaCursoController.php`
- Create: `resources/views/programas/cursos.blade.php`
- Modify: `routes/web.php`, `resources/views/programas/edit.blade.php` (link), `resources/views/layouts/app.blade.php` (item de menu "Cursos" da Secretaria, junto de "Programa")
- Test: `tests/Feature/ProgramaCursoTest.php`

**Interfaces:**
- Consumes: `ContextoSecretaria`, `ProgramaCurso::PADROES`.
- Produces: `CursoProgramaService::criar(Programa $programa, string $nivel, string $modalidade, ?int $prazoQualificacao = null, ?int $prazoDefesa = null): ProgramaCurso`; `CursoProgramaService::sincronizarOfertas(Programa $programa): void`; rotas `programa-cursos.index` (GET `/programa/cursos`), `programa-cursos.store` (POST `/programa/cursos`), `programa-cursos.update` (PUT `/programa-cursos/{curso}`), `programa-cursos.regras` (PUT `/programa-cursos/{curso}/regras`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaCursoTest extends TestCase
{
    use RefreshDatabase;

    private function secretariaComPrograma(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG', 'oferta_mestrado' => false, 'oferta_doutorado' => false]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        return [$sec, $programa];
    }

    public function test_criar_curso_gera_regras_padrao_e_atualiza_ofertas(): void
    {
        [$sec, $programa] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-cursos.store'), [
            'nivel' => 'doutorado', 'modalidade' => 'academico',
        ])->assertRedirect(route('programa-cursos.index'));

        $curso = ProgramaCurso::with('regrasBanca')->firstOrFail();
        $this->assertSame(30, $curso->prazo_qualificacao_meses);
        $this->assertSame(48, $curso->prazo_defesa_meses);
        $this->assertSame(5, $curso->regraPara('defesa')->min_avaliadores);
        $this->assertSame(2, $curso->regraPara('qualificacao')->min_externos_instituicao);
        $this->assertTrue($programa->fresh()->oferta_doutorado);
        $this->assertFalse($programa->fresh()->oferta_mestrado);
    }

    public function test_nao_duplica_nivel_e_modalidade(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);

        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico'])
            ->assertSessionHasErrors('modalidade');
        $this->assertDatabaseCount('programa_cursos', 1);
    }

    public function test_mestrado_academico_e_profissional_convivem(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'profissional'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('programa_cursos', 2);
    }

    public function test_inativar_curso_recalcula_ofertas_e_mantem_regras(): void
    {
        [$sec, $programa] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);
        $curso = ProgramaCurso::firstOrFail();

        $this->actingAs($sec)->put(route('programa-cursos.update', $curso), [
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => '0',
        ])->assertRedirect();

        $this->assertFalse($curso->fresh()->ativo);
        $this->assertFalse($programa->fresh()->oferta_mestrado);
        $this->assertSame(2, $curso->fresh()->regrasBanca()->count());
    }

    public function test_regras_validam_limites(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-cursos.store'), ['nivel' => 'mestrado', 'modalidade' => 'academico']);
        $curso = ProgramaCurso::firstOrFail();

        $invalida = ['regras' => [
            'qualificacao' => ['min_avaliadores' => 3, 'max_avaliadores' => 2, 'min_internos' => 1, 'min_externos_instituicao' => 1],
            'defesa' => ['min_avaliadores' => 2, 'max_avaliadores' => 3, 'min_internos' => 2, 'min_externos_instituicao' => 2],
        ]];
        $this->actingAs($sec)->put(route('programa-cursos.regras', $curso), $invalida)
            ->assertSessionHasErrors(['regras.qualificacao.max_avaliadores', 'regras.defesa.min_externos_instituicao']);

        $valida = ['regras' => [
            'qualificacao' => ['min_avaliadores' => 3, 'max_avaliadores' => 4, 'min_internos' => 1, 'min_externos_instituicao' => 1],
            'defesa' => ['min_avaliadores' => 3, 'max_avaliadores' => 5, 'min_internos' => 2, 'min_externos_instituicao' => 1],
        ]];
        $this->actingAs($sec)->put(route('programa-cursos.regras', $curso), $valida)->assertSessionHasNoErrors();
        $this->assertSame(4, $curso->fresh()->regraPara('qualificacao')->max_avaliadores);
    }

    public function test_curso_de_outro_programa_retorna_403(): void
    {
        [$sec] = $this->secretariaComPrograma();
        $outro = Programa::create(['nome' => 'Outro']);
        $curso = ProgramaCurso::create([
            'programa_id' => $outro->id, 'nivel' => 'mestrado', 'modalidade' => 'academico',
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'ativo' => true,
        ]);

        $this->actingAs($sec)->put(route('programa-cursos.update', $curso), [
            'prazo_qualificacao_meses' => 1, 'prazo_defesa_meses' => 2, 'ativo' => '1',
        ])->assertForbidden();
    }

    public function test_docente_recebe_403(): void
    {
        $this->actingAs(User::factory()->docente()->create())
            ->get(route('programa-cursos.index'))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — FAIL (route not defined).

- [ ] **Step 3: Implement `CursoProgramaService`**

```php
<?php

namespace App\Services;

use App\Models\Programa;
use App\Models\ProgramaCurso;
use Illuminate\Support\Facades\DB;

class CursoProgramaService
{
    public function criar(Programa $programa, string $nivel, string $modalidade, ?int $prazoQualificacao = null, ?int $prazoDefesa = null): ProgramaCurso
    {
        $padrao = ProgramaCurso::PADROES[$nivel];

        return DB::transaction(function () use ($programa, $nivel, $modalidade, $prazoQualificacao, $prazoDefesa, $padrao) {
            $curso = ProgramaCurso::create([
                'programa_id' => $programa->id,
                'nivel' => $nivel,
                'modalidade' => $modalidade,
                'prazo_qualificacao_meses' => $prazoQualificacao ?? $padrao['prazo_qualificacao_meses'],
                'prazo_defesa_meses' => $prazoDefesa ?? $padrao['prazo_defesa_meses'],
                'ativo' => true,
            ]);

            [$min, $max, $internos, $externos] = $padrao['regra'];
            foreach (ProgramaCurso::ETAPAS as $etapa) {
                $curso->regrasBanca()->create([
                    'etapa' => $etapa,
                    'min_avaliadores' => $min,
                    'max_avaliadores' => $max,
                    'min_internos' => $internos,
                    'min_externos_instituicao' => $externos,
                ]);
            }

            $this->sincronizarOfertas($programa);

            return $curso;
        });
    }

    public function sincronizarOfertas(Programa $programa): void
    {
        $niveis = $programa->cursos()->where('ativo', true)->pluck('nivel');

        $programa->update([
            'oferta_mestrado' => $niveis->contains('mestrado'),
            'oferta_doutorado' => $niveis->contains('doutorado'),
        ]);
    }
}
```

- [ ] **Step 4: Implement `ProgramaCursoController`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProgramaCurso;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramaCursoController extends Controller
{
    public function __construct(
        private readonly ContextoSecretaria $contexto,
        private readonly CursoProgramaService $cursos,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma(auth()->user());
        $cursos = $programa->cursos()->with('regrasBanca')->get();

        return view('programas.cursos', compact('programa', 'cursos'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());

        $dados = $request->validate([
            'nivel' => ['required', Rule::in(ProgramaCurso::NIVEIS)],
            'modalidade' => ['required', Rule::in(ProgramaCurso::MODALIDADES),
                Rule::unique('programa_cursos')->where(fn ($q) => $q
                    ->where('programa_id', $programa->id)
                    ->where('nivel', $request->input('nivel')))],
            'prazo_qualificacao_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'prazo_defesa_meses' => ['nullable', 'integer', 'min:1', 'max:120', 'gte:prazo_qualificacao_meses'],
        ], ['modalidade.unique' => 'O programa já possui este curso.']);

        $this->cursos->criar($programa, $dados['nivel'], $dados['modalidade'],
            $dados['prazo_qualificacao_meses'] ?? null, $dados['prazo_defesa_meses'] ?? null);

        return redirect()->route('programa-cursos.index')->with('status', 'Curso cadastrado com regras de banca padrão.');
    }

    public function update(Request $request, ProgramaCurso $curso)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma($request->user(), $curso->programa_id);

        $dados = $request->validate([
            'prazo_qualificacao_meses' => ['required', 'integer', 'min:1', 'max:120'],
            'prazo_defesa_meses' => ['required', 'integer', 'min:1', 'max:120', 'gte:prazo_qualificacao_meses'],
            'ativo' => ['required', 'boolean'],
        ]);

        $curso->update($dados);
        $this->cursos->sincronizarOfertas($curso->programa);

        return redirect()->route('programa-cursos.index')->with('status', 'Curso atualizado.');
    }

    public function regras(Request $request, ProgramaCurso $curso)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma($request->user(), $curso->programa_id);

        $regras = [];
        foreach (ProgramaCurso::ETAPAS as $etapa) {
            $p = "regras.$etapa.";
            $regras[$p . 'min_avaliadores'] = ['required', 'integer', 'min:1', 'max:5'];
            $regras[$p . 'max_avaliadores'] = ['required', 'integer', 'max:5', 'gte:' . $p . 'min_avaliadores'];
            $regras[$p . 'min_internos'] = ['required', 'integer', 'min:0', 'max:5'];
            $regras[$p . 'min_externos_instituicao'] = ['required', 'integer', 'min:0', 'max:5'];
        }
        $dados = $request->validate($regras);

        $erros = [];
        foreach (ProgramaCurso::ETAPAS as $etapa) {
            $r = $dados['regras'][$etapa];
            if ($r['min_internos'] + $r['min_externos_instituicao'] > $r['max_avaliadores']) {
                $erros["regras.$etapa.min_externos_instituicao"] = 'Internos + externos não podem exceder o máximo de avaliadores.';
            }
        }
        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        DB::transaction(function () use ($curso, $dados) {
            foreach (ProgramaCurso::ETAPAS as $etapa) {
                $curso->regrasBanca()->updateOrCreate(['etapa' => $etapa], $dados['regras'][$etapa]);
            }
        });

        return redirect()->route('programa-cursos.index')->with('status', 'Regras de banca atualizadas.');
    }
}
```

- [ ] **Step 5: Rotas** (no grupo `auth, verified`, antes de `Route::resource('programas', ...)`):

```php
    Route::get('/programa/cursos', [ProgramaCursoController::class, 'index'])->name('programa-cursos.index');
    Route::post('/programa/cursos', [ProgramaCursoController::class, 'store'])->name('programa-cursos.store');
    Route::put('/programa-cursos/{curso}', [ProgramaCursoController::class, 'update'])->name('programa-cursos.update');
    Route::put('/programa-cursos/{curso}/regras', [ProgramaCursoController::class, 'regras'])->name('programa-cursos.regras');
```

- [ ] **Step 6: View `programas/cursos.blade.php`** (layout `x-app-layout`, Bootstrap, mesmo padrão visual de `criterios-cpa/index`): formulário "Novo curso" (selects `nivel`, `modalidade`; números opcionais de prazo); para cada curso um cartão com título `{{ $curso->nome }}` + badge ativo/inativo, form PUT `programa-cursos.update` (prazos + select `ativo` 1/0) e form PUT `programa-cursos.regras` com uma tabela linhas = Qualificação/Defesa, colunas = mín. avaliadores, máx. avaliadores, mín. internos, mín. externos à `{{ $programa->instituicao?->sigla ?? 'instituição' }}`, inputs `name="regras[{{ $etapa }}][min_avaliadores]"` etc. com `value="{{ old("regras.$etapa.min_avaliadores", $curso->regraPara($etapa)?->min_avaliadores) }}"`; exibir `@error` por campo.

- [ ] **Step 7: Run tests** — `php artisan test --compact tests/Feature/ProgramaCursoTest.php` → PASS; suíte completa → PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/CursoProgramaService.php app/Http/Controllers/ProgramaCursoController.php resources/views/programas routes/web.php resources/views/layouts/app.blade.php tests/Feature/ProgramaCursoTest.php
git commit -m "feat: cadastrar cursos do programa com regras de banca"
```

---

### Task 6: VerificacaoBancaService — ORCID e composição por curso

**Files:**
- Create: `app/Services/VerificacaoBancaService.php`
- Modify: `app/Http/Controllers/BancaController.php` (`validarComposicao`, `externoUneb`, `dadosFormulario`)
- Modify: `app/Services/HomologacaoBancaService.php`, `app/Http/Controllers/HomologacaoBancaController.php` (`index`)
- Modify: `resources/views/bancas/form.blade.php` (rótulos), `resources/views/bancas/homologacao.blade.php` (quadro de verificação)
- Modify: `tests/Feature/HomologacaoBancaTest.php` (fixture: ORCID do discente + membros + instituição)
- Test: `tests/Feature/VerificacaoBancaTest.php`

**Interfaces:**
- Consumes: `ProgramaCurso::PADROES`, `ProgramaCurso::regraPara()`, `DiscenteProgramaVinculo::programaCurso()`, `Programa::instituicao()`.
- Produces:
  - `VerificacaoBancaService::regraPara(TrabalhoAcademico $trabalho, string $etapa): array` → `['min_avaliadores'=>int,'max_avaliadores'=>int,'min_internos'=>int,'min_externos_instituicao'=>int]`
  - `VerificacaoBancaService::externoInstituicao(DocenteProgramaVinculo $vinculo, ?string $siglaInstituicao): bool`
  - `VerificacaoBancaService::verificar(TrabalhoAcademico $trabalho, string $etapa, Collection $avaliadorVinculoIds): Collection` de itens `['chave'=>string,'descricao'=>string,'exigido'=>string,'encontrado'=>string,'ok'=>bool]`
  - `VerificacaoBancaService::verificarBanca(Banca $banca): Collection` (mesmo formato, usando membros ativos)
  - `VerificacaoBancaService::exigir(Collection $itens, string $campo): void` (lança `ValidationException` com as descrições das falhas)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\CursoProgramaService;
use App\Services\VerificacaoBancaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VerificacaoBancaTest extends TestCase
{
    use RefreshDatabase;

    private function trabalho(?string $orcid, bool $comCurso = true, bool $comInstituicao = true): TrabalhoAcademico
    {
        $inst = $comInstituicao ? Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]) : null;
        $programa = Programa::create(['nome' => 'PPG', 'instituicao_id' => $inst?->id]);
        $curso = $comCurso ? app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico') : null;
        $profile = DiscenteProfile::create([
            'user_id' => User::factory()->discente()->create()->id, 'orcid_url' => $orcid, 'ativo' => true,
        ]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'programa_curso_id' => $curso?->id, 'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);

        return TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function avaliador(Programa $programa, string $tipo, string $sigla): int
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => $sigla, 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo, 'pode_avaliar' => true, 'ativo' => true,
        ])->id;
    }

    public function test_banca_conforme_passa(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097');
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', 'UFBA'),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertTrue($itens->every(fn ($i) => $i['ok']));
    }

    public function test_sem_orcid_falha(): void
    {
        $trabalho = $this->trabalho(null);
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', 'UFBA'),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertFalse($itens->firstWhere('chave', 'orcid')['ok']);
        $this->expectException(ValidationException::class);
        app(VerificacaoBancaService::class)->exigir($itens, 'avaliadores');
    }

    public function test_externo_a_instituicao_usa_sigla_da_instituicao_do_programa(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097');
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', ' uneb '),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertFalse($itens->firstWhere('chave', 'externos_instituicao')['ok']);
    }

    public function test_regra_editada_do_curso_prevalece(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097');
        $trabalho->discenteVinculo->programaCurso->regrasBanca()->where('etapa', 'qualificacao')
            ->update(['min_avaliadores' => 3, 'max_avaliadores' => 3]);
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', 'UFBA'),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertFalse($itens->firstWhere('chave', 'quantidade')['ok']);
    }

    public function test_programa_legado_sem_curso_e_sem_instituicao_usa_padrao(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097', comCurso: false, comInstituicao: false);

        $regra = app(VerificacaoBancaService::class)->regraPara($trabalho, 'defesa');

        $this->assertSame(['min_avaliadores' => 2, 'max_avaliadores' => 5, 'min_internos' => 1, 'min_externos_instituicao' => 1], $regra);
        $this->assertTrue(app(VerificacaoBancaService::class)->externoInstituicao(
            DocenteProgramaVinculo::find($this->avaliador($trabalho->programa, 'externo', 'UFBA'))->load('docenteProfile'),
            null,
        ));
    }
}
```

Also append to `tests/Feature/HomologacaoBancaTest.php`:

```php
    public function test_banca_sem_orcid_do_discente_nao_e_homologada(): void
    {
        [$secretaria, , $banca] = $this->estrutura(orcid: null);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_tela_de_homologacao_mostra_quadro_de_verificacao(): void
    {
        [$secretaria] = $this->estrutura(orcid: null);

        $this->actingAs($secretaria)->get(route('bancas.homologacao'))
            ->assertOk()
            ->assertSee('ORCID do discente')
            ->assertSee('Número de avaliadores');
    }
```

and change the fixture `estrutura()` to `private function estrutura(?string $orcid = 'https://orcid.org/0000-0002-1825-0097'): array`, passing `'orcid_url' => $orcid` to `DiscenteProfile::create`, creating `Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true])` and `'instituicao_id'` on the `Programa`, and, after creating the banca, two members (interno `UNEB` + externo `UFBA`) using `DocenteProfile` / `DocenteProgramaVinculo` / `BancaMembro::create(['banca_id' => $banca->id, 'docente_programa_vinculo_id' => $id, 'papel' => 'avaliador', 'ordem' => $n, 'ativo' => true])`. Move the member-creation loop that exists in `estruturaComComposicao()` into `estrutura()` (keep `estruturaComComposicao()` creating only the orientador) so that the defense test still finds one `interno` and one `externo` vínculo.

- [ ] **Step 2: Run tests to verify they fail** — `php artisan test --compact tests/Feature/VerificacaoBancaTest.php tests/Feature/HomologacaoBancaTest.php` → FAIL (service missing; homologation ignores ORCID).

- [ ] **Step 3: Implement `VerificacaoBancaService`**

```php
<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\DocenteProgramaVinculo;
use App\Models\ProgramaCurso;
use App\Models\TrabalhoAcademico;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VerificacaoBancaService
{
    public function regraPara(TrabalhoAcademico $trabalho, string $etapa): array
    {
        $trabalho->loadMissing('discenteVinculo.programaCurso.regrasBanca');
        $regra = $trabalho->discenteVinculo?->programaCurso?->regraPara($etapa);

        if ($regra) {
            return $regra->only(['min_avaliadores', 'max_avaliadores', 'min_internos', 'min_externos_instituicao']);
        }

        $nivel = $trabalho->discenteVinculo?->programaCurso?->nivel ?? $trabalho->nivel ?? 'mestrado';
        [$min, $max, $internos, $externos] = (ProgramaCurso::PADROES[$nivel] ?? ProgramaCurso::PADROES['mestrado'])['regra'];

        return [
            'min_avaliadores' => $min,
            'max_avaliadores' => $max,
            'min_internos' => $internos,
            'min_externos_instituicao' => $externos,
        ];
    }

    public function externoInstituicao(DocenteProgramaVinculo $vinculo, ?string $siglaInstituicao): bool
    {
        $normalizar = fn (?string $s) => mb_strtoupper(preg_replace('/\s+/', '', (string) $s));
        $sigla = $normalizar($vinculo->docenteProfile?->instituicao_sigla);

        return $sigla !== '' && $sigla !== $normalizar($siglaInstituicao);
    }

    public function verificar(TrabalhoAcademico $trabalho, string $etapa, Collection $avaliadorVinculoIds): Collection
    {
        $trabalho->loadMissing('programa.instituicao', 'discenteVinculo.discenteProfile');
        $regra = $this->regraPara($trabalho, $etapa);
        $sigla = $trabalho->programa?->instituicao?->sigla;

        $avaliadores = DocenteProgramaVinculo::with('docenteProfile')
            ->whereIn('id', $avaliadorVinculoIds->map(fn ($id) => (int) $id)->unique())
            ->get();
        $total = $avaliadores->count();
        $internos = $avaliadores->where('tipo_vinculo', 'interno')->count();
        $externos = $avaliadores->filter(fn ($v) => $this->externoInstituicao($v, $sigla))->count();
        $orcid = filled($trabalho->discenteVinculo?->discenteProfile?->orcid_url);
        $rotuloInstituicao = $sigla ?: 'instituição do programa';

        return collect([
            [
                'chave' => 'orcid',
                'descricao' => 'ORCID do discente',
                'exigido' => 'informado',
                'encontrado' => $orcid ? 'informado' : 'ausente',
                'ok' => $orcid,
                'mensagem' => 'O discente precisa informar o ORCID no seu cadastro.',
            ],
            [
                'chave' => 'quantidade',
                'descricao' => 'Número de avaliadores',
                'exigido' => $regra['min_avaliadores'] === $regra['max_avaliadores']
                    ? (string) $regra['min_avaliadores']
                    : "{$regra['min_avaliadores']} a {$regra['max_avaliadores']}",
                'encontrado' => (string) $total,
                'ok' => $total >= $regra['min_avaliadores'] && $total <= $regra['max_avaliadores'],
                'mensagem' => "A banca deve ter entre {$regra['min_avaliadores']} e {$regra['max_avaliadores']} avaliadores.",
            ],
            [
                'chave' => 'internos',
                'descricao' => 'Avaliadores internos ao programa',
                'exigido' => "≥ {$regra['min_internos']}",
                'encontrado' => (string) $internos,
                'ok' => $internos >= $regra['min_internos'],
                'mensagem' => "A banca deve ter pelo menos {$regra['min_internos']} avaliador(es) interno(s) ao programa.",
            ],
            [
                'chave' => 'externos_instituicao',
                'descricao' => "Avaliadores externos à {$rotuloInstituicao}",
                'exigido' => "≥ {$regra['min_externos_instituicao']}",
                'encontrado' => (string) $externos,
                'ok' => $externos >= $regra['min_externos_instituicao'],
                'mensagem' => "A banca deve ter pelo menos {$regra['min_externos_instituicao']} avaliador(es) externo(s) à {$rotuloInstituicao}.",
            ],
        ]);
    }

    public function verificarBanca(Banca $banca): Collection
    {
        $banca->loadMissing('trabalhoAcademico', 'membros');
        $ids = $banca->membros->where('ativo', true)->where('papel', 'avaliador')->pluck('docente_programa_vinculo_id');

        return $this->verificar($banca->trabalhoAcademico, $banca->tipo, $ids);
    }

    public function exigir(Collection $itens, string $campo): void
    {
        $falhas = $itens->reject(fn ($i) => $i['ok'])->pluck('mensagem');

        if ($falhas->isNotEmpty()) {
            throw ValidationException::withMessages([$campo => $falhas->implode(' ')]);
        }
    }
}
```

- [ ] **Step 4: Integrar em `BancaController`**
- Injetar no construtor (ou via `app()`): `private readonly VerificacaoBancaService $verificacao`.
- Em `validarComposicao`, **manter** as verificações de orientador, de orientador/coorientador como avaliador e de avaliadores ativos/do programa/`pode_avaliar`; **remover** o bloco `$internos`/`$externosUneb` e os `if ($trabalho->nivel === 'mestrado')`/`'doutorado'`; no fim, acrescentar a assinatura `string $etapa` e:

```php
        $this->verificacao->exigir(
            $this->verificacao->verificar($trabalho, $etapa, $avaliadorIds),
            'avaliadores',
        );
```

  Chamadas: `store` → `$this->validarComposicao($trabalho, $data['tipo'], collect($data['avaliadores'] ?? []));`; `update` → `$this->validarComposicao($banca->trabalhoAcademico, $data['tipo'], collect($data['avaliadores'] ?? []));`.
- Substituir `externoUneb(DocenteProgramaVinculo $vinculo)` por uso de `$this->verificacao->externoInstituicao($vinculo, $trabalho?->programa?->instituicao?->sigla)` em `dadosFormulario` (passe a sigla calculada; remova o método `externoUneb`).
- `dadosFormulario` passa também `'siglaPrograma' => $trabalho?->programa?->sigla ?? 'programa'`, `'siglaInstituicao' => $trabalho?->programa?->instituicao?->sigla ?? 'instituição'` e `'regra' => $trabalho ? $this->verificacao->regraPara($trabalho, $banca->tipo) : null`.
- `bancas/form.blade.php`: trocar os textos fixos "interno ao PPGMSB" → `interno ao {{ $siglaPrograma }}`, "externo à UNEB" → `externo à {{ $siglaInstituicao }}`; a linha 97 passa a descrever a regra (`Regra do curso: {{ $regra['min_avaliadores'] }} a {{ $regra['max_avaliadores'] }} avaliadores, ≥ {{ $regra['min_internos'] }} interno(s), ≥ {{ $regra['min_externos_instituicao'] }} externo(s) à {{ $siglaInstituicao }}.` dentro de `@if ($regra)`); na linha ~293 trocar `strtoupper($profile->instituicao_sigla) !== 'UNEB'` por `strtoupper(trim($profile->instituicao_sigla)) !== strtoupper(trim($siglaInstituicao))`.

- [ ] **Step 5: Integrar na homologação**
- `HomologacaoBancaService`: injetar `VerificacaoBancaService $verificacao` no construtor; no início de `homologar`, antes das pendências CPA:

```php
        $this->verificacao->exigir($this->verificacao->verificarBanca($banca), 'homologacao');
```

- `HomologacaoBancaController::index`: após carregar `$bancas`, `$verificacoes = $bancas->mapWithKeys(fn ($b) => [$b->id => app(VerificacaoBancaService::class)->verificarBanca($b)]);` e passar à view. Acrescentar `'membros'` ao `with`.
- `bancas/homologacao.blade.php`: para cada banca, antes dos botões, uma tabela "Verificação" com `@foreach ($verificacoes[$banca->id] as $item)` → `{{ $item['descricao'] }} | {{ $item['exigido'] }} | {{ $item['encontrado'] }} | <i class="bi bi-{{ $item['ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i>`; o botão "Homologar" recebe `@disabled($verificacoes[$banca->id]->contains(fn ($i) => ! $i['ok']))`.

- [ ] **Step 6: Run tests** — `php artisan test --compact tests/Feature/VerificacaoBancaTest.php tests/Feature/HomologacaoBancaTest.php` → PASS; suíte completa → PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/VerificacaoBancaService.php app/Services/HomologacaoBancaService.php app/Http/Controllers/BancaController.php app/Http/Controllers/HomologacaoBancaController.php resources/views/bancas tests/Feature/VerificacaoBancaTest.php tests/Feature/HomologacaoBancaTest.php
git commit -m "feat: verificar orcid e composicao da banca pelas regras do curso"
```

---

### Task 7: ORCID/Lattes no perfil do discente, envio bloqueado e cadastro por curso

**Files:**
- Modify: `app/Http/Controllers/ProfileController.php`, `app/Http/Requests/ProfileUpdateRequest.php`
- Create: `resources/views/profile/partials/update-identificadores-academicos-form.blade.php`
- Modify: `resources/views/profile/edit.blade.php`, `resources/views/dashboards/discente.blade.php` (alerta)
- Modify: `app/Http/Controllers/TrabalhoDocumentoController.php` (`enviarParaBanca`)
- Modify: `app/Http/Controllers/UsuarioController.php` (`cadastroStatus`, regras de `store`/`update`, curso), `resources/views/usuarios/create.blade.php`, `resources/views/usuarios/edit.blade.php`
- Test: `tests/Feature/OrcidDiscenteTest.php`

**Interfaces:**
- Consumes: `ProgramaCurso`, `ContextoSecretaria`.
- Produces: campo `programa_curso_id` no cadastro/edição de discente (substitui `nivel`); `nivel` do vínculo e do trabalho = `ProgramaCurso::nivel`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrcidDiscenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_discente_informa_orcid_e_lattes_no_perfil(): void
    {
        $user = User::factory()->discente()->create(['primeiro_acesso' => false]);
        DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://orcid.org/0000-0002-1825-0097', $user->discenteProfile->fresh()->orcid_url);
    }

    public function test_orcid_invalido_e_recusado(): void
    {
        $user = User::factory()->discente()->create();
        DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'orcid_url' => '0000-0002',
        ])->assertSessionHasErrors('orcid_url');
    }

    public function test_envio_para_banca_sem_orcid_e_recusado(): void
    {
        $user = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG']);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        Banca::create(['trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'cadastrada', 'libera_envio_trabalho' => true]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'enviado_por_id' => $user->id, 'tipo_documento' => 'qualificacao',
            'arquivo_pdf_path' => 'x.pdf', 'arquivo_pdf_nome_original' => 'x.pdf',
        ]);

        $this->actingAs($user)->post(route('meu-trabalho.enviar-banca', $documento))
            ->assertSessionHasErrors('orcid');
    }

    public function test_secretaria_cadastra_discente_sem_orcid_com_lattes_e_curso(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        $curso = app(CursoProgramaService::class)->criar($programa, 'doutorado', 'profissional');

        $this->actingAs($sec)->post(route('usuarios.store'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '2026001', 'programa_curso_id' => $curso->id,
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
        ])->assertSessionHasNoErrors();

        $usuario = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('completo', $usuario->cadastro_status);
        $this->assertDatabaseHas('discente_programa_vinculos', ['programa_curso_id' => $curso->id, 'nivel' => 'doutorado']);
    }

    public function test_discente_sem_lattes_e_recusado(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        $curso = app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');

        $this->actingAs($sec)->post(route('usuarios.store'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '1', 'programa_curso_id' => $curso->id,
        ])->assertSessionHasErrors('lattes_url');
    }
}
```

(Confira o nome da rota de envio com `php artisan route:list --path=meu-trabalho` e o enum `tipo_documento` de `trabalho_documentos`; ajuste os valores do teste para os reais antes de rodar.)

- [ ] **Step 2: Run test to verify it fails** — FAIL.

- [ ] **Step 3: Perfil**

`ProfileUpdateRequest::rules()` acrescenta:

```php
            'orcid_url' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/orcid\.org\/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/'],
            'lattes_url' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/lattes\.cnpq\.br\/[0-9]+$/'],
```

`ProfileController::update`:

```php
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->categoria === 'discente' && $user->discenteProfile) {
            $user->discenteProfile->update($request->safe()->only(['orcid_url', 'lattes_url']));
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
```

Nova partial `update-identificadores-academicos-form.blade.php` — **o mesmo `<form method="post" action="{{ route('profile.update') }}">` com `@method('patch')`**, então inclua também campos ocultos `name`/`email` com os valores atuais — com inputs `orcid_url` e `lattes_url` (`value="{{ old('orcid_url', $user->discenteProfile?->orcid_url) }}"`), placeholders `https://orcid.org/0000-0000-0000-0000` e `http://lattes.cnpq.br/1234567890123456`, e `<x-input-error :messages="$errors->get('orcid_url')" />`. Em `profile/edit.blade.php`, incluir a partial em um novo cartão `@if ($user->categoria === 'discente')`.

`dashboards/discente.blade.php` (topo do conteúdo):

```blade
@if (blank($usuario->discenteProfile?->orcid_url))
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>Informe seu ORCID no <a href="{{ route('profile.edit') }}">seu cadastro</a>. Ele é obrigatório para qualificação e defesa.</div>
    </div>
@endif
```

- [ ] **Step 4: Envio para banca** — em `TrabalhoDocumentoController::enviarParaBanca`, logo após o `abort(403)` de posse:

```php
        if (blank($usuario->discenteProfile?->orcid_url)) {
            return back()->withErrors(['orcid' => 'Informe seu ORCID no seu cadastro antes de enviar o trabalho para a banca.']);
        }
```

- [ ] **Step 5: Cadastro individual por curso** (`UsuarioController`)
- Em `store` e `update`, nas regras: trocar `'orcid_url' => ['required_unless:categoria,sec', ...]` por `['required_if:categoria,docente', 'nullable', ...]` (mensagem `orcid_url.required_if` = "O ORCID é obrigatório para docentes."); `lattes_url` ganha `'required_if:categoria,discente'` (mensagem "O Lattes é obrigatório para discentes."); trocar `'nivel' => [...]` por:

```php
            'programa_curso_id' => [
                'required_if:categoria,discente', 'nullable',
                Rule::exists('programa_cursos', 'id')->where(fn ($q) => $q
                    ->where('programa_id', $programaAtual?->id)
                    ->where('ativo', true)),
            ],
```

- Em `cadastroStatus`, para discente: `['matricula', 'programa_curso_id', 'lattes_url']`.
- Ao criar/atualizar o vínculo: `$curso = ProgramaCurso::find($dados['programa_curso_id']);` e usar `'programa_curso_id' => $curso?->id, 'nivel' => $curso?->nivel ?? 'mestrado'` no `DiscenteProgramaVinculo` e `'nivel' => $vinculo->nivel` no `TrabalhoAcademico` (já é assim).
- `create`/`edit` passam `$cursos = $programaAtual?->cursos()->where('ativo', true)->get() ?? collect();` à view; em `usuarios/create.blade.php` e `edit.blade.php`, trocar o select `nivel` por select `programa_curso_id` (`{{ $curso->nome }}`), e marcar Lattes como obrigatório para discente e ORCID como "opcional — o discente pode informar no próprio cadastro".

- [ ] **Step 6: Run tests** — `php artisan test --compact tests/Feature/OrcidDiscenteTest.php` → PASS; suíte completa → PASS (ajustar testes existentes que postam `nivel` para `usuarios.store`: `grep -rn "usuarios.store\|'nivel' =>" tests`).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProfileController.php app/Http/Requests/ProfileUpdateRequest.php resources/views/profile resources/views/dashboards/discente.blade.php app/Http/Controllers/TrabalhoDocumentoController.php app/Http/Controllers/UsuarioController.php resources/views/usuarios tests/Feature/OrcidDiscenteTest.php
git commit -m "feat: exigir orcid do discente nos atos de banca e cadastrar por curso"
```

---

### Task 8: A13/A14 com prazos do curso

**Files:**
- Modify: `app/Services/AutoavaliacaoIndicadorService.php` (bloco "Eficiência do percurso — A13 a A16")
- Test: `tests/Feature/AutoavaliacaoPrazoCursoTest.php`

**Interfaces:**
- Consumes: `DiscenteProgramaVinculo::programaCurso()`.

- [ ] **Step 1: Write the failing test** — ler o método público de `AutoavaliacaoIndicadorService` que calcula o período (ver assinatura no topo do arquivo e em `tests/Feature/PerguntaEstrategicaTest.php`, que já o usa) e escrever o teste com: programa, curso doutorado acadêmico com `prazo_qualificacao_meses = 30`, discente com `data_ingresso = 2024-01-01`, banca de qualificação `finalizada` com `data_hora = 2026-03-01` (26 meses). Asserção: indicador `A13` = `100.0` (seria `0.0` com o limite fixo de 18) e rótulo de `A13` contém "prazo do curso".

- [ ] **Step 2: Run** → FAIL (A13 = 0.0).

- [ ] **Step 3: Implement** — substituir os filtros fixos:

```php
            $prazo = fn ($b, string $campo, int $padrao) =>
                $b->trabalhoAcademico->discenteVinculo->programaCurso?->{$campo} ?? $padrao;

            $a13 = $totalQual > 0
                ? round($qualificacoes->filter(fn ($b) =>
                    $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora)
                        <= $prazo($b, 'prazo_qualificacao_meses', 18))->count() / $totalQual * 100, 1)
                : null;

            $a14 = $totalDefesa > 0
                ? round($defesas->filter(fn ($b) =>
                    $b->trabalhoAcademico->discenteVinculo->data_ingresso->diffInMonths($b->data_hora)
                        <= $prazo($b, 'prazo_defesa_meses', 24))->count() / $totalDefesa * 100, 1)
                : null;
```

  e os rótulos: `'Qualificações realizadas no prazo do curso (%)'` e `'Defesas realizadas no prazo do curso (%)'`. Garantir que a query de `$bancas` carregue `trabalhoAcademico.discenteVinculo.programaCurso` (acrescentar ao `with`).

- [ ] **Step 4: Run** → PASS; suíte completa → PASS (atualizar asserções de rótulo antigas se existirem: `grep -rn "18 meses\|24 meses" tests resources/views`; ajustar também textos em `resources/views/relatorios/*` que citem os limites fixos).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AutoavaliacaoIndicadorService.php tests/Feature/AutoavaliacaoPrazoCursoTest.php resources/views/relatorios
git commit -m "feat: medir eficiencia pelos prazos de cada curso"
```

---

### Task 9: Ata com logotipos e cabeçalho do cadastro

**Files:**
- Modify: `resources/views/avaliacoes/ata.blade.php` (cabeçalho, linhas ~258-268 e título)
- Modify: `app/Http/Controllers/AvaliacaoController.php` (método `ata`: carregar `trabalhoAcademico.programa.instituicao`)
- Test: `tests/Feature/AtaCabecalhoTest.php`

- [ ] **Step 1: Write the failing test** — montar (reusando o padrão de `HomologacaoBancaTest::estrutura`) uma banca `finalizada` de programa `Programa de Pós-Graduação em Teste` com `logo_path = 'logos/p.png'`, instituição `Universidade Teste` sigla `UT`, departamento `Depto X`, cidade `Feira`, estado `BA`, `logo_path = 'logos/i.png'`; `actingAs` Secretaria do programa; `get(route('avaliacoes.ata', $banca))` → `assertOk()`, `assertSee('Universidade Teste')`, `assertSee('Depto X')`, `assertSee(route('programas.logo', $programa), false)`, `assertSee(route('instituicoes.logo', $instituicao), false)`, `assertDontSee('Modelagem e Simulação de Biossistemas')`. Segundo teste: programa sem logo → `assertDontSee(route('programas.logo', $programa), false)`.

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Implement** — no topo da view: `@php $programa = $banca->trabalhoAcademico->programa; $instituicao = $programa?->instituicao; @endphp`; cabeçalho:

```blade
    <div class="cabecalho">
        @if ($programa?->logoUrl())
            <img src="{{ $programa->logoUrl() }}" alt="{{ $programa->sigla }}">
        @endif
        <div class="cabecalho-texto">
            <p>{{ $instituicao?->nome ?? 'Universidade do Estado da Bahia' }}</p>
            @if ($instituicao?->departamento)<p>{{ $instituicao->departamento }}</p>@endif
            <p>{{ $programa?->nome }}</p>
            @if ($instituicao?->cidade)
                <p class="inst">{{ $instituicao->cidade }}{{ $instituicao->estado ? ' — ' . $instituicao->estado : '' }}</p>
            @endif
        </div>
        <img src="{{ $instituicao?->logoUrl() ?? asset('images/logo_uneb.png') }}" alt="{{ $instituicao?->sigla ?? 'UNEB' }}">
    </div>
```

  Título: trocar `em Modelagem e Simulação de Biossistemas` por `em {{ \Illuminate\Support\Str::after($programa?->nome ?? '', 'em ') ?: $programa?->nome }}`. Rodar `grep -n "Modelagem e Simulação\|PPGMSB\|UNEB" resources/views/avaliacoes/ata.blade.php` e substituir os demais textos fixos pelo equivalente do cadastro.

- [ ] **Step 4: Run** → PASS; suíte → PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/avaliacoes/ata.blade.php app/Http/Controllers/AvaliacaoController.php tests/Feature/AtaCabecalhoTest.php
git commit -m "feat: gerar cabecalho da ata a partir do cadastro"
```

---

### Task 10: Importação de discentes por CSV

**Files:**
- Create: `app/Services/CadastroDiscenteService.php`
- Create: `app/Services/ImportacaoDiscentesService.php`
- Create: `app/Http/Controllers/ImportacaoDiscenteController.php`
- Create: `resources/views/usuarios/importar.blade.php`, `resources/views/usuarios/importar-previa.blade.php`, `resources/views/usuarios/importar-resultado.blade.php`
- Modify: `routes/web.php`, `resources/views/usuarios/index.blade.php` (botão "Importar discentes")
- Test: `tests/Feature/ImportacaoDiscentesTest.php`

**Interfaces:**
- Consumes: `ContextoSecretaria`, `ProgramaCurso`, `LinhaPesquisa`, `PrimeiroAcessoUsuarioMail`.
- Produces:
  - `CadastroDiscenteService::criar(Programa $programa, array $dados): array` → `[User $usuario, string $senhaTemporaria]`; `$dados` = `nome, email, matricula, programa_curso_id, nivel, data_ingresso (Y-m-d), lattes_url, linha_pesquisa_id|null, telefone|null`.
  - `ImportacaoDiscentesService::analisar(string $conteudo, Programa $programa): array` → `['erro_arquivo' => ?string, 'linhas' => list<array{numero:int, dados:array, status:'valida'|'erro'|'existente', erros:list<string>}>]`.
  - Rotas `usuarios.importar` (GET `/usuarios/importar-discentes`), `usuarios.importar.analisar` (POST `/usuarios/importar-discentes`), `usuarios.importar.confirmar` (POST `/usuarios/importar-discentes/confirmar`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Mail\PrimeiroAcessoUsuarioMail;
use App\Models\AreaConcentracao;
use App\Models\LinhaPesquisa;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ImportacaoDiscentesTest extends TestCase
{
    use RefreshDatabase;

    private const CABECALHO = 'nome;email;matricula;curso;data_ingresso;lattes;linha_pesquisa;telefone';

    private function contexto(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');
        $area = AreaConcentracao::create(['programa_id' => $programa->id, 'nome' => 'Área', 'ativo' => true]);
        LinhaPesquisa::create(['area_concentracao_id' => $area->id, 'nome' => 'Modelagem Matemática', 'ativo' => true]);

        return [$sec, $programa];
    }

    private function csv(string $conteudo, string $nome = 'discentes.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, $conteudo);
    }

    private function linha(string $email, string $matricula, string $extra = ''): string
    {
        return "Ana Souza;{$email};{$matricula};mestrado_academico;1/3/2024;http://lattes.cnpq.br/1234567890123456;{$extra};";
    }

    public function test_previa_classifica_linhas(): void
    {
        [$sec] = $this->contexto();
        User::factory()->create(['email' => 'ja@exemplo.com']);
        $conteudo = implode("\n", [
            self::CABECALHO,
            $this->linha('ok@exemplo.com', '1', 'modelagem matematica'),
            $this->linha('ja@exemplo.com', '2'),
            'Sem Lattes;x@exemplo.com;3;mestrado_academico;01/03/2024;;;',
            'Curso errado;y@exemplo.com;4;doutorado_academico;01/03/2024;http://lattes.cnpq.br/1;;',
            $this->linha('OK@exemplo.com ', '5'),
            '',
            '',
        ]);

        $resposta = $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)]);

        $resposta->assertOk()
            ->assertSee('1 válida', false)
            ->assertSee('O Lattes é obrigatório')
            ->assertSee('Curso inexistente ou inativo')
            ->assertSee('já cadastrado');
        $this->assertDatabaseMissing('users', ['email' => 'ok@exemplo.com']);
    }

    public function test_aceita_virgula_bom_e_latin1(): void
    {
        [$sec] = $this->contexto();
        $conteudo = "\xEF\xBB\xBF" . "nome,email,matricula,curso,data_ingresso,lattes\n"
            . "Jos\xE9 Ara\xFAjo,jose@exemplo.com,10,mestrado_academico,01/03/2024,http://lattes.cnpq.br/99\n";

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)])
            ->assertOk()
            ->assertSee('José Araújo')
            ->assertSee('1 válida', false);
    }

    public function test_confirmar_cria_discentes_e_enfileira_emails_uma_unica_vez(): void
    {
        Mail::fake();
        [$sec, $programa] = $this->contexto();
        $conteudo = implode("\n", [self::CABECALHO, $this->linha('a@exemplo.com', '1'), $this->linha('b@exemplo.com', '2')]);

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)]);
        $token = session('importacao_discentes_token');

        $this->actingAs($sec)->post(route('usuarios.importar.confirmar'), ['token' => $token])->assertOk()->assertSee('2 importado', false);
        $this->actingAs($sec)->post(route('usuarios.importar.confirmar'), ['token' => $token])
            ->assertRedirect(route('usuarios.importar'))->assertSessionHasErrors('arquivo');

        $this->assertSame(2, User::where('categoria', 'discente')->count());
        $this->assertDatabaseHas('discente_programa_vinculos', ['programa_id' => $programa->id, 'matricula' => '1', 'nivel' => 'mestrado']);
        $this->assertDatabaseHas('discente_profiles', ['lattes_url' => 'http://lattes.cnpq.br/1234567890123456', 'orcid_url' => null]);
        Mail::assertQueued(PrimeiroAcessoUsuarioMail::class, 2);
    }

    public function test_matricula_repetida_no_arquivo_ou_no_programa_e_erro(): void
    {
        [$sec] = $this->contexto();
        $conteudo = implode("\n", [self::CABECALHO, $this->linha('a@exemplo.com', '7'), $this->linha('b@exemplo.com', '7')]);

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)])
            ->assertSee('Matrícula repetida');
    }

    public function test_arquivo_sem_coluna_obrigatoria_e_recusado(): void
    {
        [$sec] = $this->contexto();

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), [
            'arquivo' => $this->csv("nome;email\nAna;a@exemplo.com\n"),
        ])->assertRedirect(route('usuarios.importar'))->assertSessionHasErrors('arquivo');
    }

    public function test_token_de_outra_secretaria_e_recusado(): void
    {
        [$sec] = $this->contexto();
        $conteudo = implode("\n", [self::CABECALHO, $this->linha('a@exemplo.com', '1')]);
        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)]);
        $token = session('importacao_discentes_token');

        $outra = User::factory()->secretaria()->create();
        app(ContextoSecretaria::class)->vincular($outra, Programa::create(['nome' => 'Outro']));

        $this->actingAs($outra)->post(route('usuarios.importar.confirmar'), ['token' => $token])
            ->assertSessionHasErrors('arquivo');
        $this->assertDatabaseMissing('users', ['email' => 'a@exemplo.com']);
    }

    public function test_discente_recebe_403(): void
    {
        $this->actingAs(User::factory()->discente()->create())
            ->get(route('usuarios.importar'))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run** → FAIL (routes not defined).

- [ ] **Step 3: `CadastroDiscenteService`**

```php
<?php

namespace App\Services;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CadastroDiscenteService
{
    /** @return array{0: User, 1: string} */
    public function criar(Programa $programa, array $dados): array
    {
        $senha = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

        $usuario = User::create([
            'name' => $dados['nome'],
            'email' => $dados['email'],
            'password' => Hash::make($senha),
            'categoria' => 'discente',
            'primeiro_acesso' => true,
            'ativo' => true,
            'cadastro_status' => 'completo',
        ]);

        $profile = DiscenteProfile::create([
            'user_id' => $usuario->id,
            'lattes_url' => $dados['lattes_url'],
            'lattes_verificado' => false,
            'orcid_verificado' => false,
            'telefone' => $dados['telefone'] ?? null,
            'pais' => 'Brasil',
            'ativo' => true,
        ]);

        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'programa_curso_id' => $dados['programa_curso_id'],
            'discente_profile_id' => $profile->id,
            'linha_pesquisa_id' => $dados['linha_pesquisa_id'] ?? null,
            'matricula' => $dados['matricula'],
            'nivel' => $dados['nivel'],
            'status' => 'ativo',
            'data_ingresso' => $dados['data_ingresso'],
            'ativo' => true,
        ]);

        TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'linha_pesquisa_id' => $vinculo->linha_pesquisa_id,
            'nivel' => $vinculo->nivel,
            'status' => 'em_andamento',
            'data_inicio' => $vinculo->data_ingresso,
        ]);

        return [$usuario, $senha];
    }
}
```

- [ ] **Step 4: `ImportacaoDiscentesService`**

```php
<?php

namespace App\Services;

use App\Models\DiscenteProgramaVinculo;
use App\Models\LinhaPesquisa;
use App\Models\Programa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportacaoDiscentesService
{
    public const OBRIGATORIAS = ['nome', 'email', 'matricula', 'curso', 'data_ingresso', 'lattes'];
    public const OPCIONAIS = ['linha_pesquisa', 'telefone'];
    public const MAX_LINHAS = 1000;

    public function analisar(string $conteudo, Programa $programa): array
    {
        $conteudo = $this->paraUtf8($conteudo);
        $registros = array_values(array_filter(
            preg_split('/\r\n|\n|\r/', $conteudo),
            fn ($l) => trim(str_replace([';', ','], '', $l)) !== ''
        ));

        if (count($registros) < 2) {
            return ['erro_arquivo' => 'O arquivo não tem linhas de discentes.', 'linhas' => []];
        }

        $separador = substr_count($registros[0], ';') >= substr_count($registros[0], ',') ? ';' : ',';
        $cabecalho = array_map(fn ($c) => Str::of($c)->ascii()->lower()->trim()->replace(' ', '_')->value(),
            str_getcsv($registros[0], $separador));
        $faltando = array_diff(self::OBRIGATORIAS, $cabecalho);
        if ($faltando) {
            return ['erro_arquivo' => 'Colunas obrigatórias ausentes: ' . implode(', ', $faltando) . '.', 'linhas' => []];
        }
        if (count($registros) - 1 > self::MAX_LINHAS) {
            return ['erro_arquivo' => 'O arquivo excede ' . self::MAX_LINHAS . ' linhas de discentes.', 'linhas' => []];
        }

        $cursos = $programa->cursos()->where('ativo', true)->get()->keyBy(fn ($c) => $c->identificador);
        $linhasPesquisa = LinhaPesquisa::where('ativo', true)
            ->whereHas('areaConcentracao', fn ($q) => $q->where('programa_id', $programa->id))
            ->get()
            ->keyBy(fn ($l) => $this->chave($l->nome));
        $emailsExistentes = User::pluck('email')->map(fn ($e) => mb_strtolower($e))->flip();
        $matriculasExistentes = DiscenteProgramaVinculo::where('programa_id', $programa->id)
            ->whereNotNull('matricula')->pluck('matricula')->flip();

        $vistosEmail = [];
        $vistosMatricula = [];
        $linhas = [];

        foreach (array_slice($registros, 1) as $i => $registro) {
            $valores = array_map('trim', str_getcsv($registro, $separador));
            $bruto = [];
            foreach ($cabecalho as $pos => $coluna) {
                $bruto[$coluna] = $valores[$pos] ?? '';
            }

            $dados = [
                'nome' => $bruto['nome'],
                'email' => mb_strtolower($bruto['email']),
                'matricula' => $bruto['matricula'],
                'curso' => mb_strtolower($bruto['curso']),
                'data_ingresso_original' => $bruto['data_ingresso'],
                'lattes_url' => $bruto['lattes'],
                'linha_pesquisa' => $bruto['linha_pesquisa'] ?? '',
                'telefone' => ($bruto['telefone'] ?? '') ?: null,
            ];
            $erros = [];

            if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 255) {
                $erros[] = 'Nome obrigatório (até 255 caracteres).';
            }
            if (! filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'E-mail inválido.';
            }
            if ($dados['matricula'] === '') {
                $erros[] = 'Matrícula obrigatória.';
            } elseif (isset($matriculasExistentes[$dados['matricula']]) || isset($vistosMatricula[$dados['matricula']])) {
                $erros[] = 'Matrícula repetida no programa ou no arquivo.';
            }
            $curso = $cursos->get($dados['curso']);
            if (! $curso) {
                $erros[] = 'Curso inexistente ou inativo: use ' . $cursos->keys()->implode(', ') . '.';
            }
            $data = $this->data($dados['data_ingresso_original']);
            if (! $data) {
                $erros[] = 'Data de ingresso inválida (use dd/mm/aaaa, não futura).';
            }
            if ($dados['lattes_url'] === '') {
                $erros[] = 'O Lattes é obrigatório.';
            } elseif (! preg_match('/^https?:\/\/lattes\.cnpq\.br\/[0-9]+$/', $dados['lattes_url'])) {
                $erros[] = 'Lattes inválido (ex.: http://lattes.cnpq.br/1234567890123456).';
            }
            $linhaPesquisa = null;
            if ($dados['linha_pesquisa'] !== '') {
                $linhaPesquisa = $linhasPesquisa->get($this->chave($dados['linha_pesquisa']));
                if (! $linhaPesquisa) {
                    $erros[] = 'Linha de pesquisa não encontrada no programa.';
                }
            }
            if ($dados['telefone'] && mb_strlen($dados['telefone']) > 30) {
                $erros[] = 'Telefone com mais de 30 caracteres.';
            }

            $existente = isset($emailsExistentes[$dados['email']]) || isset($vistosEmail[$dados['email']]);
            $vistosEmail[$dados['email']] = true;
            if ($dados['matricula'] !== '') {
                $vistosMatricula[$dados['matricula']] = true;
            }

            $dados['programa_curso_id'] = $curso?->id;
            $dados['nivel'] = $curso?->nivel;
            $dados['data_ingresso'] = $data?->format('Y-m-d');
            $dados['linha_pesquisa_id'] = $linhaPesquisa?->id;

            $linhas[] = [
                'numero' => $i + 2,
                'dados' => $dados,
                'status' => $existente ? 'existente' : ($erros ? 'erro' : 'valida'),
                'erros' => $existente ? ['E-mail já cadastrado ou repetido no arquivo.'] : $erros,
            ];
        }

        return ['erro_arquivo' => null, 'linhas' => $linhas];
    }

    private function paraUtf8(string $conteudo): string
    {
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }

        return mb_check_encoding($conteudo, 'UTF-8') ? $conteudo : mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
    }

    private function chave(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->value();
    }

    private function data(string $valor): ?Carbon
    {
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }
        $data = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();

        return $data->isFuture() ? null : $data;
    }
}
```

- [ ] **Step 5: `ImportacaoDiscenteController`**

```php
<?php

namespace App\Http\Controllers;

use App\Mail\PrimeiroAcessoUsuarioMail;
use App\Services\CadastroDiscenteService;
use App\Services\ContextoSecretaria;
use App\Services\ImportacaoDiscentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ImportacaoDiscenteController extends Controller
{
    public function __construct(
        private readonly ContextoSecretaria $contexto,
        private readonly ImportacaoDiscentesService $importacao,
        private readonly CadastroDiscenteService $cadastro,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function create()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma(auth()->user());
        $cursos = $programa->cursos()->where('ativo', true)->get();

        return view('usuarios.importar', compact('programa', 'cursos'));
    }

    public function analisar(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());
        $request->validate(['arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'arquivo.required' => 'Selecione o arquivo CSV.',
            'arquivo.mimes' => 'Envie um arquivo .csv.',
            'arquivo.max' => 'O arquivo deve ter no máximo 2 MB.',
        ]);

        $resultado = $this->importacao->analisar($request->file('arquivo')->get(), $programa);
        if ($resultado['erro_arquivo']) {
            return redirect()->route('usuarios.importar')->withErrors(['arquivo' => $resultado['erro_arquivo']]);
        }

        $token = (string) Str::uuid();
        Cache::put("importacao-discentes:$token", [
            'user_id' => $request->user()->id,
            'programa_id' => $programa->id,
            'conteudo' => $request->file('arquivo')->get(),
        ], now()->addMinutes(30));
        session(['importacao_discentes_token' => $token]);

        $linhas = collect($resultado['linhas']);

        return view('usuarios.importar-previa', compact('linhas', 'token'));
    }

    public function confirmar(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());
        $token = (string) $request->input('token');
        $previa = Cache::get("importacao-discentes:$token");

        if (! $previa || $previa['user_id'] !== $request->user()->id || $previa['programa_id'] !== $programa->id) {
            return redirect()->route('usuarios.importar')
                ->withErrors(['arquivo' => 'A prévia expirou ou não pertence a você. Envie o arquivo novamente.']);
        }
        Cache::forget("importacao-discentes:$token");

        // Revalida: um e-mail pode ter sido cadastrado depois da prévia.
        $linhas = collect($this->importacao->analisar($previa['conteudo'], $programa)['linhas']);

        $criados = DB::transaction(fn () => $linhas->where('status', 'valida')
            ->map(fn ($linha) => $this->cadastro->criar($programa, $linha['dados']))
            ->values());

        foreach ($criados as [$usuario, $senha]) {
            Mail::to($usuario->email)->queue(new PrimeiroAcessoUsuarioMail($usuario, $senha, route('login')));
        }

        $naoImportadas = $linhas->where('status', '!=', 'valida')->values();

        return view('usuarios.importar-resultado', [
            'importados' => $criados->count(),
            'naoImportadas' => $naoImportadas,
        ]);
    }
}
```

(O cache guarda o conteúdo do arquivo, e a confirmação reanalisa — assim a revalidação usa as mesmas regras. `Cache::forget` antes de criar impede dupla importação.)

- [ ] **Step 6: Rotas** (grupo `auth, verified`, **antes** de `/usuarios/{usuario}/edit`):

```php
    Route::get('/usuarios/importar-discentes', [ImportacaoDiscenteController::class, 'create'])->name('usuarios.importar');
    Route::post('/usuarios/importar-discentes', [ImportacaoDiscenteController::class, 'analisar'])->name('usuarios.importar.analisar');
    Route::post('/usuarios/importar-discentes/confirmar', [ImportacaoDiscenteController::class, 'confirmar'])->name('usuarios.importar.confirmar');
```

- [ ] **Step 7: Views** (padrão Bootstrap do projeto)
- `usuarios/importar.blade.php`: form `enctype="multipart/form-data"` com input `arquivo` (`accept=".csv,text/csv"`); quadro "Formato do arquivo" com a tabela de colunas (obrigatória/opcional/regra) da spec, os identificadores de curso aceitos (`@foreach ($cursos as $c) <code>{{ $c->identificador }}</code> ({{ $c->nome }})`) e um exemplo em `<pre>`:
  `nome;email;matricula;curso;data_ingresso;lattes;linha_pesquisa;telefone` / `Ana Souza;ana@exemplo.com;2026001;mestrado_academico;01/03/2026;http://lattes.cnpq.br/1234567890123456;Modelagem Matemática;(71) 99999-0000`. Sem link de download. Nota: "ORCID não é importado; o discente informa no próprio cadastro."
- `usuarios/importar-previa.blade.php`: contadores `{{ $linhas->where('status','valida')->count() }} válida(s)`, `... com erro`, `... já existente(s)`; tabela (nº da linha, nome, e-mail, matrícula, curso, status com badge verde/vermelho/cinza, lista de `erros`); form POST `usuarios.importar.confirmar` com `<input type="hidden" name="token" value="{{ $token }}">`, botão "Confirmar importação" desabilitado se não houver válidas e `onclick="this.disabled=true;this.form.submit()"`; link "Enviar outro arquivo".
- `usuarios/importar-resultado.blade.php`: `{{ $importados }} importado(s)`, texto "Os e-mails de primeiro acesso foram enfileirados."; tabela das `$naoImportadas` com motivos para correção.
- `usuarios/index.blade.php`: botão "Importar discentes" → `route('usuarios.importar')` ao lado de "Novo usuário".

- [ ] **Step 8: Run** — `php artisan test --compact tests/Feature/ImportacaoDiscentesTest.php` → PASS; suíte → PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/CadastroDiscenteService.php app/Services/ImportacaoDiscentesService.php app/Http/Controllers/ImportacaoDiscenteController.php resources/views/usuarios routes/web.php tests/Feature/ImportacaoDiscentesTest.php
git commit -m "feat: importar discentes por csv com previa"
```

---

### Task 11: Isolamento das telas de cadastro da Secretaria

**Files:**
- Modify: `app/Http/Controllers/UsuarioController.php` (`index`, `edit`, `update`, `reenviarAcesso`)
- Modify: `app/Http/Controllers/AreaConcentracaoController.php`, `app/Http/Controllers/LinhaPesquisaController.php`
- Modify: `app/Http/Controllers/BancaController.php` (`index`, `create`, `store`, `edit`, `update`, `destroy`, `reabrir`)
- Modify: `app/Http/Controllers/OrientacaoController.php`, `app/Http/Controllers/BaremaController.php`, `app/Http/Controllers/CriterioCpaController.php`, `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/IsolamentoProgramaTest.php`

**Interfaces:**
- Consumes: `ContextoSecretaria::exigirPrograma()`, `garantirPrograma()`.

- [ ] **Step 1: Write the failing test** — fixture com duas Secretarias (A e B), cada uma com seu programa, um discente e uma área/linha/banca em cada; testes:

```php
    public function test_lista_de_usuarios_mostra_apenas_o_programa_da_secretaria(): void
    {
        [$secA, $discenteA, , $discenteB] = $this->doisProgramas();

        $this->actingAs($secA)->get(route('usuarios.index'))
            ->assertSee($discenteA->email)
            ->assertDontSee($discenteB->email);
    }

    public function test_secretaria_nao_edita_usuario_de_outro_programa(): void
    {
        [$secA, , , $discenteB] = $this->doisProgramas();

        $this->actingAs($secA)->get(route('usuarios.edit', $discenteB))->assertForbidden();
    }

    public function test_secretaria_nao_acessa_areas_de_outro_programa(): void
    {
        [$secA, , $programaB] = $this->doisProgramas();

        $this->actingAs($secA)->get(route('programas.areas.index', $programaB))->assertForbidden();
    }

    public function test_bancas_listadas_sao_do_programa_da_secretaria(): void
    {
        [$secA, $discenteA, , $discenteB] = $this->doisProgramas();

        $this->actingAs($secA)->get(route('bancas.index'))
            ->assertSee($discenteA->name)
            ->assertDontSee($discenteB->name);
    }

    public function test_dashboard_conta_apenas_o_programa(): void
    {
        [$secA] = $this->doisProgramas();

        $this->actingAs($secA)->get(route('dashboard'))->assertViewHas('totalDiscentes', 1);
    }
```

  `doisProgramas()` retorna `[$secA, $discenteA, $programaB, $discenteB]`, criando para cada programa: `Programa`, vínculo da Secretaria via `ContextoSecretaria::vincular`, `DiscenteProfile` + `DiscenteProgramaVinculo` + `TrabalhoAcademico`, `AreaConcentracao`, e usando nomes distintos (`'Discente A'`, `'Discente B'`). Adicione um teste 403 para `areas-concentracao.edit`, `linhas-pesquisa.edit`, `bancas.edit` e `orientacoes` (nomes de rota exatos via `php artisan route:list --path=orientacoes`) de recursos do programa B.

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Implement** — injetar `ContextoSecretaria $contexto` em cada controller e aplicar:
- `UsuarioController::index`: `$programa = $this->contexto->exigirPrograma(auth()->user());` e filtrar:

```php
        $usuarios = User::with([...mesmos relacionamentos...])
            ->where(function ($q) use ($programa) {
                $q->whereHas('programaUsuarios', fn ($p) => $p->where('programa_id', $programa->id))
                    ->orWhereHas('docenteProfile.vinculosPrograma', fn ($p) => $p->where('programa_id', $programa->id))
                    ->orWhereHas('discenteProfile.vinculosPrograma', fn ($p) => $p->where('programa_id', $programa->id));
            })
            ->orderBy('name')
            ->get();
```

  `edit`/`update`/`reenviarAcesso`: novo método privado `garantirUsuarioDoPrograma(User $usuario)` que aplica a mesma condição com `->whereKey($usuario->id)->exists()` e `abort_unless(..., 403)`. Substituir o `programaAtual()` privado por `$this->contexto->programa(auth()->user())`.
- `AreaConcentracaoController`: `index/create/store(Programa $programa)` → `$this->contexto->garantirPrograma(auth()->user(), $programa->id)`; `edit/update/destroy(AreaConcentracao $a)` → `garantirPrograma(..., $a->programa_id)`.
- `LinhaPesquisaController`: rotas com `AreaConcentracao` → `garantirPrograma(..., $area->programa_id)`; com `LinhaPesquisa` → `garantirPrograma(..., $linha->areaConcentracao->programa_id)`.
- `BancaController::index`: `->where('programa_id', $programa->id)` em `TrabalhoAcademico`; `create`: listar só trabalhos do programa; `store`: `garantirPrograma(..., $trabalho->programa_id)`; `edit/update/destroy/reabrir`: `garantirPrograma(..., $banca->trabalhoAcademico->programa_id)`.
- `OrientacaoController`: mesma regra via `programa_id` do trabalho/vínculo (ler o arquivo e aplicar em cada ação que recebe modelo por rota).
- `BaremaController::index` e `CriterioCpaController::index`: trocar `->when($programa, ...)` por `exigirPrograma` + `where('programa_id', ...)` (sem programa → 403, nunca "todos"); ações com `Barema $barema`/`CriterioCpa $criterioCpa` → `garantirPrograma(..., $model->programa_id)` (em `BaremaSecaoController`/`BaremaItemController` usar `$secao->barema->programa_id` / `$item->secao->barema->programa_id`).
- `DashboardController` (ramo `sec`): `totalDiscentes` = `DiscenteProgramaVinculo::where('programa_id', $programa->id)->where('ativo', true)->count()`; `totalDocentes` = `DocenteProgramaVinculo::where('programa_id', $programa->id)->where('ativo', true)->count()`; revisar os demais números do painel da Secretaria com o mesmo filtro.

- [ ] **Step 4: Run** → PASS; suíte completa → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers tests/Feature/IsolamentoProgramaTest.php
git commit -m "fix: isolar telas da secretaria pelo seu programa"
```

---

### Task 12: Isolamento dos módulos analíticos

**Files:**
- Modify: `app/Http/Controllers/RelatorioController.php`, `app/Http/Controllers/MabacController.php`, `app/Http/Controllers/SwaraSessaoController.php`, `app/Http/Controllers/BibliometriaController.php`, `app/Http/Controllers/AutoavaliacaoController.php`, `app/Http/Controllers/PerguntaEstrategicaController.php`, `app/Http/Controllers/MembroExternoController.php` (conferir)
- Test: `tests/Feature/IsolamentoAnaliticoTest.php`

- [ ] **Step 1: Mapear** — para cada controller, listar as queries de leitura da Secretaria: `grep -n "::query()\|::with(\|::where(\|::all()\|::orderBy(" app/Http/Controllers/{Relatorio,Mabac,SwaraSessao,Bibliometria,Autoavaliacao,PerguntaEstrategica,MembroExterno}Controller.php`. Classifique cada uma: filtra por programa (ok) / não filtra (corrigir) / não é dado por programa (ignorar).

- [ ] **Step 2: Write the failing tests** — com o fixture `doisProgramas()` (copie-o da Task 11 para este arquivo), um teste por tela de listagem, no formato:

```php
    public function test_relatorios_mostram_apenas_o_programa(): void
    {
        [$secA, $discenteA, , $discenteB] = $this->doisProgramas();

        $this->actingAs($secA)->get(route('relatorios.index'))
            ->assertOk()
            ->assertDontSee($discenteB->name);
    }
```

  e um teste 403 por ação que recebe modelo de outro programa por rota (`MabacAnalise`, `SwaraSessao`, período de autoavaliação, pergunta estratégica, convite de membro externo). Use `php artisan route:list --path=<prefixo>` para os nomes exatos.

- [ ] **Step 3: Implement** — aplicar o padrão: `$programa = $this->contexto->exigirPrograma(auth()->user());` e `->where('programa_id', $programa->id)` (ou `->whereHas('trabalhoAcademico', fn ($q) => $q->where('programa_id', $programa->id))` para bancas/avaliações); ações com modelo por rota → `garantirPrograma(...)`. Onde o modelo não tiver `programa_id` e nenhuma relação que leve a ele, registrar no commit como limitação conhecida (não criar coluna nesta tarefa).

- [ ] **Step 4: Run** → PASS; suíte completa → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers tests/Feature/IsolamentoAnaliticoTest.php
git commit -m "fix: isolar modulos analiticos pelo programa da secretaria"
```

---

### Task 13: Documentação e verificação final

**Files:**
- Modify: `docs/superpowers/operacao-fluxo-secretaria.md`, `CLAUDE.md`, `AGENTS.md` (seção Módulos/Pontos de atenção)

- [ ] **Step 1: Documentação**
- `operacao-fluxo-secretaria.md`, seção "Rotina da Secretaria": nova ordem — 1) cadastrar instituição (com logotipo) 2) cadastrar o programa (um por Secretaria) 3) cadastrar cursos e regras de banca 4) áreas/linhas 5) docentes e discentes (individual ou "Importar discentes") 6) orientações … ; acrescentar em "Rotas de operação": `instituicoes.create`, `programa-cursos.index`, `usuarios.importar`; nota: "ORCID do discente é obrigatório para cadastrar/homologar banca e enviar o trabalho; o discente informa em Meu perfil."
- `CLAUDE.md` e `AGENTS.md`: em "Pontos de atenção", acrescentar: "Cada Secretaria administra um único programa (`ContextoSecretaria`); todas as telas da Secretaria filtram por ele." e "Regras de composição de banca vêm do curso do discente (`curso_regras_banca`, via `VerificacaoBancaService`) — não há mais regra fixa por nível."

- [ ] **Step 2: Verificação completa**

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan route:list --path=programa
php artisan route:list --path=instituic
php artisan route:list --path=usuarios
php artisan test --compact
npm run build
git diff --check
```

Expected: todos os testes passam (85 de base + novos); build sem erro; `git diff --check` sem saída.

- [ ] **Step 3: Migração na base de desenvolvimento** — `php artisan migrate` (NÃO `migrate:fresh`); conferir com `php artisan tinker --execute="echo App\Models\ProgramaCurso::count();"`.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/operacao-fluxo-secretaria.md CLAUDE.md AGENTS.md
git commit -m "docs: atualizar operacao da secretaria com cursos e importacao"
```
