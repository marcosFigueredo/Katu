<?php

namespace App\Http\Controllers;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\LinhaPesquisa;
use App\Models\Programa;
use App\Models\ProgramaUsuario;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Mail\PrimeiroAcessoUsuarioMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;


class UsuarioController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    private function programaAtual()
    {
        return auth()->user()
            ->programaUsuarios()
            ->where('ativo', true)
            ->with('programa')
            ->first()
            ?->programa;
    }

    private function cadastroStatus(array $dados): string
    {
        $camposObrigatorios = [
            'name',
            'email',
            'categoria',
        ];

        if (($dados['categoria'] ?? null) === 'docente') {
            $camposObrigatorios = array_merge($camposObrigatorios, [
                'titulacao',
                'instituicao_origem',
                'instituicao_sigla',
            ]);
        }

        if (($dados['categoria'] ?? null) === 'discente') {
            $camposObrigatorios = array_merge($camposObrigatorios, [
                'matricula',
                'nivel',
                'linha_pesquisa_id',
            ]);
        }

        foreach ($camposObrigatorios as $campo) {
            if (empty($dados[$campo])) {
                return 'pendente';
            }
        }

        return 'completo';
    }

    public function index()
    {
        $this->autorizarSecretaria();

        $usuarios = User::with([
            'docenteProfile.vinculosPrograma.programa',
            'discenteProfile.vinculosPrograma.programa',
            'programaUsuarios.programa',
        ])
            ->orderBy('name')
            ->get();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        $programaAtual = $this->programaAtual();

        $linhasPesquisa = LinhaPesquisa::with('areaConcentracao.programa')
            ->when($programaAtual, function ($query) use ($programaAtual) {
                $query->whereHas('areaConcentracao', function ($q) use ($programaAtual) {
                    $q->where('programa_id', $programaAtual->id);
                });
            })
            ->orderBy('nome')
            ->get();

        return view('usuarios.create', compact('programaAtual', 'linhasPesquisa'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();

        $programaAtual = $this->programaAtual();
        if ($request->filled('data_ingresso')) {
            try {
                $request->merge([
                    'data_ingresso' => Carbon::createFromFormat('d/m/Y', $request->data_ingresso)->format('Y-m-d'),
                ]);
            } catch (\Exception $e) {
                // Mantém o valor original para a validação acusar erro.
            }
        }


        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'categoria' => ['required', Rule::in(['sec', 'docente', 'discente'])],

            'vincular_programa' => ['nullable', 'boolean'],

            'titulacao' => ['nullable', 'string', 'max:255'],
            'area_formacao' => ['nullable', 'string', 'max:255'],
            'instituicao_origem' => ['nullable', 'string', 'max:255'],
            'instituicao_sigla' => ['nullable', 'string', 'max:50'],

            'formacao' => ['nullable', 'string', 'max:255'],
            'instituicao_formacao' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:100'],
            'pais' => ['nullable', 'string', 'max:255'],

            'lattes_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^https?:\/\/lattes\.cnpq\.br\/[0-9]+$/',
            ],

            'orcid_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^https?:\/\/orcid\.org\/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/',
            ],

            'telefone' => ['nullable', 'string', 'max:30'],
            'telefone_whatsapp' => ['nullable', 'boolean'],
            'tipo_vinculo' => ['nullable', Rule::in(['interno', 'externo'])],
            'categoria_docente' => ['nullable', Rule::in(['permanente', 'colaborador', 'visitante'])],

            'matricula' => ['nullable', 'string', 'max:255'],
            'nivel' => ['nullable', Rule::in(['mestrado', 'doutorado'])],
            'linha_pesquisa_id' => ['nullable', 'exists:linhas_pesquisa,id'],
            'data_ingresso' => ['nullable', 'date'],
        ], [
            'lattes_url.regex' => 'Informe uma URL válida do Lattes. Exemplo: http://lattes.cnpq.br/1234567890123456',
            'orcid_url.regex' => 'Informe uma URL válida do ORCID. Exemplo: https://orcid.org/0000-0000-0000-0000',
        ]);

        $dados['titulacao'] = $dados['titulacao'] ?? 'Doutor(a)';
        $dados['cadastro_status'] = $this->cadastroStatus($dados);
        $senhaTemporaria = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);


        $usuarioCriado = DB::transaction(function () use ($request, $dados, $programaAtual, $senhaTemporaria) {
            $usuario = User::create([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => Hash::make($senhaTemporaria),
                'categoria' => $dados['categoria'],
                'primeiro_acesso' => true,
                'ativo' => true,
                'cadastro_status' => $dados['cadastro_status'],
            ]);

            if ($dados['categoria'] === 'sec' && $programaAtual) {
                ProgramaUsuario::firstOrCreate([
                    'programa_id' => $programaAtual->id,
                    'user_id' => $usuario->id,
                    'categoria_no_programa' => 'secretaria',
                ], [
                    'ativo' => true,
                ]);
            }

            if ($dados['categoria'] === 'docente') {
                $docenteProfile = DocenteProfile::create([
                    'user_id' => $usuario->id,
                    'titulacao' => $dados['titulacao'],
                    'area_formacao' => $dados['area_formacao'] ?? null,
                    'lattes_url' => $dados['lattes_url'] ?? null,
                    'lattes_verificado' => false,
                    'orcid_url' => $dados['orcid_url'] ?? null,
                    'orcid_verificado' => false,
                    'instituicao_origem' => $dados['instituicao_origem'] ?? null,
                    'instituicao_sigla' => $dados['instituicao_sigla'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'telefone_whatsapp' => $request->boolean('telefone_whatsapp'),
                    'ativo' => true,
                ]);

                if ($programaAtual && $request->boolean('vincular_programa')) {
                    DocenteProgramaVinculo::create([
                        'programa_id' => $programaAtual->id,
                        'docente_profile_id' => $docenteProfile->id,
                        'tipo_vinculo' => $dados['tipo_vinculo'] ?? 'interno',
                        'categoria_docente' => ($dados['tipo_vinculo'] ?? 'interno') === 'interno'
                            ? ($dados['categoria_docente'] ?? 'permanente')
                            : null,
                        'pode_orientar' => $request->boolean('pode_orientar'),
                        'pode_avaliar' => $request->boolean('pode_avaliar', true),
                        'ativo' => true,
                    ]);
                }
            }

            if ($dados['categoria'] === 'discente') {
                $discenteProfile = DiscenteProfile::create([
                    'user_id' => $usuario->id,
                    'formacao' => $dados['formacao'] ?? null,
                    'instituicao_formacao' => $dados['instituicao_formacao'] ?? null,
                    'lattes_url' => $dados['lattes_url'] ?? null,
                    'lattes_verificado' => false,
                    'orcid_url' => $dados['orcid_url'] ?? null,
                    'orcid_verificado' => false,
                    'telefone' => $dados['telefone'] ?? null,
                    'telefone_whatsapp' => $request->boolean('telefone_whatsapp'),
                    'cidade' => $dados['cidade'] ?? null,
                    'estado' => $dados['estado'] ?? null,
                    'pais' => $dados['pais'] ?? 'Brasil',
                    'ativo' => true,
                ]);

                if ($programaAtual) {
                    $vinculo = DiscenteProgramaVinculo::create([
                        'programa_id' => $programaAtual->id,
                        'discente_profile_id' => $discenteProfile->id,
                        'linha_pesquisa_id' => $dados['linha_pesquisa_id'] ?? null,
                        'matricula' => $dados['matricula'] ?? null,
                        'nivel' => $dados['nivel'] ?? 'mestrado',
                        'status' => 'ativo',
                        'data_ingresso' => $dados['data_ingresso'] ?? null,
                        'ativo' => true,
                    ]);

                    TrabalhoAcademico::create([
                        'programa_id' => $programaAtual->id,
                        'discente_programa_vinculo_id' => $vinculo->id,
                        'linha_pesquisa_id' => $vinculo->linha_pesquisa_id,
                        'nivel' => $vinculo->nivel,
                        'status' => 'em_andamento',
                        'data_inicio' => $vinculo->data_ingresso,
                    ]);
                }
            }
            return $usuario;
        });
        if ($usuarioCriado->cadastro_status === 'completo') {
            Mail::to($usuarioCriado->email)->send(
                new PrimeiroAcessoUsuarioMail(
                    $usuarioCriado,
                    $senhaTemporaria,
                    route('login')
                )
            );

            return redirect()
                ->route('usuarios.index')
                ->with('status', 'Usuário cadastrado com sucesso. Os dados de primeiro acesso foram enviados por e-mail.');
        }

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Usuário salvo como pendente. Complete o cadastro antes de enviar o acesso por e-mail.');
    }

    public function edit(User $usuario)
    {
        $this->autorizarSecretaria();

        $programaAtual = $this->programaAtual();

        $usuario->load([
            'docenteProfile.vinculosPrograma.programa',
            'discenteProfile.vinculosPrograma.programa',
            'programaUsuarios.programa',
        ]);

        $linhasPesquisa = LinhaPesquisa::with('areaConcentracao.programa')
            ->when($programaAtual, function ($query) use ($programaAtual) {
                $query->whereHas('areaConcentracao', function ($q) use ($programaAtual) {
                    $q->where('programa_id', $programaAtual->id);
                });
            })
            ->orderBy('nome')
            ->get();

        return view('usuarios.edit', compact('usuario', 'programaAtual', 'linhasPesquisa'));
    }

    public function update(Request $request, User $usuario)
    {
        $this->autorizarSecretaria();

        $programaAtual = $this->programaAtual();
        $statusAnterior = $usuario->cadastro_status;

        if ($request->filled('data_ingresso')) {
            try {
                $request->merge([
                    'data_ingresso' => Carbon::createFromFormat('d/m/Y', $request->data_ingresso)->format('Y-m-d'),
                ]);
            } catch (\Exception $e) {
                // Mantém o valor original para a validação acusar erro.
            }
        }

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
            'categoria' => ['required', Rule::in(['sec', 'docente', 'discente'])],

            'vincular_programa' => ['nullable', 'boolean'],

            'titulacao' => ['nullable', 'string', 'max:255'],
            'area_formacao' => ['nullable', 'string', 'max:255'],
            'instituicao_origem' => ['nullable', 'string', 'max:255'],
            'instituicao_sigla' => ['nullable', 'string', 'max:50'],

            'formacao' => ['nullable', 'string', 'max:255'],
            'instituicao_formacao' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:100'],
            'pais' => ['nullable', 'string', 'max:255'],

            'lattes_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^https?:\/\/lattes\.cnpq\.br\/[0-9]+$/',
            ],

            'orcid_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^https?:\/\/orcid\.org\/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/',
            ],

            'telefone' => ['nullable', 'string', 'max:30'],
            'telefone_whatsapp' => ['nullable', 'boolean'],
            'tipo_vinculo' => ['nullable', Rule::in(['interno', 'externo'])],
            'categoria_docente' => ['nullable', Rule::in(['permanente', 'colaborador', 'visitante'])],

            'matricula' => ['nullable', 'string', 'max:255'],
            'nivel' => ['nullable', Rule::in(['mestrado', 'doutorado'])],
            'linha_pesquisa_id' => ['nullable', 'exists:linhas_pesquisa,id'],
            'data_ingresso' => ['nullable', 'date'],
        ], [
            'lattes_url.regex' => 'Informe uma URL válida do Lattes. Exemplo: http://lattes.cnpq.br/1234567890123456',
            'orcid_url.regex' => 'Informe uma URL válida do ORCID. Exemplo: https://orcid.org/0000-0000-0000-0000',
        ]);

        $dados['titulacao'] = $dados['titulacao'] ?? 'Doutor(a)';
        $dados['cadastro_status'] = $this->cadastroStatus($dados);

        $usuarioAtualizado = DB::transaction(function () use ($request, $dados, $programaAtual, $usuario) {
            $usuario->update([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'categoria' => $dados['categoria'],
                'cadastro_status' => $dados['cadastro_status'],
            ]);

            if ($dados['categoria'] === 'sec' && $programaAtual) {
                ProgramaUsuario::firstOrCreate([
                    'programa_id' => $programaAtual->id,
                    'user_id' => $usuario->id,
                    'categoria_no_programa' => 'secretaria',
                ], [
                    'ativo' => true,
                ]);
            }

            if ($dados['categoria'] === 'docente') {
                $docenteProfile = DocenteProfile::updateOrCreate([
                    'user_id' => $usuario->id,
                ], [
                    'titulacao' => $dados['titulacao'],
                    'area_formacao' => $dados['area_formacao'] ?? null,
                    'lattes_url' => $dados['lattes_url'] ?? null,
                    'orcid_url' => $dados['orcid_url'] ?? null,
                    'instituicao_origem' => $dados['instituicao_origem'] ?? null,
                    'instituicao_sigla' => $dados['instituicao_sigla'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'telefone_whatsapp' => $request->boolean('telefone_whatsapp'),
                    'ativo' => true,
                ]);

                if ($programaAtual && $request->boolean('vincular_programa')) {
                    DocenteProgramaVinculo::updateOrCreate([
                        'programa_id' => $programaAtual->id,
                        'docente_profile_id' => $docenteProfile->id,
                    ], [
                        'tipo_vinculo' => $dados['tipo_vinculo'] ?? 'interno',
                        'categoria_docente' => ($dados['tipo_vinculo'] ?? 'interno') === 'interno'
                            ? ($dados['categoria_docente'] ?? 'permanente')
                            : null,
                        'pode_orientar' => false,
                        'pode_avaliar' => true,
                        'ativo' => true,
                    ]);
                }

                if ($programaAtual && ! $request->boolean('vincular_programa')) {
                    DocenteProgramaVinculo::where('programa_id', $programaAtual->id)
                        ->where('docente_profile_id', $docenteProfile->id)
                        ->delete();
                }
            }

            if ($dados['categoria'] === 'discente') {
                $discenteProfile = DiscenteProfile::updateOrCreate([
                    'user_id' => $usuario->id,
                ], [
                    'formacao' => $dados['formacao'] ?? null,
                    'instituicao_formacao' => $dados['instituicao_formacao'] ?? null,
                    'lattes_url' => $dados['lattes_url'] ?? null,
                    'orcid_url' => $dados['orcid_url'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'telefone_whatsapp' => $request->boolean('telefone_whatsapp'),
                    'cidade' => $dados['cidade'] ?? null,
                    'estado' => $dados['estado'] ?? null,
                    'pais' => $dados['pais'] ?? 'Brasil',
                    'ativo' => true,
                ]);

                if ($programaAtual) {
                    $vinculo = DiscenteProgramaVinculo::updateOrCreate([
                        'programa_id' => $programaAtual->id,
                        'discente_profile_id' => $discenteProfile->id,
                    ], [
                        'linha_pesquisa_id' => $dados['linha_pesquisa_id'] ?? null,
                        'matricula' => $dados['matricula'] ?? null,
                        'nivel' => $dados['nivel'] ?? 'mestrado',
                        'status' => 'ativo',
                        'data_ingresso' => $dados['data_ingresso'] ?? null,
                        'ativo' => true,
                    ]);

                    TrabalhoAcademico::updateOrCreate([
                        'discente_programa_vinculo_id' => $vinculo->id,
                    ], [
                        'programa_id' => $programaAtual->id,
                        'linha_pesquisa_id' => $vinculo->linha_pesquisa_id,
                        'nivel' => $vinculo->nivel,
                        'status' => 'em_andamento',
                        'data_inicio' => $vinculo->data_ingresso,
                    ]);
                }
            }

            return $usuario->fresh();
        });

        if (
            $statusAnterior === 'pendente'
            && $usuarioAtualizado->cadastro_status === 'completo'
            && $usuarioAtualizado->primeiro_acesso
        ) {
            $senhaTemporaria = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

            $usuarioAtualizado->update([
                'password' => Hash::make($senhaTemporaria),
                'primeiro_acesso' => true,
            ]);

            Mail::to($usuarioAtualizado->email)->send(
                new PrimeiroAcessoUsuarioMail(
                    $usuarioAtualizado,
                    $senhaTemporaria,
                    route('login')
                )
            );

            return redirect()
                ->route('usuarios.index')
                ->with('status', 'Usuário atualizado com sucesso. O acesso foi enviado por e-mail.');
        }

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Usuário atualizado com sucesso.');
    }
    public function reenviarAcesso(User $usuario)
    {
        $this->autorizarSecretaria();

        if ($usuario->cadastro_status !== 'completo') {
            return redirect()
                ->route('usuarios.index')
                ->with('status', 'Não é possível reenviar acesso para usuário com cadastro pendente.');
        }

        if (! $usuario->primeiro_acesso) {
            return redirect()
                ->route('usuarios.index')
                ->with('status', 'Este usuário já realizou o primeiro acesso.');
        }

        $senhaTemporaria = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

        $usuario->update([
            'password' => Hash::make($senhaTemporaria),
            'primeiro_acesso' => true,
        ]);

        Mail::to($usuario->email)->send(
            new PrimeiroAcessoUsuarioMail(
                $usuario,
                $senhaTemporaria,
                route('login')
            )
        );

        return redirect()
            ->route('usuarios.index')
            ->with('status', 'Acesso reenviado com sucesso. A nova senha temporária foi registrada no log de e-mail.');
    }
}
