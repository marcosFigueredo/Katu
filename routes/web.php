<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrimeiroAcessoSenhaController;
use App\Http\Controllers\ImportacaoDiscenteController;
use App\Http\Controllers\InstituicaoController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\ProgramaCursoController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\ProgramaDocumentoReferenciaController;
use App\Http\Controllers\ProgramaTemplateLatexController;
use App\Http\Controllers\AreaConcentracaoController;
use App\Http\Controllers\LinhaPesquisaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\BaremaController;
use App\Http\Controllers\BaremaItemController;
use App\Http\Controllers\BaremaSecaoController;
use App\Http\Controllers\OrientacaoDiscenteController;
use App\Http\Controllers\TrabalhoDocumentoController;
use App\Http\Controllers\SubmissaoLatexController;
use App\Http\Controllers\AutoavaliacaoOrientadorController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\TabelaLattesController;
use App\Http\Controllers\TrabalhoDadosPesquisaController;
use App\Http\Controllers\EspelhoDiscenteController;
use App\Http\Controllers\TrabalhoLattesController;
use App\Http\Controllers\BancaController;
use App\Http\Controllers\SwaraSessaoController;
use App\Http\Controllers\MabacController;
use App\Http\Controllers\BibliometriaController;
use App\Http\Controllers\AutoavaliacaoController;
use App\Http\Controllers\PerguntaEstrategicaController;
use App\Http\Controllers\OrientacaoAvaliacaoController;
use App\Http\Controllers\TrabalhoImpactoController;
use App\Http\Controllers\AtividadeFormativaController;
use App\Http\Controllers\AtividadesRealizadasController;
use App\Http\Controllers\ProducaoAssociadaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\MembroExternoController;
use App\Http\Controllers\CriterioCpaController;
use App\Http\Controllers\HomologacaoBancaController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('throttle:10,1')->group(function () {
    Route::get('/cadastro/membro-externo/{token}', [MembroExternoController::class, 'registro'])
        ->name('membros-externos.registro');
    Route::post('/cadastro/membro-externo/{token}', [MembroExternoController::class, 'registrar'])
        ->name('membros-externos.registro.store');
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/instituicoes/create', [InstituicaoController::class, 'create'])->name('instituicoes.create');
    Route::post('/instituicoes', [InstituicaoController::class, 'store'])->name('instituicoes.store');
    Route::get('/instituicao', [InstituicaoController::class, 'edit'])->name('instituicao.edit');
    Route::put('/instituicao', [InstituicaoController::class, 'update'])->name('instituicao.update');
    Route::post('/instituicao/logo', [InstituicaoController::class, 'atualizarLogo'])->name('instituicao.logo.update');
    Route::get('/instituicoes/{instituicao}/logo', [LogoController::class, 'instituicao'])->name('instituicoes.logo');
    Route::get('/programas/{programa}/logo', [LogoController::class, 'programa'])->name('programas.logo');
    Route::get('/programa/cursos', [ProgramaCursoController::class, 'index'])->name('programa-cursos.index');
    Route::post('/programa/cursos', [ProgramaCursoController::class, 'store'])->name('programa-cursos.store');
    Route::put('/programa-cursos/{curso}', [ProgramaCursoController::class, 'update'])->name('programa-cursos.update');
    Route::put('/programa-cursos/{curso}/regras', [ProgramaCursoController::class, 'regras'])->name('programa-cursos.regras');
    Route::get('/programa/documentos-referencia', [ProgramaDocumentoReferenciaController::class, 'index'])
        ->name('programa-documentos-referencia.index');
    Route::post('/programa/documentos-referencia', [ProgramaDocumentoReferenciaController::class, 'store'])
        ->name('programa-documentos-referencia.store');
    Route::get('/programa/template-latex', [ProgramaTemplateLatexController::class, 'index'])
        ->name('programa-template.index');
    Route::post('/programa/template-latex', [ProgramaTemplateLatexController::class, 'store'])
        ->name('programa-template.store');
    Route::get('/programas/{programa}/template-latex/download', [ProgramaTemplateLatexController::class, 'download'])
        ->name('programa-template.download');
});

Route::resource('programas', ProgramaController::class)
    ->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('criterios-cpa', CriterioCpaController::class)
        ->parameters(['criterios-cpa' => 'criterioCpa'])
        ->except(['show', 'destroy']);
    Route::post('criterios-cpa/{criterioCpa}/inativar', [CriterioCpaController::class, 'inativar'])
        ->name('criterios-cpa.inativar');

    Route::get('/membros-externos', [MembroExternoController::class, 'index'])
        ->name('membros-externos.index');
    Route::get('/membros-externos/create', [MembroExternoController::class, 'create'])
        ->name('membros-externos.create');
    Route::post('/membros-externos/convites', [MembroExternoController::class, 'store'])
        ->name('membros-externos.convites.store');
    Route::post('/membros-externos/convites/{convite}/reenviar', [MembroExternoController::class, 'reenviar'])
        ->name('membros-externos.convites.reenviar');
    Route::post('/membros-externos/{usuario}/aprovar', [MembroExternoController::class, 'aprovar'])
        ->name('membros-externos.aprovar');
    Route::get('/membros-externos/{usuario}/diploma', [MembroExternoController::class, 'diploma'])
        ->name('membros-externos.diploma');
    Route::post('/membros-externos/{usuario}/devolver', [MembroExternoController::class, 'devolver'])
        ->name('membros-externos.devolver');
});

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
Route::middleware(['auth', 'verified'])->group(function () {
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
});

// usuarios rotas
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/usuarios/importar-discentes', [ImportacaoDiscenteController::class, 'create'])->name('usuarios.importar');
    Route::post('/usuarios/importar-discentes', [ImportacaoDiscenteController::class, 'analisar'])->name('usuarios.importar.analisar');
    Route::post('/usuarios/importar-discentes/confirmar', [ImportacaoDiscenteController::class, 'confirmar'])->name('usuarios.importar.confirmar');

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

// Áreas do CNPq e setores CNAE (dados para o Lattes) – listas únicas mantidas pela Secretaria
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/atividades-formativas', [AtividadeFormativaController::class, 'index'])->name('atividades-formativas.index');
    Route::post('/atividades-formativas', [AtividadeFormativaController::class, 'store'])->name('atividades-formativas.store');
    Route::put('/atividades-formativas/{atividadeFormativa}', [AtividadeFormativaController::class, 'update'])->name('atividades-formativas.update');
    Route::post('/atividades-formativas/{atividadeFormativa}/inativar', [AtividadeFormativaController::class, 'inativar'])->name('atividades-formativas.inativar');
    Route::get('/tabelas-lattes', [TabelaLattesController::class, 'index'])->name('tabelas-lattes.index');
    Route::get('/tabelas-lattes/{lista}/novo', [TabelaLattesController::class, 'create'])->name('tabelas-lattes.create');
    Route::post('/tabelas-lattes/{lista}', [TabelaLattesController::class, 'store'])->name('tabelas-lattes.store');
    Route::get('/tabelas-lattes/{lista}/{id}/editar', [TabelaLattesController::class, 'edit'])->name('tabelas-lattes.edit');
    Route::put('/tabelas-lattes/{lista}/{id}', [TabelaLattesController::class, 'update'])->name('tabelas-lattes.update');
    Route::patch('/tabelas-lattes/{lista}/{id}/alternar', [TabelaLattesController::class, 'alternar'])->name('tabelas-lattes.alternar');
});

// Orientação do discente – gerida pela Secretaria na edição do usuário
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/usuarios/{usuario}/orientacao/orientador', [OrientacaoDiscenteController::class, 'definir'])
        ->name('usuarios.orientacao.definir');

    Route::post('/usuarios/{usuario}/orientacao/troca', [OrientacaoDiscenteController::class, 'trocar'])
        ->name('usuarios.orientacao.trocar');

    Route::post('/usuarios/{usuario}/orientacao/coorientadores', [OrientacaoDiscenteController::class, 'incluirCoorientador'])
        ->name('usuarios.orientacao.coorientador.incluir');

    Route::delete('/usuarios/{usuario}/orientacao/coorientadores/{orientacao}', [OrientacaoDiscenteController::class, 'retirarCoorientador'])
        ->name('usuarios.orientacao.coorientador.retirar');
});

// Bancas – composição da banca examinadora
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/bancas/homologacao', [HomologacaoBancaController::class, 'index'])
        ->name('bancas.homologacao');
    Route::get('/bancas/{banca}/homologacao', [HomologacaoBancaController::class, 'show'])
        ->name('bancas.homologacao.show');
    Route::post('/bancas/{banca}/homologacao/responder', [HomologacaoBancaController::class, 'responder'])
        ->name('bancas.homologacao.responder');
    Route::post('/bancas/{banca}/homologacao/atividades', [HomologacaoBancaController::class, 'conferirAtividades'])
        ->name('bancas.homologacao.atividades');
    Route::post('/bancas/{banca}/homologacao/publicacoes', [HomologacaoBancaController::class, 'conferirPublicacoes'])
        ->name('bancas.homologacao.publicacoes');
    Route::post('/bancas/{banca}/homologar', [HomologacaoBancaController::class, 'homologar'])
        ->name('bancas.homologar');
    Route::post('/bancas/{banca}/devolver', [HomologacaoBancaController::class, 'devolver'])
        ->name('bancas.devolver');
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

    Route::get('/avaliacoes/{banca}/lattes', [AvaliacaoController::class, 'lattes'])
        ->name('avaliacoes.lattes');

    Route::get('/avaliacoes/{banca}/autoavaliacao', [AutoavaliacaoOrientadorController::class, 'form'])
        ->name('avaliacoes.autoavaliacao.form');

    Route::post('/avaliacoes/{banca}/autoavaliacao', [AutoavaliacaoOrientadorController::class, 'store'])
        ->name('avaliacoes.autoavaliacao.store');

    Route::get('/avaliacoes/{banca}/barema/{barema}/relatorio', [AvaliacaoController::class, 'relatorioIndividual'])
        ->name('avaliacoes.relatorio-individual');

    Route::get('/avaliacoes/{banca}/relatorio-consolidado', [AvaliacaoController::class, 'relatorioConsolidado'])
        ->name('avaliacoes.relatorio-consolidado');

    Route::get('/avaliacoes/{banca}/ata', [AvaliacaoController::class, 'ata'])
        ->name('avaliacoes.ata');

    Route::get('/avaliacoes/{banca}/folha-aprovacao', [AvaliacaoController::class, 'folhaAprovacao'])
        ->name('avaliacoes.folha-aprovacao');
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

    Route::post('/meu-trabalho/enviar-homologacao/{documento}', [TrabalhoDocumentoController::class, 'enviarHomologacao'])
        ->name('meu-trabalho.enviar-homologacao');

    // Programa com template LaTeX: o discente envia o projeto (.zip) em vez do PDF.
    Route::post('/meu-trabalho/submissoes-latex', [SubmissaoLatexController::class, 'store'])
        ->name('meu-trabalho.submissao-latex.store');

    Route::get('/meu-trabalho/submissoes-latex/{submissao}', [SubmissaoLatexController::class, 'show'])
        ->name('meu-trabalho.submissao-latex.show');

    Route::get('/meu-trabalho/submissoes-latex/{submissao}/pdf/{versao}', [SubmissaoLatexController::class, 'pdf'])
        ->whereIn('versao', ['banca', 'conferencia'])
        ->name('meu-trabalho.submissao-latex.pdf');

    Route::post('/meu-trabalho/submissoes-latex/{submissao}/enviar-homologacao', [SubmissaoLatexController::class, 'enviarHomologacao'])
        ->name('meu-trabalho.submissao-latex.enviar-homologacao');

    Route::get('/meu-trabalho/lattes', [TrabalhoLattesController::class, 'edit'])
        ->name('meu-trabalho.lattes.edit');

    Route::put('/meu-trabalho/lattes', [TrabalhoLattesController::class, 'update'])
        ->name('meu-trabalho.lattes.update');

    Route::get('/meu-trabalho/atividades-formativas', [AtividadesRealizadasController::class, 'index'])
        ->name('meu-trabalho.atividades.index');
    Route::post('/meu-trabalho/atividades-formativas', [AtividadesRealizadasController::class, 'store'])
        ->name('meu-trabalho.atividades.store');
    Route::delete('/meu-trabalho/atividades-formativas/{atividadeRealizada}', [AtividadesRealizadasController::class, 'destroy'])
        ->name('meu-trabalho.atividades.destroy');

    Route::get('/meu-trabalho/producao', [ProducaoAssociadaController::class, 'index'])
        ->name('meu-trabalho.producao.index');
    Route::post('/meu-trabalho/producao/confirmar', [ProducaoAssociadaController::class, 'confirmar'])
        ->name('meu-trabalho.producao.confirmar');
    Route::post('/meu-trabalho/producao/{tipo}', [ProducaoAssociadaController::class, 'store'])
        ->name('meu-trabalho.producao.store');
    Route::delete('/meu-trabalho/producao/{tipo}/{id}', [ProducaoAssociadaController::class, 'destroy'])
        ->whereNumber('id')
        ->name('meu-trabalho.producao.destroy');

    Route::get('/meu-trabalho/dados-pesquisa', [TrabalhoDadosPesquisaController::class, 'edit'])
        ->name('meu-trabalho.dados-pesquisa.edit');

    Route::put('/meu-trabalho/dados-pesquisa', [TrabalhoDadosPesquisaController::class, 'update'])
        ->name('meu-trabalho.dados-pesquisa.update');

    Route::get('/meu-trabalho/espelho', [EspelhoDiscenteController::class, 'form'])
        ->name('meu-trabalho.espelho.form');

    Route::post('/meu-trabalho/espelho', [EspelhoDiscenteController::class, 'store'])
        ->name('meu-trabalho.espelho.store');

    Route::get('/meu-trabalho/impacto', [TrabalhoImpactoController::class, 'edit'])
        ->name('trabalho-impacto.edit');

    Route::put('/meu-trabalho/impacto', [TrabalhoImpactoController::class, 'update'])
        ->name('trabalho-impacto.update');
});

// IEO – Avaliação de orientação pelo discente
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/bancas/{banca}/orientacao-avaliacao/create', [OrientacaoAvaliacaoController::class, 'create'])
        ->name('orientacao-avaliacao.create');

    Route::post('/bancas/{banca}/orientacao-avaliacao', [OrientacaoAvaliacaoController::class, 'store'])
        ->name('orientacao-avaliacao.store');

    Route::get('/orientacao-avaliacao', [OrientacaoAvaliacaoController::class, 'index'])
        ->name('orientacao-avaliacao.index');
});

// Download via URL assinada (fora do grupo auth para validar assinatura antes)
Route::get('/meu-trabalho/download/{documento}', [TrabalhoDocumentoController::class, 'download'])
    ->name('meu-trabalho.download')
    ->middleware('auth');

// SWARA – calibração de pesos dos critérios do barema
Route::middleware(['auth', 'verified'])->prefix('baremas/{barema}/swara')->name('swara.')->group(function () {
    Route::get('/', [SwaraSessaoController::class, 'index'])->name('index');
    Route::get('/create', [SwaraSessaoController::class, 'create'])->name('create');
    Route::post('/', [SwaraSessaoController::class, 'store'])->name('store');
    Route::get('/{sessao}', [SwaraSessaoController::class, 'show'])->name('show');
    Route::post('/{sessao}/aplicar', [SwaraSessaoController::class, 'aplicar'])->name('aplicar');
});

// MABAC – análise multicritério comparativa
Route::middleware(['auth', 'verified'])->prefix('mabac')->name('mabac.')->group(function () {
    Route::get('/', [MabacController::class, 'index'])->name('index');
    Route::get('/create', [MabacController::class, 'create'])->name('create');
    Route::post('/', [MabacController::class, 'store'])->name('store');
    Route::get('/{mabac}', [MabacController::class, 'show'])->name('show');
    Route::delete('/{mabac}', [MabacController::class, 'destroy'])->name('destroy');
});

// Bibliometria – indicadores por trabalho acadêmico
Route::middleware(['auth', 'verified'])->prefix('bibliometria')->name('bibliometria.')->group(function () {
    Route::get('/trabalhos/{trabalho}', [BibliometriaController::class, 'show'])->name('show');
    Route::post('/trabalhos/{trabalho}/sincronizar', [BibliometriaController::class, 'sincronizar'])->name('sincronizar');
});

// Autoavaliação – períodos e indicadores institucionais
Route::middleware(['auth', 'verified'])->prefix('autoavaliacao')->name('autoavaliacao.')->group(function () {
    Route::get('/', [AutoavaliacaoController::class, 'index'])->name('index');
    Route::get('/create', [AutoavaliacaoController::class, 'create'])->name('create');
    Route::post('/', [AutoavaliacaoController::class, 'store'])->name('store');
    Route::get('/{autoavaliacao}', [AutoavaliacaoController::class, 'show'])->name('show');
    Route::post('/{autoavaliacao}/calcular', [AutoavaliacaoController::class, 'calcular'])->name('calcular');
    Route::delete('/{autoavaliacao}', [AutoavaliacaoController::class, 'destroy'])->name('destroy');
});

// Relatórios — secretaria
Route::middleware(['auth', 'verified'])->prefix('relatorios')->name('relatorios.')->group(function () {
    Route::get('/', [RelatorioController::class, 'index'])->name('index');
    Route::get('/bancas', [RelatorioController::class, 'bancas'])->name('bancas');
    Route::get('/composicao', [RelatorioController::class, 'composicao'])->name('composicao');
    Route::get('/docentes', [RelatorioController::class, 'docentes'])->name('docentes');
    Route::get('/percurso', [RelatorioController::class, 'percurso'])->name('percurso');
    Route::get('/orientacao', [RelatorioController::class, 'orientacao'])->name('orientacao');
    Route::get('/impacto', [RelatorioController::class, 'impacto'])->name('impacto');
    Route::get('/discentes', [RelatorioController::class, 'discentes'])->name('discentes');
    Route::get('/documentos', [RelatorioController::class, 'documentos'])->name('documentos');
    Route::get('/autoavaliacao', [RelatorioController::class, 'autoavaliacaoRelatorio'])->name('autoavaliacao');
    Route::get('/mabac', [RelatorioController::class, 'mabacRelatorio'])->name('mabac');
    Route::get('/swara', [RelatorioController::class, 'swaraRelatorio'])->name('swara');
    Route::get('/corpo-docente', [RelatorioController::class, 'corpoDocente'])->name('corpo-docente');
    Route::get('/dashboard', [RelatorioController::class, 'dashboard'])->name('dashboard');
    Route::get('/exportar', [RelatorioController::class, 'exportar'])->name('exportar');
});

// Perguntas Estratégicas — painel de autoavaliação orientado a perguntas (CAPES + comissão)
Route::middleware(['auth', 'verified'])->prefix('perguntas-estrategicas')->name('perguntas-estrategicas.')->group(function () {
    Route::get('/', [PerguntaEstrategicaController::class, 'index'])->name('index');
    Route::get('/create', [PerguntaEstrategicaController::class, 'create'])->name('create');
    Route::post('/', [PerguntaEstrategicaController::class, 'store'])->name('store');
    Route::get('/{pergunta}/edit', [PerguntaEstrategicaController::class, 'edit'])->name('edit');
    Route::put('/{pergunta}', [PerguntaEstrategicaController::class, 'update'])->name('update');
    Route::delete('/{pergunta}', [PerguntaEstrategicaController::class, 'destroy'])->name('destroy');
});

require __DIR__ . '/auth.php';
