<?php

namespace App\Http\Controllers;

use App\Models\TrabalhoAcademico;
use App\Services\ProjetoPesquisaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** O estudante indica qual objetivo do projeto do orientador a sua pesquisa avança. */
class TrabalhoProjetoController extends Controller
{
    public const MENSAGEM_JANELA = 'O objetivo só pode ser alterado antes de enviar a qualificação, depois da qualificação finalizada (até enviar a defesa) ou quando o projeto do orientador muda.';

    public function __construct(private readonly ProjetoPesquisaService $service)
    {
    }

    public function edit()
    {
        $trabalho = $this->trabalho();
        $vigente = $trabalho->projetoVinculoVigente()?->load('projeto.docenteProgramaVinculo.docenteProfile.user');

        return view('meu-trabalho.projeto-pesquisa', [
            'vigente' => $vigente,
            'editavel' => $this->service->objetivoEditavel($trabalho),
            // Só pergunta se mantém quando o vínculo é novo e havia objetivo antes.
            'anterior' => $vigente && $vigente->objetivo === null ? $this->service->objetivoAnterior($trabalho) : null,
        ]);
    }

    public function update(Request $request)
    {
        $trabalho = $this->trabalho();
        $vigente = $trabalho->projetoVinculoVigente();
        if (! $vigente) {
            throw ValidationException::withMessages(['objetivo' => 'Seu orientador ainda não tem projeto de pesquisa ativo cadastrado.']);
        }
        if (! $this->service->objetivoEditavel($trabalho)) {
            throw ValidationException::withMessages(['objetivo' => self::MENSAGEM_JANELA]);
        }

        $anterior = $vigente->objetivo === null ? $this->service->objetivoAnterior($trabalho) : null;
        // Sem objetivo anterior não há o que manter: o texto é sempre exigido (ex.: formulário antigo em outra aba).
        $dados = $request->validate($anterior !== null ? [
            'mantem_objetivo' => ['required', 'boolean'],
            'objetivo' => ['exclude_if:mantem_objetivo,1', 'required', 'string', 'max:3000'],
        ] : [
            'objetivo' => ['required', 'string', 'max:3000'],
        ], [
            'mantem_objetivo.required' => 'Indique se mantém os objetivos que informou antes.',
            'objetivo.required' => 'Indique qual objetivo do projeto sua pesquisa avança.',
        ]);

        $mantem = $anterior !== null ? (bool) $dados['mantem_objetivo'] : null;
        $vigente->update([
            'objetivo' => $mantem ? $anterior : trim($dados['objetivo']),
            'mantem_objetivo' => $mantem ?? $vigente->mantem_objetivo,
            'objetivo_informado_em' => now(),
        ]);

        return redirect()->route('meu-trabalho.projeto.edit')->with('success', 'Objetivo registrado.');
    }

    private function trabalho(): TrabalhoAcademico
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'discente', 403);

        $trabalho = $user->discenteProfile?->vinculosPrograma()->with('trabalhoAcademico')->get()
            ->pluck('trabalhoAcademico')->filter()->first();
        abort_unless($trabalho, 404);

        return $trabalho;
    }
}
