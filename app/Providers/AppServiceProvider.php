<?php

namespace App\Providers;

use App\Services\Latex\CompiladorLatex;
use App\Services\Latex\LatexmkCompilador;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompiladorLatex::class, fn () => new LatexmkCompilador(
            (string) config('latex.binario', 'latexmk'),
            (int) config('latex.timeout', 180),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
