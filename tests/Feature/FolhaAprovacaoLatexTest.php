<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\DocenteProgramaVinculo;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Database\Seeders\UsuariosTesteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolhaAprovacaoLatexTest extends TestCase
{
    use RefreshDatabase;

    public function test_orientador_baixa_folha_de_aprovacao_pdf(): void
    {
        $this->seed(UsuariosTesteSeeder::class);

        $trabalho = TrabalhoAcademico::firstOrFail();
        $orientador = User::where('email', 'orientador.teste@ppgmsb.local')->firstOrFail();
        $avaliador = User::where('email', 'avaliador.interno@ppgmsb.local')->firstOrFail();
        $avaliadorVinculo = DocenteProgramaVinculo::whereHas(
            'docenteProfile.user',
            fn ($query) => $query->where('email', $avaliador->email),
        )->firstOrFail();

        $banca = Banca::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
            'status' => 'finalizada',
            'resultado' => 'aprovado',
            'nota_media' => 9.0,
            'data_hora' => now(),
            'local' => 'Alagoinhas/BA',
        ]);

        BancaMembro::create([
            'banca_id' => $banca->id,
            'docente_programa_vinculo_id' => $avaliadorVinculo->id,
            'papel' => 'avaliador',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $response = $this->actingAs($orientador)->get(route('avaliacoes.folha-aprovacao', $banca));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }
}
