<?php

namespace App\Http\Controllers;

use App\Models\AreaCnpq;
use App\Models\SetorCnae;
use App\Models\TrabalhoAcademico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Dados do trabalho para o Lattes, informados pelo estudante (áreas do CNPq, setor CNAE, textos, visibilidade). */
class TrabalhoLattesController extends Controller
{
    public function edit()
    {
        $trabalho = $this->trabalho();
        $trabalho->load('areasCnpq.pai.pai', 'setorCnae');

        $selecionadas = $trabalho->areasCnpq->pluck('id')->all();
        // Itens desativados continuam visíveis só para quem já os escolheu.
        $areas = AreaCnpq::with('pai.pai')
            ->whereIn('nivel', ['area', 'subarea'])
            ->where(fn ($q) => $q->where('ativo', true)->orWhereIn('id', $selecionadas))
            ->orderBy('codigo')
            ->get();
        $setores = SetorCnae::where(fn ($q) => $q->where('ativo', true)->orWhere('id', $trabalho->setor_cnae_id))
            ->orderBy('codigo')
            ->get();

        $tituloEn = $trabalho->titulo_en ?? $this->tituloEnDoLatex($trabalho);

        return view('meu-trabalho.lattes', compact('trabalho', 'areas', 'setores', 'selecionadas', 'tituloEn'));
    }

    public function update(Request $request)
    {
        $trabalho = $this->trabalho();
        $selecionadas = $trabalho->areasCnpq()->pluck('areas_cnpq.id')->all();

        $dados = $request->validate([
            'areas' => ['nullable', 'array'],
            // Área ativa (ou já escolhida antes de ser desativada), no nível área ou subárea.
            'areas.*' => ['integer', Rule::exists('areas_cnpq', 'id')->where(fn ($q) => $q
                ->whereIn('nivel', ['area', 'subarea'])
                ->where(fn ($w) => $w->where('ativo', true)->orWhereIn('id', $selecionadas)))],
            'setor_cnae_id' => ['nullable', 'integer', Rule::exists('setores_cnae', 'id')->where(fn ($q) => $q
                ->where('ativo', true)->orWhere('id', $trabalho->setor_cnae_id))],
            'titulo_en' => ['nullable', 'string', 'max:500'],
            'outras_informacoes' => ['nullable', 'string', 'max:5000'],
            'outras_informacoes_en' => ['nullable', 'string', 'max:5000'],
            'visibilidade' => ['required', Rule::in(['publica', 'privada'])],
        ], [
            'areas.*.exists' => 'Escolha uma área ativa da lista.',
            'setor_cnae_id.exists' => 'Escolha um setor ativo da lista.',
        ]);

        DB::transaction(function () use ($trabalho, $dados) {
            $trabalho->update([
                'setor_cnae_id' => $dados['setor_cnae_id'] ?? null,
                'titulo_en' => $dados['titulo_en'] ?? null,
                'outras_informacoes' => $dados['outras_informacoes'] ?? null,
                'outras_informacoes_en' => $dados['outras_informacoes_en'] ?? null,
                'visibilidade' => $dados['visibilidade'],
            ]);
            $trabalho->areasCnpq()->sync($dados['areas'] ?? []);
        });

        return redirect()->route('meu-trabalho.lattes.edit')->with('success', 'Dados para o Lattes salvos.');
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

    /** Título em inglês extraído da submissão LaTeX mais recente que compilou. */
    private function tituloEnDoLatex(TrabalhoAcademico $trabalho): ?string
    {
        $elementos = $trabalho->submissoesLatex()->where('status', 'ok')->latest('id')->value('elementos');
        $elementos = is_string($elementos) ? json_decode($elementos, true) : $elementos;
        $titulo = $elementos['titulo_en'] ?? null;

        return is_string($titulo) && trim($titulo) !== '' ? trim($titulo) : null;
    }
}
