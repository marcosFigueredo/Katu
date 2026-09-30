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
