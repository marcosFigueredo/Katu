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

    public function test_vincular_reativa_vinculo_inativo_existente(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'A']);
        ProgramaUsuario::create([
            'programa_id' => $programa->id, 'user_id' => $sec->id,
            'categoria_no_programa' => 'secretaria', 'ativo' => false,
        ]);

        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->assertTrue(app(ContextoSecretaria::class)->programa($sec)->is($programa));
    }

    public function test_migration_de_backfill_reativa_vinculo_inativo_sem_duplicar(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'A']);
        ProgramaUsuario::create([
            'programa_id' => $programa->id, 'user_id' => $sec->id,
            'categoria_no_programa' => 'secretaria', 'ativo' => false,
        ]);

        $migration = require database_path('migrations/2026_09_30_100400_vincular_programa_instituicao_secretarias.php');
        $migration->up();

        $this->assertSame(1, ProgramaUsuario::where('user_id', $sec->id)->count());
        $this->assertTrue(app(ContextoSecretaria::class)->programa($sec)->is($programa));
    }
}
