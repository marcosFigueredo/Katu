<?php

namespace App\Http\Controllers;

use App\Models\Bolsa;
use App\Models\DiscenteProgramaVinculo;
use App\Models\User;
use Illuminate\Http\Request;

/** Bolsas do estudante, registradas pela Secretaria na edição do usuário. */
class BolsaController extends Controller
{
    public function store(Request $request, User $usuario)
    {
        $vinculo = $this->vinculoDoDiscente($usuario);
        $dados = $request->validate(Bolsa::regras(), Bolsa::mensagens());

        $vinculo->bolsas()->create($this->normalizar($dados) + ['registrado_por' => auth()->id()]);

        return $this->voltar($usuario, 'Bolsa registrada.');
    }

    public function update(Request $request, User $usuario, Bolsa $bolsa)
    {
        $vinculo = $this->vinculoDoDiscente($usuario);
        abort_unless($bolsa->discente_programa_vinculo_id === $vinculo->id, 404);

        $bolsa->update($this->normalizar($request->validate(Bolsa::regras(), Bolsa::mensagens())));

        return $this->voltar($usuario, 'Bolsa atualizada.');
    }

    public function destroy(User $usuario, Bolsa $bolsa)
    {
        $vinculo = $this->vinculoDoDiscente($usuario);
        abort_unless($bolsa->discente_programa_vinculo_id === $vinculo->id, 404);

        $bolsa->delete();

        return $this->voltar($usuario, 'Bolsa removida.');
    }

    private function normalizar(array $dados): array
    {
        if ($dados['agencia'] !== 'outra') {
            $dados['agencia_outra'] = null;
        }

        return $dados;
    }

    private function vinculoDoDiscente(User $usuario): DiscenteProgramaVinculo
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);

        $vinculo = DiscenteProgramaVinculo::where('discente_profile_id', $usuario->discenteProfile?->id)
            ->where('programa_id', $this->programaDaSecretaria()->id)
            ->first();
        abort_unless($vinculo, 403);

        return $vinculo;
    }

    private function voltar(User $usuario, string $mensagem)
    {
        return redirect()->to(route('usuarios.edit', $usuario).'#bolsas')->with('success', $mensagem);
    }
}
