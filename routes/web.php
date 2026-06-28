<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrimeiroAcessoSenhaController;
use App\Http\Controllers\InstituicaoController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\AreaConcentracaoController;
use App\Http\Controllers\LinhaPesquisaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\BaremaController;
use App\Http\Controllers\BaremaItemController;
use App\Http\Controllers\BaremaSecaoController;
use App\Http\Controllers\OrientacaoController;
use App\Http\Controllers\TrabalhoDocumentoController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\BancaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/primeiro-acesso/senha', [PrimeiroAcessoSenhaController::class, 'edit'])
    ->middleware(['auth'])
    ->name('primeiro-acesso-senha.edit');

Route::put('/primeiro-acesso/senha', [PrimeiroAcessoSenhaController::class, 'update'])
    ->middleware(['auth'])
    ->name('primeiro-acesso-senha.update');

Route::get('/instituicao', [InstituicaoController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('instituicao.edit');

Route::put('/instituicao', [InstituicaoController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('instituicao.update');

Route::resource('programas', ProgramaController::class)
    ->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/programas/{programa}/areas', [AreaConcentracaoController::class, 'index'])
        ->name('programas.areas.index');

    Route::get('/programas/{programa}/areas/create', [AreaConcentracaoController::class, 'create'])
        ->name('programas.areas.create');

    Route::post('/programas/{programa}/areas', [AreaConcentracaoController::class, 'store'])
        ->name('programas.areas.store');

    Route::get('/areas-concentracao/{areaConcentracao}/edit', [AreaConcentracaoController::class, 'edit'])
        ->name('areas-concentracao.edit');

    Route::put('/areas-concentracao/{areaConcentracao}', [AreaConcentracaoController::class, 'update'])
        ->name('areas-concentracao.update');

    Route::delete('/areas-concentracao/{areaConcentracao}', [AreaConcentracaoController::class, 'destroy'])
        ->name('areas-concentracao.destroy');
});

// linhas de pesquisa rotas
Route::get('/areas-concentracao/{areaConcentracao}/linhas', [LinhaPesquisaController::class, 'index'])
    ->name('areas.linhas.index');

Route::get('/areas-concentracao/{areaConcentracao}/linhas/create', [LinhaPesquisaController::class, 'create'])
    ->name('areas.linhas.create');

Route::post('/areas-concentracao/{areaConcentracao}/linhas', [LinhaPesquisaController::class, 'store'])
    ->name('areas.linhas.store');

Route::get('/linhas-pesquisa/{linhaPesquisa}/edit', [LinhaPesquisaController::class, 'edit'])
    ->name('linhas-pesquisa.edit');

Route::put('/linhas-pesquisa/{linhaPesquisa}', [LinhaPesquisaController::class, 'update'])
    ->name('linhas-pesquisa.update');

Route::delete('/linhas-pesquisa/{linhaPesquisa}', [LinhaPesquisaController::class, 'destroy'])
    ->name('linhas-pesquisa.destroy');

// usuarios rotas
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])
        ->name('usuarios.index');

    Route::get('/usuarios/create', [UsuarioController::class, 'create'])
        ->name('usuarios.create');

    Route::post('/usuarios', [UsuarioController::class, 'store'])
        ->name('usuarios.store');

    Route::get('/usuarios/{usuario}/edit', [UsuarioController::class, 'edit'])
        ->name('usuarios.edit');

    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])
        ->name('usuarios.update');
        
    Route::post('/usuarios/{usuario}/reenviar-acesso', [UsuarioController::class, 'reenviarAcesso'])
    ->name('usuarios.reenviar-acesso');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Baremas – instrumentos de avaliação
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('baremas', BaremaController::class)->except(['show']);

    Route::post('/baremas/{barema}/secoes', [BaremaSecaoController::class, 'store'])
        ->name('baremas.secoes.store');

    Route::put('/barema-secoes/{secao}', [BaremaSecaoController::class, 'update'])
        ->name('barema-secoes.update');

    Route::delete('/barema-secoes/{secao}', [BaremaSecaoController::class, 'destroy'])
        ->name('barema-secoes.destroy');

    Route::post('/barema-secoes/{secao}/itens', [BaremaItemController::class, 'store'])
        ->name('barema-secoes.itens.store');

    Route::put('/barema-itens/{item}', [BaremaItemController::class, 'update'])
        ->name('barema-itens.update');

    Route::delete('/barema-itens/{item}', [BaremaItemController::class, 'destroy'])
        ->name('barema-itens.destroy');
});

// Orientações – gestão de orientadores e coorientadores
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/orientacoes', [OrientacaoController::class, 'index'])
        ->name('orientacoes.index');

    Route::get('/orientacoes/{trabalho}/edit', [OrientacaoController::class, 'edit'])
        ->name('orientacoes.edit');

    Route::put('/orientacoes/{trabalho}', [OrientacaoController::class, 'update'])
        ->name('orientacoes.update');
});

// Bancas – composição da banca examinadora
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bancas', BancaController::class)->except(['show']);
    Route::post('/bancas/{banca}/reabrir', [BancaController::class, 'reabrir'])
        ->name('bancas.reabrir');
});

// Avaliações – área do docente (avaliador e orientador)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/avaliacoes', [AvaliacaoController::class, 'index'])
        ->name('avaliacoes.index');

    Route::get('/avaliacoes/{banca}', [AvaliacaoController::class, 'banca'])
        ->name('avaliacoes.banca');

    Route::get('/avaliacoes/{banca}/barema/{barema}', [AvaliacaoController::class, 'form'])
        ->name('avaliacoes.form');

    Route::post('/avaliacoes/{banca}/barema/{barema}', [AvaliacaoController::class, 'salvar'])
        ->name('avaliacoes.salvar');

    Route::post('/avaliacoes/{banca}/finalizar', [AvaliacaoController::class, 'finalizar'])
        ->name('avaliacoes.finalizar');

    Route::get('/avaliacoes/{banca}/barema/{barema}/relatorio', [AvaliacaoController::class, 'relatorioIndividual'])
        ->name('avaliacoes.relatorio-individual');

    Route::get('/avaliacoes/{banca}/relatorio-consolidado', [AvaliacaoController::class, 'relatorioConsolidado'])
        ->name('avaliacoes.relatorio-consolidado');

    Route::get('/avaliacoes/{banca}/ata', [AvaliacaoController::class, 'ata'])
        ->name('avaliacoes.ata');
});

// Área do discente – envio e análise de documentos
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/meu-trabalho', [TrabalhoDocumentoController::class, 'index'])
        ->name('meu-trabalho.index');

    Route::post('/meu-trabalho/documentos', [TrabalhoDocumentoController::class, 'store'])
        ->name('meu-trabalho.store');

    Route::get('/meu-trabalho/analise/{analise}', [TrabalhoDocumentoController::class, 'analise'])
        ->name('meu-trabalho.analise');

    Route::post('/meu-trabalho/analise/{analise}/avaliar-resumo', [TrabalhoDocumentoController::class, 'avaliarResumo'])
        ->name('meu-trabalho.avaliar-resumo');

    Route::post('/meu-trabalho/enviar-banca/{documento}', [TrabalhoDocumentoController::class, 'enviarParaBanca'])
        ->name('meu-trabalho.enviar-banca');
});

// Download via URL assinada (fora do grupo auth para validar assinatura antes)
Route::get('/meu-trabalho/download/{documento}', [TrabalhoDocumentoController::class, 'download'])
    ->name('meu-trabalho.download')
    ->middleware('auth');

require __DIR__ . '/auth.php';
