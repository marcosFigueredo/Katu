<?php

namespace Tests\Feature;

use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Database\Seeders\CenarioTesteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CenarioTesteSeederTest extends TestCase
{
    use RefreshDatabase;

    private function programaDoUsuario(): Programa
    {
        $instituicao = Instituicao::create(['nome' => 'Universidade do Estado da Bahia', 'sigla' => 'UNEB', 'ativo' => true]);

        return Programa::create([
            'nome' => 'Modelagem e Simulação de Biossistemas', 'sigla' => 'PPGMSB',
            'instituicao_id' => $instituicao->id, 'conceito_capes' => 3,
        ]);
    }

    public function test_cria_todos_os_perfis_com_a_mesma_senha_sem_alterar_o_programa(): void
    {
        $programa = $this->programaDoUsuario();

        $this->seed(CenarioTesteSeeder::class);
        $this->seed(CenarioTesteSeeder::class); // idempotente

        $emails = [
            'secretaria2@ppgmsb.test', 'permanente1@ppgmsb.test', 'permanente2@ppgmsb.test',
            'colaborador@ppgmsb.test', 'externo.ufba@ppgmsb.test', 'externo.usp@ppgmsb.test',
            'discente.completo@ppgmsb.test', 'discente.sem.orcid@ppgmsb.test', 'discente.sem.orientador@ppgmsb.test',
            'secretaria.outro@ppgmsb.test', 'discente.outro@ppgmsb.test',
        ];
        foreach ($emails as $email) {
            $usuario = User::where('email', $email)->firstOrFail();
            $this->assertTrue(Hash::check('uneb@123', $usuario->password), $email);
            $this->assertFalse((bool) $usuario->primeiro_acesso, $email);
        }
        $this->assertSame(count($emails), User::whereIn('email', $emails)->count());

        $programa->refresh();
        $this->assertSame('Modelagem e Simulação de Biossistemas', $programa->nome);
        $this->assertSame(3, $programa->conceito_capes);

        $completo = User::where('email', 'discente.completo@ppgmsb.test')->first();
        $this->assertNotNull($completo->discenteProfile->orcid_url);
        $this->assertNull(User::where('email', 'discente.sem.orcid@ppgmsb.test')->first()->discenteProfile->orcid_url);
        $this->assertSame(2, Orientacao::where('discente_programa_vinculo_id', $completo->discenteProfile->vinculosPrograma->first()->id)->count());

        $secOutro = User::where('email', 'secretaria.outro@ppgmsb.test')->first();
        $this->assertNotSame($programa->id, app(ContextoSecretaria::class)->programa($secOutro)->id);
        $this->assertSame($programa->id, app(ContextoSecretaria::class)->programa(User::where('email', 'secretaria2@ppgmsb.test')->first())->id);
    }
}
