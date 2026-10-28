<?php

namespace App\Http\Controllers;

use App\Models\Orientacao;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\OrientacaoService;
use Illuminate\Http\Request;

/** Orientação do discente, gerida pela Secretaria na edição do usuário. */
class OrientacaoDiscenteController extends Controller
{
    public function __construct(private readonly OrientacaoService $service)
    {
    }

    public function definir(Request $request, User $usuario)
    {
        $trabalho = $this->trabalhoDoDiscente($usuario);
        $dados = $request->validate(['orientador_vinculo_id' => ['required', 'integer']]);

        $this->service->definirOrientadorInicial($trabalho, (int) $dados['orientador_vinculo_id'], auth()->user());

        return $this->voltar($usuario, 'Orientador registrado.');
    }

    public function trocar(Request $request, User $usuario)
    {
        $trabalho = $this->trabalhoDoDiscente($usuario);
        $dados = $request->validate([
            'orientador_vinculo_id' => ['required', 'integer'],
            'processo_sei'          => ['required', 'string', 'max:50'],
            'justificativa'         => ['required', 'string', 'max:2000'],
        ], [
            'processo_sei.required'  => 'Informe o número do processo SEI que validou a troca.',
            'justificativa.required' => 'Informe a justificativa da troca.',
        ]);

        $this->service->trocarOrientador(
            $trabalho,
            (int) $dados['orientador_vinculo_id'],
            $dados['processo_sei'],
            $dados['justificativa'],
            auth()->user(),
        );

        return $this->voltar($usuario, 'Orientador trocado.');
    }

    public function incluirCoorientador(Request $request, User $usuario)
    {
        $trabalho = $this->trabalhoDoDiscente($usuario);
        $dados = $request->validate(['coorientador_vinculo_id' => ['required', 'integer']]);

        $this->service->incluirCoorientador($trabalho, (int) $dados['coorientador_vinculo_id'], auth()->user());

        return $this->voltar($usuario, 'Coorientador incluído.');
    }

    public function retirarCoorientador(User $usuario, Orientacao $orientacao)
    {
        $trabalho = $this->trabalhoDoDiscente($usuario);
        abort_unless($orientacao->trabalho_academico_id === $trabalho->id, 404);

        $this->service->retirarCoorientador($orientacao, auth()->user());

        return $this->voltar($usuario, 'Coorientador retirado.');
    }

    private function trabalhoDoDiscente(User $usuario): TrabalhoAcademico
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);

        $programaId = $this->programaDaSecretaria()->id;

        // Prefere o trabalho no programa da Secretaria; um trabalho de outro programa cai no 403 abaixo.
        $trabalhos = TrabalhoAcademico::whereHas('discenteVinculo', fn ($q) => $q->where('discente_profile_id', $usuario->discenteProfile?->id))
            ->get();
        $trabalho = $trabalhos->firstWhere('programa_id', $programaId) ?? $trabalhos->first();

        abort_unless($trabalho, 404);
        $this->garantirProgramaDaSecretaria($trabalho->programa_id);

        return $trabalho;
    }

    private function voltar(User $usuario, string $mensagem)
    {
        return redirect()->route('usuarios.edit', $usuario)->with('success', $mensagem);
    }
}
