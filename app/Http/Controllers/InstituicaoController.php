<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Services\ContextoSecretaria;
use Illuminate\Http\Request;

class InstituicaoController extends Controller
{
    public function __construct(private readonly ContextoSecretaria $contexto)
    {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    private function regras(bool $logoObrigatorio): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:50'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:20'],
            'site' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'logo' => [$logoObrigatorio ? 'required' : 'nullable', ...LogoController::REGRAS],
        ];
    }

    private const MENSAGENS = [
        'logo.required' => 'Envie o logotipo da instituição.',
        'logo.mimes' => 'O logotipo deve ser PNG, JPG ou WebP.',
        'logo.mimetypes' => 'O logotipo deve ser PNG, JPG ou WebP.',
        'logo.image' => 'O logotipo deve ser uma imagem PNG, JPG ou WebP.',
        'logo.max' => 'O logotipo deve ter no máximo 2 MB.',
    ];

    public function create()
    {
        $this->autorizarSecretaria();

        if ($this->contexto->programa(auth()->user())?->instituicao) {
            return redirect()->route('instituicao.edit');
        }

        return view('instituicoes.create');
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        abort_if($this->contexto->programa($request->user())?->instituicao_id, 403);

        $dados = $request->validate($this->regras(true), self::MENSAGENS);
        $dados['logo_path'] = LogoController::armazenar($request->file('logo'), null);
        $dados['ativo'] = true;
        unset($dados['logo']);

        $instituicao = Instituicao::create($dados);

        $programa = $this->contexto->programa($request->user());
        if ($programa) {
            $programa->update(['instituicao_id' => $instituicao->id]);

            return redirect()->route('instituicao.edit')->with('status', 'Instituição cadastrada.');
        }

        return redirect()->route('programas.create')
            ->with('status', 'Instituição cadastrada. Agora cadastre o programa.');
    }

    public function edit()
    {
        $this->autorizarSecretaria();
        $instituicao = $this->contexto->programa(auth()->user())?->instituicao;

        if (! $instituicao) {
            return redirect()->route('instituicoes.create');
        }

        return view('instituicoes.edit', compact('instituicao'));
    }

    public function update(Request $request)
    {
        $this->autorizarSecretaria();
        $instituicao = $this->contexto->exigirPrograma($request->user())->instituicao;
        abort_unless($instituicao, 404);

        $dados = $request->validate($this->regras(false), self::MENSAGENS);
        if ($request->hasFile('logo')) {
            $dados['logo_path'] = LogoController::armazenar($request->file('logo'), $instituicao->logo_path);
        }
        unset($dados['logo']);

        $instituicao->update($dados);

        return redirect()->route('instituicao.edit')->with('status', 'Instituição atualizada com sucesso.');
    }

    public function atualizarLogo(Request $request)
    {
        $this->autorizarSecretaria();
        $instituicao = $this->contexto->exigirPrograma($request->user())->instituicao;
        abort_unless($instituicao, 404);

        $request->validate(['logo' => ['required', ...LogoController::REGRAS]], self::MENSAGENS);

        $instituicao->update([
            'logo_path' => LogoController::armazenar($request->file('logo'), $instituicao->logo_path),
        ]);

        return redirect()->route('instituicao.edit')->with('status', 'Logotipo atualizado.');
    }
}
