<?php

namespace Database\Seeders;

use App\Models\AreaConcentracao;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\LinhaPesquisa;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\ProgramaUsuario;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosTesteSeeder extends Seeder
{
    private string $senha = 'Teste@123';

    public function run(): void
    {
        $programa = Programa::updateOrCreate(
            ['sigla' => 'PPGMSB'],
            [
                'nome' => 'Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas',
                'oferta_mestrado' => true,
                'oferta_doutorado' => true,
                'ativo' => true,
            ],
        );

        $area = AreaConcentracao::updateOrCreate(
            ['programa_id' => $programa->id, 'nome' => 'Modelagem e Simulação de Biossistemas'],
            ['ativo' => true],
        );

        $linha = LinhaPesquisa::updateOrCreate(
            ['area_concentracao_id' => $area->id, 'nome' => 'Sistemas e Processos em Biossistemas'],
            ['ativo' => true],
        );

        $secretaria = $this->usuario('Secretaria Teste', 'secretaria.teste@ppgmsb.local', 'sec');

        ProgramaUsuario::updateOrCreate(
            [
                'programa_id' => $programa->id,
                'user_id' => $secretaria->id,
                'categoria_no_programa' => 'secretaria',
            ],
            ['ativo' => true],
        );

        $orientador = $this->docente(
            $programa,
            'Orientador Teste',
            'orientador.teste@ppgmsb.local',
            'UNEB',
            'Universidade do Estado da Bahia',
            'interno',
            true,
            true,
        );

        $coorientador = $this->docente(
            $programa,
            'Coorientador Teste',
            'coorientador.teste@ppgmsb.local',
            'UNEB',
            'Universidade do Estado da Bahia',
            'interno',
            true,
            false,
        );

        $this->docente(
            $programa,
            'Avaliador Interno Teste',
            'avaliador.interno@ppgmsb.local',
            'UNEB',
            'Universidade do Estado da Bahia',
            'interno',
            false,
            true,
        );

        $this->docente(
            $programa,
            'Avaliador Externo Teste',
            'avaliador.externo@ppgmsb.local',
            'UFBA',
            'Universidade Federal da Bahia',
            'externo',
            false,
            true,
        );

        $discente = $this->discente($programa, $linha);
        $trabalho = TrabalhoAcademico::updateOrCreate(
            ['discente_programa_vinculo_id' => $discente->id],
            [
                'programa_id' => $programa->id,
                'linha_pesquisa_id' => $linha->id,
                'nivel' => 'mestrado',
                'status' => 'em_andamento',
                'data_inicio' => now()->subYear()->toDateString(),
            ],
        );

        Orientacao::updateOrCreate(
            [
                'trabalho_academico_id' => $trabalho->id,
                'docente_programa_vinculo_id' => $orientador->id,
            ],
            ['tipo' => 'orientador', 'ordem' => 1, 'ativo' => true],
        );

        Orientacao::updateOrCreate(
            [
                'trabalho_academico_id' => $trabalho->id,
                'docente_programa_vinculo_id' => $coorientador->id,
            ],
            ['tipo' => 'coorientador', 'ordem' => 1, 'ativo' => true],
        );
    }

    private function usuario(string $nome, string $email, string $categoria): User
    {
        $usuario = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nome,
                'password' => Hash::make($this->senha),
                'categoria' => $categoria,
                'primeiro_acesso' => false,
                'cadastro_status' => 'completo',
                'ativo' => true,
            ],
        );

        $usuario->forceFill([
            'email_verified_at' => now(),
            'password' => Hash::make($this->senha),
            'primeiro_acesso' => false,
            'cadastro_status' => 'completo',
            'ativo' => true,
        ])->save();

        return $usuario;
    }

    private function docente(
        Programa $programa,
        string $nome,
        string $email,
        string $instituicaoSigla,
        string $instituicaoOrigem,
        string $tipoVinculo,
        bool $podeOrientar,
        bool $podeAvaliar,
    ): DocenteProgramaVinculo {
        $usuario = $this->usuario($nome, $email, 'docente');

        $profile = DocenteProfile::updateOrCreate(
            ['user_id' => $usuario->id],
            [
                'titulacao' => 'Doutorado',
                'area_formacao' => 'Biossistemas',
                'instituicao_origem' => $instituicaoOrigem,
                'instituicao_sigla' => $instituicaoSigla,
                'telefone' => '+55 (71) 99999-0000',
                'telefone_whatsapp' => true,
                'ativo' => true,
            ],
        );

        return DocenteProgramaVinculo::updateOrCreate(
            [
                'programa_id' => $programa->id,
                'docente_profile_id' => $profile->id,
            ],
            [
                'tipo_vinculo' => $tipoVinculo,
                'categoria_docente' => $tipoVinculo === 'interno' ? 'permanente' : null,
                'pode_orientar' => $podeOrientar,
                'pode_avaliar' => $podeAvaliar,
                'ativo' => true,
            ],
        );
    }

    private function discente(Programa $programa, LinhaPesquisa $linha): DiscenteProgramaVinculo
    {
        $usuario = $this->usuario('Discente Teste', 'discente.teste@ppgmsb.local', 'discente');

        $profile = DiscenteProfile::updateOrCreate(
            ['user_id' => $usuario->id],
            [
                'formacao' => 'Graduação',
                'instituicao_formacao' => 'UNEB',
                'telefone' => '+55 (71) 98888-0000',
                'telefone_whatsapp' => true,
                'cidade' => 'Salvador',
                'estado' => 'BA',
                'pais' => 'Brasil',
                'ativo' => true,
            ],
        );

        return DiscenteProgramaVinculo::updateOrCreate(
            [
                'programa_id' => $programa->id,
                'discente_profile_id' => $profile->id,
            ],
            [
                'linha_pesquisa_id' => $linha->id,
                'matricula' => 'TESTE-2026-001',
                'nivel' => 'mestrado',
                'status' => 'ativo',
                'data_ingresso' => now()->subYear()->toDateString(),
                'ativo' => true,
            ],
        );
    }
}
