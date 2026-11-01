<?php

namespace App\Http\Controllers;

use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Services\ContextoSecretaria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramaController extends Controller
{
    public function __construct(private readonly ContextoSecretaria $contexto)
    {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    private function regras(?Programa $programa): array
    {
        $coordenadorPermanente = function (string $atributo, mixed $valor, Closure $falha) use ($programa) {
            $valido = $programa && DocenteProgramaVinculo::query()
                ->where('programa_id', $programa->id)
                ->where('categoria_docente', 'permanente')
                ->where('ativo', true)
                ->whereHas('docenteProfile', fn ($q) => $q->where('user_id', $valor))
                ->exists();
            if (! $valido) {
                $falha('Escolha um docente permanente ativo vinculado ao programa.');
            }
        };

        return [
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'instituicao_id' => ['required', 'exists:instituicoes,id'],
            'codigo_capes' => ['nullable', 'string', 'max:50'],
            'area_avaliacao' => ['nullable', 'string', 'max:255'],
            'conceito_capes' => ['nullable', 'integer', 'between:1,7'],
            'ano_inicio' => ['nullable', 'integer', 'digits:4', 'min:1950', 'max:' . now()->year],
            'coordenador_id' => ['nullable', 'integer', $coordenadorPermanente],
            'vice_coordenador_id' => ['nullable', 'integer', 'different:coordenador_id', $coordenadorPermanente],
            'portaria_coordenador' => ['nullable', 'string', 'max:500'],
            'max_coorientadores' => ['nullable', 'integer', 'min:0', 'max:5'],
            'email' => ['nullable', 'email', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'homepage_trabalhos' => ['nullable', 'url', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'descricao' => ['nullable', 'string'],
            'logo' => ['nullable', ...LogoController::REGRAS],
        ];
    }

    private const MENSAGENS = [
        'instituicao_id.required' => 'Selecione a instituição do programa.',
        'conceito_capes.between' => 'O conceito CAPES deve estar entre 1 e 7.',
        'max_coorientadores.max' => 'O máximo de coorientadores por discente é 5.',
        'vice_coordenador_id.different' => 'O vice-coordenador deve ser diferente do coordenador.',
    ];

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->programa(auth()->user());

        return $programa
            ? redirect()->route('programas.edit', $programa)
            : redirect()->route('programas.create');
    }

    public function create()
    {
        $this->autorizarSecretaria();
        if ($programa = $this->contexto->programa(auth()->user())) {
            return redirect()->route('programas.edit', $programa);
        }

        $instituicoes = Instituicao::where('ativo', true)->orderBy('nome')->get();

        return view('programas.create', compact('instituicoes'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        abort_if($this->contexto->programa($request->user()), 403, 'Esta Secretaria já administra um programa.');

        $dados = collect($request->validate($this->regras(null), self::MENSAGENS))
            ->except(['logo', 'coordenador_id', 'vice_coordenador_id'])
            ->reject(fn ($valor, $campo) => $campo === 'max_coorientadores' && $valor === null)
            ->all();
        if ($request->hasFile('logo')) {
            $dados['logo_path'] = LogoController::armazenar($request->file('logo'), null);
        }
        $dados['ativo'] = true;

        $programa = DB::transaction(function () use ($dados, $request) {
            $programa = Programa::create($dados);
            $this->contexto->vincular($request->user(), $programa);

            return $programa;
        });

        return redirect()->route('programas.edit', $programa)
            ->with('status', 'Programa cadastrado. Cadastre agora os cursos do programa.');
    }

    public function edit(Programa $programa)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma(auth()->user(), $programa->id);

        $instituicoes = Instituicao::where('ativo', true)->orderBy('nome')->get();
        $docentesPermanentes = DocenteProgramaVinculo::with('docenteProfile.user')
            ->where('programa_id', $programa->id)
            ->where('categoria_docente', 'permanente')
            ->where('ativo', true)
            ->get()
            ->map(fn ($v) => $v->docenteProfile?->user)
            ->filter()
            ->sortBy('name')
            ->values();
        $programa->load('cursos');

        return view('programas.edit', compact('programa', 'instituicoes', 'docentesPermanentes'));
    }

    public function update(Request $request, Programa $programa)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma($request->user(), $programa->id);

        $dados = collect($request->validate($this->regras($programa), self::MENSAGENS))->except('logo')
            ->reject(fn ($valor, $campo) => $campo === 'max_coorientadores' && $valor === null)
            ->all();
        if ($request->hasFile('logo')) {
            $dados['logo_path'] = LogoController::armazenar($request->file('logo'), $programa->logo_path);
        }

        $programa->update($dados);

        return redirect()->route('programas.edit', $programa)->with('status', 'Programa atualizado com sucesso.');
    }

    public function destroy(Programa $programa)
    {
        $this->autorizarSecretaria();
        $this->contexto->garantirPrograma(auth()->user(), $programa->id);

        return redirect()->route('programas.edit', $programa)
            ->with('status', 'O programa da Secretaria não pode ser removido.');
    }
}
