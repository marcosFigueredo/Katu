<?php

namespace App\Http\Controllers;

use App\Mail\ConviteMembroExternoMail;
use App\Mail\PrimeiroAcessoUsuarioMail;
use App\Models\ConviteMembroExterno;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Programa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MembroExternoController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    private function programaAtual(): ?Programa
    {
        return auth()->user()
            ->programaUsuarios()
            ->where('ativo', true)
            ->with('programa')
            ->first()
            ?->programa;
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->programaAtual();

        $convites = ConviteMembroExterno::query()
            ->when($programa, fn ($query) => $query->where('programa_id', $programa->id), fn ($query) => $query->whereKey(-1))
            ->with('usuario.docenteProfile')
            ->latest()
            ->get();

        return view('membros-externos.index', compact('programa', 'convites'));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        return view('membros-externos.create', [
            'programa' => $this->programaAtual(),
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->programaAtual();

        if (! $programa) {
            return back()->withErrors(['programa' => 'A Secretaria não possui programa ativo vinculado.']);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'expira_em' => ['required', 'date', 'after:today'],
        ]);

        $token = Str::random(64);
        $convite = ConviteMembroExterno::create([
            'programa_id' => $programa->id,
            'criado_por' => auth()->id(),
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'token_hash' => ConviteMembroExterno::hashToken($token),
            'status' => 'enviado',
            'expires_at' => Carbon::parse($dados['expira_em'])->endOfDay(),
        ]);

        Mail::to($convite->email)->send(new ConviteMembroExternoMail(
            $convite,
            route('membros-externos.registro', ['token' => $token]),
        ));

        return redirect()
            ->route('membros-externos.index')
            ->with('status', 'Convite enviado para o membro externo.');
    }

    public function reenviar(ConviteMembroExterno $convite)
    {
        $this->autorizarSecretaria();
        $this->garantirConviteDoPrograma($convite);

        if ($convite->status === 'aprovado') {
            return back()->withErrors(['convite' => 'Este membro externo já foi aprovado.']);
        }

        $token = Str::random(64);
        $convite->update([
            'token_hash' => ConviteMembroExterno::hashToken($token),
            'status' => 'enviado',
            'expires_at' => now()->addDays(7)->endOfDay(),
        ]);

        Mail::to($convite->email)->send(new ConviteMembroExternoMail(
            $convite->fresh(),
            route('membros-externos.registro', ['token' => $token]),
        ));

        return back()->with('status', 'Convite reenviado com validade de sete dias.');
    }

    public function registro(string $token)
    {
        $convite = $this->convitePorToken($token);

        return view('membros-externos.registro', compact('convite', 'token'));
    }

    public function registrar(Request $request, string $token)
    {
        $convite = $this->convitePorToken($token);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'titulacao' => ['required', 'string', 'max:255'],
            'area_formacao' => ['nullable', 'string', 'max:255'],
            'instituicao_origem' => ['required', 'string', 'max:255'],
            'instituicao_sigla' => ['required', 'string', 'max:50'],
            'lattes_url' => ['nullable', 'url', 'max:255'],
            'orcid_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^https?:\/\/orcid\.org\/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/',
            ],
            'telefone' => ['nullable', 'string', 'max:30'],
            'diploma' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [
            'diploma.required' => 'Envie a imagem ou PDF do diploma de doutorado.',
            'diploma.mimes' => 'O diploma deve ser JPG, PNG ou PDF.',
            'diploma.max' => 'O diploma deve ter no máximo 10 MB.',
            'orcid_url.regex' => 'Informe uma URL válida do ORCID.',
        ]);

        $arquivo = $request->file('diploma');
        $caminhoDiploma = Storage::disk('local')->putFile('diplomas-doutorado', $arquivo);
        $senhaTemporaria = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

        DB::transaction(function () use ($convite, $dados, $caminhoDiploma, $arquivo, $senhaTemporaria) {
            $usuario = $convite->usuario;

            if (! $usuario) {
                $usuario = User::where('email', $convite->email)->first();

                if ($usuario && $usuario->categoria !== 'docente') {
                    abort(422, 'O e-mail do convite já pertence a outra categoria de usuário.');
                }

                $usuario ??= User::create([
                    'name' => $dados['name'],
                    'email' => $convite->email,
                    'password' => Hash::make($senhaTemporaria),
                    'categoria' => 'docente',
                    'email_verified_at' => now(),
                    'primeiro_acesso' => true,
                    'cadastro_status' => 'aguardando_validacao',
                    'ativo' => true,
                ]);
            }

            $usuario->update([
                'name' => $dados['name'],
                'categoria' => 'docente',
                'primeiro_acesso' => true,
                'cadastro_status' => 'aguardando_validacao',
                'ativo' => true,
            ]);

            DocenteProfile::updateOrCreate(
                ['user_id' => $usuario->id],
                [
                    'titulacao' => $dados['titulacao'],
                    'area_formacao' => $dados['area_formacao'] ?? null,
                    'instituicao_origem' => $dados['instituicao_origem'],
                    'instituicao_sigla' => $dados['instituicao_sigla'],
                    'lattes_url' => $dados['lattes_url'] ?? null,
                    'orcid_url' => $dados['orcid_url'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'diploma_doutorado_path' => $caminhoDiploma,
                    'diploma_doutorado_nome' => $arquivo->getClientOriginalName(),
                    'diploma_doutorado_enviado_em' => now(),
                    'diploma_doutorado_verificado' => false,
                    'ativo' => true,
                ],
            );

            $convite->update([
                'user_id' => $usuario->id,
                'status' => 'aguardando_validacao',
                'used_at' => $convite->used_at ?? now(),
            ]);
        });

        return response()->view('membros-externos.pendente', compact('convite'));
    }

    public function aprovar(User $usuario)
    {
        $this->autorizarSecretaria();
        $programa = $this->programaAtual();
        $convite = $this->conviteDoUsuario($usuario, $programa);

        abort_unless($convite && in_array($convite->status, ['aguardando_validacao', 'devolvido'], true), 404);

        $usuario->load('docenteProfile');
        abort_unless($usuario->categoria === 'docente' && $usuario->docenteProfile?->diploma_doutorado_path, 422, 'O diploma de doutorado é obrigatório para aprovação.');

        $senhaTemporaria = 'PPG-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

        DB::transaction(function () use ($usuario, $programa, $convite, $senhaTemporaria) {
            $usuario->update([
                'cadastro_status' => 'completo',
                'password' => Hash::make($senhaTemporaria),
                'primeiro_acesso' => true,
                'ativo' => true,
            ]);

            $usuario->docenteProfile->update([
                'diploma_doutorado_verificado' => true,
            ]);

            DocenteProgramaVinculo::updateOrCreate(
                [
                    'programa_id' => $programa->id,
                    'docente_profile_id' => $usuario->docenteProfile->id,
                ],
                [
                    'tipo_vinculo' => 'externo',
                    'categoria_docente' => null,
                    'pode_orientar' => false,
                    'pode_avaliar' => true,
                    'ativo' => true,
                ],
            );

            $convite->update([
                'status' => 'aprovado',
                'observacoes' => null,
            ]);
        });

        Mail::to($usuario->email)->send(new PrimeiroAcessoUsuarioMail(
            $usuario->fresh(),
            $senhaTemporaria,
            route('login'),
        ));

        return redirect()
            ->route('membros-externos.index')
            ->with('status', 'Membro externo aprovado e vinculado ao programa.');
    }

    public function diploma(User $usuario)
    {
        $this->autorizarSecretaria();
        $convite = $this->conviteDoUsuario($usuario, $this->programaAtual());
        abort_unless($convite, 404);

        $usuario->load('docenteProfile');
        $profile = $usuario->docenteProfile;
        abort_unless($profile?->diploma_doutorado_path, 404);

        return Storage::disk('local')->download(
            $profile->diploma_doutorado_path,
            $profile->diploma_doutorado_nome ?: 'diploma-doutorado',
        );
    }

    public function devolver(Request $request, User $usuario)
    {
        $this->autorizarSecretaria();
        $programa = $this->programaAtual();
        $convite = $this->conviteDoUsuario($usuario, $programa);

        abort_unless($convite, 404);

        $dados = $request->validate([
            'observacoes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($usuario, $convite, $dados) {
            $usuario->update(['cadastro_status' => 'devolvido']);
            $convite->update([
                'status' => 'devolvido',
                'observacoes' => $dados['observacoes'],
            ]);
        });

        return back()->with('status', 'Cadastro devolvido ao membro externo para ajustes.');
    }

    private function convitePorToken(string $token): ConviteMembroExterno
    {
        $convite = ConviteMembroExterno::where('token_hash', ConviteMembroExterno::hashToken($token))->first();

        abort_unless($convite, 404);

        if ($convite->expires_at?->isPast()) {
            $convite->update(['status' => 'expirado']);
            abort(410, 'Este convite expirou. Solicite um novo link à Secretaria.');
        }

        abort_if($convite->status === 'aprovado', 410, 'Este convite já foi utilizado.');

        return $convite;
    }

    private function garantirConviteDoPrograma(ConviteMembroExterno $convite): void
    {
        abort_unless($convite->programa_id === $this->programaAtual()?->id, 404);
    }

    private function conviteDoUsuario(User $usuario, ?Programa $programa): ?ConviteMembroExterno
    {
        return ConviteMembroExterno::query()
            ->where('programa_id', $programa?->id)
            ->where('user_id', $usuario->id)
            ->latest()
            ->first();
    }
}
