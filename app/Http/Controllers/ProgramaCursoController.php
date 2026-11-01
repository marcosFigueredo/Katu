<?php

namespace App\Http\Controllers;

use App\Models\ProgramaCurso;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProgramaCursoController extends Controller
{
    public function __construct(
        private readonly ContextoSecretaria $contexto,
        private readonly CursoProgramaService $cursos,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma(auth()->user());
        $cursos = $programa->cursos()->with('regrasBanca')->get();

        return view('programas.cursos', compact('programa', 'cursos'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());

        $dados = $request->validate([
            'nivel' => ['required', Rule::in(ProgramaCurso::NIVEIS)],
            'modalidade' => ['required', Rule::in(ProgramaCurso::MODALIDADES),
                Rule::unique('programa_cursos')->where(fn ($q) => $q
                    ->where('programa_id', $programa->id)
                    ->where('nivel', $request->input('nivel')))],
            'prazo_qualificacao_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'prazo_defesa_meses' => ['nullable', 'integer', 'min:1', 'max:120', 'gte:prazo_qualificacao_meses'],
        ], ['modalidade.unique' => 'O programa já possui este curso.']);

        $this->cursos->criar($programa, $dados['nivel'], $dados['modalidade'],
            $dados['prazo_qualificacao_meses'] ?? null, $dados['prazo_defesa_meses'] ?? null);

        return redirect()->route('programa-cursos.index')->with('status', 'Curso cadastrado com regras de banca padrão.');
    }

    public function update(Request $request, ProgramaCurso $curso)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma($request->user(), $curso->programa_id);

        $dados = $request->validate([
            'prazo_qualificacao_meses' => ['required', 'integer', 'min:1', 'max:120'],
            'prazo_defesa_meses' => ['required', 'integer', 'min:1', 'max:120', 'gte:prazo_qualificacao_meses'],
            'min_publicacoes_defesa' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'ativo' => ['required', 'boolean'],
        ]);

        $curso->update($dados);
        $this->cursos->sincronizarOfertas($curso->programa);

        return redirect()->route('programa-cursos.index')->with('status', 'Curso atualizado.');
    }

    public function regras(Request $request, ProgramaCurso $curso)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma($request->user(), $curso->programa_id);

        $regras = [];
        foreach (ProgramaCurso::ETAPAS as $etapa) {
            $p = "regras.$etapa.";
            $regras[$p . 'min_avaliadores'] = ['required', 'integer', 'min:1', 'max:5'];
            $regras[$p . 'max_avaliadores'] = ['required', 'integer', 'max:5', 'gte:' . $p . 'min_avaliadores'];
            $regras[$p . 'min_internos'] = ['required', 'integer', 'min:0', 'max:5'];
            $regras[$p . 'min_externos_instituicao'] = ['required', 'integer', 'min:0', 'max:5'];
        }
        $validador = Validator::make($request->all(), $regras);
        $validador->after(function ($validador) use ($request) {
            foreach (ProgramaCurso::ETAPAS as $etapa) {
                $r = (array) $request->input("regras.$etapa", []);
                if ((int) ($r['min_internos'] ?? 0) + (int) ($r['min_externos_instituicao'] ?? 0) > (int) ($r['max_avaliadores'] ?? 0)) {
                    $validador->errors()->add("regras.$etapa.min_externos_instituicao", 'Internos + externos não podem exceder o máximo de avaliadores.');
                }
            }
        });
        $dados = $validador->validate();

        DB::transaction(function () use ($curso, $dados) {
            foreach (ProgramaCurso::ETAPAS as $etapa) {
                $curso->regrasBanca()->updateOrCreate(['etapa' => $etapa], $dados['regras'][$etapa]);
            }
        });

        return redirect()->route('programa-cursos.index')->with('status', 'Regras de banca atualizadas.');
    }
}
