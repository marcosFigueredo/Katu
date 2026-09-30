<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Models\Programa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogoController extends Controller
{
    public const REGRAS = ['file', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048'];

    public static function armazenar(UploadedFile $arquivo, ?string $anterior): string
    {
        $caminho = $arquivo->store('logos', 'local');

        if ($anterior && str_starts_with($anterior, 'logos/')) {
            Storage::disk('local')->delete($anterior);
        }

        return $caminho;
    }

    public function instituicao(Instituicao $instituicao): StreamedResponse
    {
        return $this->responder($instituicao->logo_path);
    }

    public function programa(Programa $programa): StreamedResponse
    {
        return $this->responder($programa->logo_path);
    }

    private function responder(?string $caminho): StreamedResponse
    {
        abort_unless($caminho && str_starts_with($caminho, 'logos/') && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
