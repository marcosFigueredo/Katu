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
