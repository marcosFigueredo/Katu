<?php

namespace App\Services;

use App\Models\DocumentoAnalise;
use App\Models\DocumentoAnaliseItem;
use App\Models\DocumentoAnaliseOllama;
use App\Models\DocumentoChecklist;
use App\Models\DocenteProfile;
use App\Models\TrabalhoDocumento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class DocumentoAnaliseService
{

    public function analisar(TrabalhoDocumento $documento): DocumentoAnalise
    {
        $documento->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa',
        ]);

        $documento->update(['status_analise' => 'processando']);

        try {
            $caminhoPdf = Storage::disk('local')->path($documento->arquivo_pdf_path);

            $parser = new Parser();
            $pdf    = $parser->parseFile($caminhoPdf);
            $paginas = $pdf->getPages();

            if (count($paginas) === 0) {
                return $this->registrarFalha($documento, 'O PDF não possui páginas identificáveis.');
            }

            $textoPagina1           = trim($paginas[0]->getText());
            $textoPagina1Normalizado = $this->normalizarBusca($textoPagina1);
            $textosPorPagina        = $this->extrairTextosPorPagina($paginas);

            $usuarioDiscente = $documento
                ->trabalhoAcademico
                ?->discenteVinculo
                ?->discenteProfile
                ?->user;

            if (! $usuarioDiscente) {
                return $this->registrarFalha($documento, 'Usuário discente não localizado para comparação com a capa.');
            }

            $dadosCapa = $this->extrairDadosDaCapa($textoPagina1, $usuarioDiscente->name);
            $checklist = $this->localizarChecklist($documento);

            $analise = DB::transaction(function () use (
                $documento,
                $textoPagina1,
                $textoPagina1Normalizado,
                $textosPorPagina,
                $usuarioDiscente,
                $dadosCapa,
                $paginas,
                $checklist
            ) {
                $analise = DocumentoAnalise::create([
                    'trabalho_documento_id'   => $documento->id,
                    'documento_checklist_id'  => $checklist?->id,
                    'status_geral'            => 'com_pendencias',
                    'percentual_conformidade' => 0,
                    'total_itens'             => 0,
                    'itens_conformes'         => 0,
                    'itens_atencao'           => 0,
                    'itens_pendentes'         => 0,
                    'itens_nao_aplicaveis'    => 0,
                    'pdf_pesquisavel'         => mb_strlen($textoPagina1Normalizado) > 100,
                    'pdf_protegido'           => false,
                    'classe_latex_detectada'  => $this->textoContem($textoPagina1, [
                        'classPPGMSB',
                        'PPGMSB-UNEB|classPPGMSB|CERTIFIED',
                    ]),
                    'classe_latex_nome'       => $this->textoContem($textoPagina1, ['classPPGMSB'])
                        ? 'classPPGMSB'
                        : null,
                    'classe_latex_versao'     => null,
                    'motor_tex'               => null,
                    'codigo_certificacao'     => $this->textoContem($textoPagina1, ['PPGMSB-UNEB|classPPGMSB|CERTIFIED'])
                        ? 'PPGMSB-UNEB|classPPGMSB|CERTIFIED'
                        : null,
                    'texto_extraido'          => $textoPagina1,
                    'mensagem_geral'          => null,
                    'analisado_em'            => Carbon::now(),
                ]);

                // ── Fase 1: Capa ─────────────────────────────────────────────
                $this->registrarItem(
                    $analise, 'PDF pesquisável na capa', 'tecnico',
                    mb_strlen($textoPagina1Normalizado) > 100 ? 'conforme' : 'pendente',
                    mb_strlen($textoPagina1Normalizado) > 100
                        ? 'A página 1 possui texto extraível.'
                        : 'A página 1 não possui texto extraível suficiente.',
                    null, true, true,
                    mb_strlen($textoPagina1Normalizado) > 100
                        ? 'Texto da capa extraído com sucesso.'
                        : 'A capa pode estar escaneada ou sem texto pesquisável.'
                );

                $this->registrarItem(
                    $analise, 'Tipo do documento na capa', 'capa',
                    $dadosCapa['tipo_documento'] ? 'conforme' : 'pendente',
                    $dadosCapa['tipo_documento'] ?: null,
                    1, true, true,
                    $dadosCapa['tipo_documento']
                        ? 'Tipo do documento localizado na capa.'
                        : 'Não foi localizado EXAME DE QUALIFICAÇÃO, DEFESA ou DISSERTAÇÃO na capa.'
                );

                $autorConfere = $this->textoContem($textoPagina1, [$usuarioDiscente->name]);
                $this->registrarItem(
                    $analise, 'Nome do autor confere com o usuário', 'capa',
                    $autorConfere ? 'conforme' : 'pendente',
                    $autorConfere ? $usuarioDiscente->name : 'Usuário esperado: ' . $usuarioDiscente->name,
                    1, true, true,
                    $autorConfere
                        ? 'O nome do autor na capa confere com o usuário do sistema.'
                        : 'O nome do usuário do sistema não foi localizado na capa.'
                );

                $emailCapa    = $dadosCapa['email'];
                $emailConfere = $emailCapa
                    && $this->normalizarBusca($emailCapa) === $this->normalizarBusca($usuarioDiscente->email);
                $this->registrarItem(
                    $analise, 'E-mail da capa confere com o sistema', 'capa',
                    $emailConfere ? 'conforme' : 'pendente',
                    $emailCapa ?: null,
                    1, true, true,
                    $emailConfere
                        ? 'O e-mail informado na capa confere com o e-mail cadastrado no sistema.'
                        : 'E-mail esperado: ' . $usuarioDiscente->email
                          . '. E-mail localizado na capa: ' . ($emailCapa ?: 'não localizado') . '.'
                );

                $orientadorNome  = $dadosCapa['orientador'];
                $orientadorValido = $orientadorNome ? $this->orientadorExisteNoBanco($orientadorNome) : false;
                $this->registrarItem(
                    $analise, 'Orientador existe no banco', 'capa',
                    $orientadorValido ? 'conforme' : 'pendente',
                    $orientadorNome ?: null,
                    1, true, true,
                    $orientadorValido
                        ? 'O orientador informado na capa foi localizado entre os docentes cadastrados.'
                        : 'Orientador informado na capa não localizado no banco: '
                          . ($orientadorNome ?: 'não informado') . '.'
                );

                $titulo = $dadosCapa['titulo'];
                $this->registrarItem(
                    $analise, 'Título localizado na capa', 'capa',
                    $titulo ? 'conforme' : 'pendente',
                    $titulo ?: null,
                    1, true, true,
                    $titulo ? 'Título localizado na capa.' : 'Não foi possível localizar o título na capa.'
                );

                $tituloTamanhoOk = $titulo && mb_strlen($titulo) <= 150;
                $this->registrarItem(
                    $analise, 'Título com até 150 caracteres', 'capa',
                    $tituloTamanhoOk ? 'conforme' : 'pendente',
                    $titulo ? $titulo . ' [' . mb_strlen($titulo) . ' caracteres]' : null,
                    1, true, true,
                    $tituloTamanhoOk
                        ? 'Título dentro do limite de 150 caracteres.'
                        : 'Título ausente ou acima do limite de 150 caracteres.'
                );

                $subtitulo = $dadosCapa['subtitulo'];
                if ($subtitulo) {
                    $subtituloOk = mb_strlen($subtitulo) <= 80;
                    $this->registrarItem(
                        $analise, 'Subtítulo com até 80 caracteres', 'capa',
                        $subtituloOk ? 'conforme' : 'pendente',
                        $subtitulo . ' [' . mb_strlen($subtitulo) . ' caracteres]',
                        1, true, true,
                        $subtituloOk
                            ? 'Subtítulo dentro do limite de 80 caracteres.'
                            : 'Subtítulo acima do limite de 80 caracteres.'
                    );
                } else {
                    $this->registrarItem(
                        $analise, 'Subtítulo com até 80 caracteres', 'capa',
                        'nao_aplicavel', null, 1, false, false,
                        'Nenhum subtítulo foi identificado na capa.'
                    );
                }

                $temInstituicao = $this->textoContem($textoPagina1, [
                    'UNEB',
                    'Universidade do Estado da Bahia',
                    'PPGMSB',
                    'Programa de Pós-Graduação',
                    'Modelagem e Simulação de Biossistemas',
                ]);
                $this->registrarItem(
                    $analise, 'Identificação institucional na capa', 'capa',
                    $temInstituicao ? 'conforme' : 'atencao',
                    $temInstituicao ? 'Foram localizados elementos textuais institucionais na capa.' : null,
                    1, true, false,
                    $temInstituicao
                        ? 'A capa possui identificação textual institucional.'
                        : 'Não foi possível validar os logotipos por extração textual. Validação visual necessária.'
                );

                // ── Fase 2: Documento completo via checklist ──────────────────
                if ($checklist) {
                    $this->analisarItensChecklist($analise, $checklist, $textosPorPagina);
                }

                $this->consolidarAnalise($analise);

                $documento->update([
                    'paginas_total'  => count($paginas),
                    'status_analise' => 'concluida',
                ]);

                return $analise->fresh(['itens']);
            });

            // Cria o registro Ollama em estado pendente.
            // O Ollama será chamado via AJAX pelo browser após o redirect,
            // sem depender de queue worker no servidor.
            DocumentoAnaliseOllama::create([
                'documento_analise_id' => $analise->id,
                'modelo'               => config('ollama.model'),
                'status'               => 'processando',
            ]);

            return $analise;
        } catch (Throwable $e) {
            return $this->registrarFalha($documento, 'Falha ao analisar o PDF: ' . $e->getMessage());
        }
    }

    // ── Fase 2: Análise estrutural via checklist ──────────────────────────────

    private function localizarChecklist(TrabalhoDocumento $documento): ?DocumentoChecklist
    {
        $trabalho = $documento->trabalhoAcademico;

        return DocumentoChecklist::query()
            ->where('ativo', true)
            ->where('tipo_documento', $documento->tipo_documento)
            ->where(function ($q) use ($trabalho) {
                $q->where('nivel', 'ambos')->orWhere('nivel', $trabalho->nivel);
            })
            ->where(function ($q) use ($trabalho) {
                $q->whereNull('programa_id')->orWhere('programa_id', $trabalho->programa_id);
            })
            ->orderByDesc('programa_id')
            ->orderByDesc('id')
            ->first();
    }

    private function extrairTextosPorPagina(array $paginas): array
    {
        $textos = [];
        foreach ($paginas as $indice => $pagina) {
            $textos[$indice + 1] = trim($pagina->getText());
        }
        return $textos;
    }

    private function analisarItensChecklist(
        DocumentoAnalise $analise,
        DocumentoChecklist $checklist,
        array $textosPorPagina
    ): void {
        // Grupos já cobertos pela análise de capa (Fase 1)
        $gruposIgnorados = ['capa', 'tecnico', 'imagens'];

        $itens = $checklist->itens()
            ->where('ativo', true)
            ->whereNotIn('grupo', $gruposIgnorados)
            ->orderBy('ordem_esperada')
            ->get();

        foreach ($itens as $item) {
            $resultado = $this->verificarItem($item, $textosPorPagina);

            DocumentoAnaliseItem::create([
                'documento_analise_id'        => $analise->id,
                'documento_checklist_item_id' => $item->id,
                'nome_item'                   => $item->nome,
                'grupo'                       => $item->grupo,
                'status'                      => $resultado['status'],
                'obrigatorio'                 => $item->obrigatorio,
                'impeditivo'                  => $item->impeditivo,
                'evidencia'                   => $resultado['evidencia'],
                'pagina_detectada'            => $resultado['pagina_detectada'],
                'termos_encontrados'          => $resultado['termos_encontrados'],
                'mensagem'                    => $resultado['mensagem'],
            ]);
        }
    }

    private function verificarItem($item, array $textosPorPagina): array
    {
        $termos = $item->termos_busca ?? [];

        if (empty($termos)) {
            return [
                'status'             => $item->status_se_ausente,
                'evidencia'          => null,
                'pagina_detectada'   => null,
                'termos_encontrados' => [],
                'mensagem'           => 'Nenhum termo de busca configurado para este item.',
            ];
        }

        $busca = $this->buscarTermosNoDocumento($item, $termos, $textosPorPagina);

        if (! empty($busca['termos_encontrados'])) {
            return [
                'status'             => 'conforme',
                'evidencia'          => $busca['evidencia'],
                'pagina_detectada'   => $busca['pagina_detectada'],
                'termos_encontrados' => array_values(array_unique($busca['termos_encontrados'])),
                'mensagem'           => 'Item localizado no documento.',
            ];
        }

        return [
            'status'             => $item->status_se_ausente,
            'evidencia'          => null,
            'pagina_detectada'   => null,
            'termos_encontrados' => [],
            'mensagem'           => 'Item não localizado no documento.',
        ];
    }

    private function buscarTermosNoDocumento($item, array $termos, array $textosPorPagina): array
    {
        // Metadados devem estar no final do documento
        if ($item->tipo_regra === 'metadados_final') {
            return $this->buscarNasUltimasPaginas($termos, $textosPorPagina);
        }

        // Classe LaTeX: busca simples em qualquer página
        if ($item->tipo_regra === 'classe_latex') {
            return $this->buscarOcorrenciaSimples($termos, $textosPorPagina);
        }

        $nomeNorm = $this->normalizarBusca($item->nome);

        // Palavras-chave e Keywords: rótulo seguido de conteúdo
        if (in_array($nomeNorm, ['palavras-chave', 'palavras chave', 'keywords'], true)) {
            return $this->buscarRotuloComConteudo($termos, $textosPorPagina);
        }

        // Sumário: aceita aparecer também na própria página do sumário
        if ($nomeNorm === 'sumario') {
            return $this->buscarTituloComConteudo($termos, $textosPorPagina, ignorarSumario: false);
        }

        // Demais itens: título de seção/capítulo com conteúdo após, fora do sumário
        return $this->buscarTituloComConteudo($termos, $textosPorPagina, ignorarSumario: true);
    }

    // ── Estratégias de busca ──────────────────────────────────────────────────

    private function buscarOcorrenciaSimples(array $termos, array $textosPorPagina): array
    {
        foreach ($textosPorPagina as $numeroPagina => $textoPagina) {
            foreach ($termos as $termo) {
                if ($this->textoContem($textoPagina, [$termo])) {
                    return [
                        'termos_encontrados' => [$termo],
                        'pagina_detectada'   => $numeroPagina,
                        'evidencia'          => $this->extrairTrechoEvidencia($textoPagina, $termo),
                    ];
                }
            }
        }

        return ['termos_encontrados' => [], 'pagina_detectada' => null, 'evidencia' => null];
    }

    private function buscarRotuloComConteudo(array $termos, array $textosPorPagina, int $minimoConteudo = 5): array
    {
        $encontradoSemConteudo = null;

        foreach ($textosPorPagina as $numeroPagina => $textoPagina) {
            if ($this->paginaEhSumario($textoPagina)) {
                continue;
            }

            foreach ($termos as $termo) {
                if (! $this->textoContem($textoPagina, [$termo])) {
                    continue;
                }

                $conteudoDepois = $this->textoDepoisDoTermo($textoPagina, $termo);

                if ($this->temConteudoMinimo($conteudoDepois, $minimoConteudo)) {
                    return [
                        'termos_encontrados' => [$termo],
                        'pagina_detectada'   => $numeroPagina,
                        'evidencia'          => $this->extrairTrechoEvidencia($textoPagina, $termo),
                    ];
                }

                // Rótulo existe mas sem conteúdo suficiente (ex: template com placeholder)
                if ($encontradoSemConteudo === null) {
                    $encontradoSemConteudo = [
                        'termos_encontrados' => [$termo],
                        'pagina_detectada'   => $numeroPagina,
                        'evidencia'          => $this->extrairTrechoEvidencia($textoPagina, $termo),
                    ];
                }
            }
        }

        return $encontradoSemConteudo
            ?? ['termos_encontrados' => [], 'pagina_detectada' => null, 'evidencia' => null];
    }

    private function detectarRangeDoSumario(array $textosPorPagina): array
    {
        $resultado = [];
        $ultimaSumario = null;

        foreach ($textosPorPagina as $num => $texto) {
            if ($this->paginaEhSumario($texto)) {
                $resultado[] = $num;
                $ultimaSumario = $num;
            } elseif ($ultimaSumario !== null && $num === $ultimaSumario + 1) {
                // Página imediatamente após o sumário: se começa com entrada numerada,
                // é continuação do índice (sumário de 2+ páginas)
                $primeiraLinha = trim(strtok(trim($texto), "\n") ?: '');
                if (preg_match('/^\d+(?:\.\d+)*\s+\w/u', $primeiraLinha)) {
                    $resultado[] = $num;
                    $ultimaSumario = $num;
                } else {
                    $ultimaSumario = null;
                }
            } else {
                $ultimaSumario = null;
            }
        }

        return $resultado;
    }

    private function buscarTituloComConteudo(array $termos, array $textosPorPagina, bool $ignorarSumario): array
    {
        $paginasSumario = $ignorarSumario ? $this->detectarRangeDoSumario($textosPorPagina) : [];

        foreach ($textosPorPagina as $numeroPagina => $textoPagina) {
            if ($ignorarSumario && in_array($numeroPagina, $paginasSumario, true)) {
                continue;
            }

            $linhas = preg_split('/\R+/', $textoPagina);

            if (! $linhas) {
                continue;
            }

            foreach ($linhas as $indice => $linha) {
                foreach ($termos as $termo) {
                    if (! $this->linhaPareceTitulo($linha, $termo)) {
                        continue;
                    }

                    $conteudoDepois = implode("\n", array_slice($linhas, $indice + 1));

                    if (isset($textosPorPagina[$numeroPagina + 1])) {
                        $conteudoDepois .= "\n" . $textosPorPagina[$numeroPagina + 1];
                    }

                    // Mínimo reduzido (30 chars) para aceitar seções curtas como Cronograma
                    if ($this->temConteudoMinimo($conteudoDepois, 30)) {
                        return [
                            'termos_encontrados' => [$termo],
                            'pagina_detectada'   => $numeroPagina,
                            'evidencia'          => $this->extrairTrechoEvidencia($textoPagina, $termo),
                        ];
                    }
                }
            }
        }

        return ['termos_encontrados' => [], 'pagina_detectada' => null, 'evidencia' => null];
    }

    private function buscarNasUltimasPaginas(array $termos, array $textosPorPagina, int $numeroPaginas = 4): array
    {
        $chaves       = array_keys($textosPorPagina);
        $ultimasChaves = array_slice($chaves, max(0, count($chaves) - $numeroPaginas));

        foreach ($ultimasChaves as $numeroPagina) {
            $textoPagina = $textosPorPagina[$numeroPagina];

            foreach ($termos as $termo) {
                if ($this->textoContem($textoPagina, [$termo])) {
                    return [
                        'termos_encontrados' => [$termo],
                        'pagina_detectada'   => $numeroPagina,
                        'evidencia'          => $this->extrairTrechoEvidencia($textoPagina, $termo),
                    ];
                }
            }
        }

        return ['termos_encontrados' => [], 'pagina_detectada' => null, 'evidencia' => null];
    }

    // ── Auxiliares de detecção ────────────────────────────────────────────────

    private function linhaPareceTitulo(string $linha, string $termo): bool
    {
        $linha = trim($this->normalizarBusca($linha));
        $termo = trim($this->normalizarBusca($termo));

        if ($linha === '' || $termo === '' || mb_strlen($linha) > 180) {
            return false;
        }

        $termoRegex = preg_quote($termo, '/');

        // Aceita: "Introdução", "1 Introdução", "1.3 Objetivos", "1.3.1 Geral"
        return (bool) preg_match(
            '/^(?:\d+(?:\.\d+)*\s+)?' . $termoRegex . '(?:\s*[:\-–—].*)?$/u',
            $linha
        );
    }

    private function paginaEhSumario(string $textoPagina): bool
    {
        return str_contains($this->normalizarBusca($textoPagina), 'sumario');
    }

    private function textoDepoisDoTermo(string $texto, string $termo): string
    {
        $posicao = mb_stripos($texto, $termo);

        if ($posicao === false) {
            return '';
        }

        return mb_substr($texto, $posicao + mb_strlen($termo));
    }

    private function temConteudoMinimo(string $texto, int $minimoCaracteres): bool
    {
        $texto = $this->normalizarBusca($texto);
        $texto = preg_replace('/\b[ivxlcdm]+\b/i', '', $texto);
        $texto = preg_replace('/\b\d+\b/', '', $texto);
        $texto = trim($texto);

        if (mb_strlen($texto) < $minimoCaracteres) {
            return false;
        }

        return preg_match('/[a-zA-Z]{4,}/', $texto) === 1;
    }

    private function extrairTrechoEvidencia(string $texto, string $termo): ?string
    {
        $posicao = mb_stripos($texto, $termo);

        if ($posicao === false) {
            return mb_substr(trim($texto), 0, 200);
        }

        $inicio = max($posicao - 80, 0);

        return trim(mb_substr($texto, $inicio, 220));
    }

    // ── Análise de Capa ───────────────────────────────────────────────────────

    private function extrairDadosDaCapa(string $textoPagina1, string $nomeUsuario): array
    {
        $linhas = collect(preg_split('/\R+/', $textoPagina1))
            ->map(fn ($linha) => trim($linha))
            ->filter()
            ->values()
            ->all();

        $tipoDocumento = null;
        $orientador    = null;
        $email         = null;

        foreach ($linhas as $linha) {
            if (! $tipoDocumento && $this->textoContem($linha, [
                'EXAME DE QUALIFICAÇÃO', 'QUALIFICAÇÃO', 'DEFESA', 'DISSERTAÇÃO',
            ])) {
                $tipoDocumento = $linha;
            }

            if (! $orientador && preg_match('/^orientador(?:a)?\s*:\s*(.+)$/i', $linha, $matches)) {
                $orientador = trim($matches[1]);
            }

            if (! $email && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $linha, $matches)) {
                $email = trim($matches[0]);
            }
        }

        [$titulo, $subtitulo] = $this->extrairTituloESubtitulo($linhas, $tipoDocumento, $nomeUsuario);

        return [
            'tipo_documento' => $tipoDocumento,
            'orientador'     => $orientador,
            'email'          => $email,
            'titulo'         => $titulo,
            'subtitulo'      => $subtitulo,
        ];
    }

    private function extrairTituloESubtitulo(array $linhas, ?string $tipoDocumento, string $nomeUsuario): array
    {
        $indiceInicio = 0;

        if ($tipoDocumento) {
            foreach ($linhas as $indice => $linha) {
                if ($linha === $tipoDocumento) {
                    $indiceInicio = $indice + 1;
                    break;
                }
            }
        }

        $candidatas = [];

        for ($i = $indiceInicio; $i < count($linhas); $i++) {
            $linha = trim($linhas[$i]);

            if ($linha === '') {
                continue;
            }

            if ($this->textoContem($linha, [
                $nomeUsuario,
                'Área de conhecimento',
                'Área de Concentração',
                'Linha de Pesquisa',
                'Orientador:',
                'Coorientador:',
                'Universidade do Estado da Bahia',
                'Programa de Pós-Graduação',
            ])) {
                break;
            }

            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $linha)) {
                break;
            }

            $candidatas[] = $linha;
        }

        if (empty($candidatas)) {
            return [null, null];
        }

        $tituloLinhas   = [];
        $subtituloLinhas = [];
        $entrouSubtitulo = false;

        foreach ($candidatas as $linha) {
            $primeiraLetra = mb_substr(trim($linha), 0, 1);

            if (! $entrouSubtitulo && preg_match('/^[a-záéíóúâêôãõç]/u', $primeiraLetra)) {
                $entrouSubtitulo = true;
            }

            if ($entrouSubtitulo) {
                $subtituloLinhas[] = $linha;
            } else {
                $tituloLinhas[] = $linha;
            }
        }

        $titulo    = trim(implode(' ', $tituloLinhas));
        $subtitulo = trim(implode(' ', $subtituloLinhas));

        if ($titulo === '') {
            $titulo    = trim(implode(' ', $candidatas));
            $subtitulo = '';
        }

        return [
            $titulo !== '' ? $titulo : null,
            $subtitulo !== '' ? $subtitulo : null,
        ];
    }

    private function orientadorExisteNoBanco(string $nomeOrientador): bool
    {
        $nomeNorm = $this->normalizarBusca($nomeOrientador);

        return DocenteProfile::query()
            ->with('user')
            ->where('ativo', true)
            ->get()
            ->contains(function ($docente) use ($nomeNorm) {
                $nomeDocente = $docente->user?->name;
                return $nomeDocente && $this->normalizarBusca($nomeDocente) === $nomeNorm;
            });
    }

    // ── Persistência ─────────────────────────────────────────────────────────

    private function registrarItem(
        DocumentoAnalise $analise,
        string $nome,
        string $grupo,
        string $status,
        ?string $evidencia,
        ?int $pagina,
        bool $obrigatorio,
        bool $impeditivo,
        ?string $mensagem
    ): void {
        DocumentoAnaliseItem::create([
            'documento_analise_id'        => $analise->id,
            'documento_checklist_item_id' => null,
            'nome_item'                   => $nome,
            'grupo'                       => $grupo,
            'status'                      => $status,
            'obrigatorio'                 => $obrigatorio,
            'impeditivo'                  => $impeditivo,
            'evidencia'                   => $evidencia,
            'pagina_detectada'            => $pagina,
            'termos_encontrados'          => [],
            'mensagem'                    => $mensagem,
        ]);
    }

    private function consolidarAnalise(DocumentoAnalise $analise): void
    {
        $itens = $analise->itens()->get();

        $total         = $itens->count();
        $conformes     = $itens->where('status', 'conforme')->count();
        $atencao       = $itens->where('status', 'atencao')->count();
        $pendentes     = $itens->where('status', 'pendente')->count();
        $naoAplicaveis = $itens->where('status', 'nao_aplicavel')->count();

        $itensConsiderados = max($total - $naoAplicaveis, 1);
        $percentual        = (int) round(($conformes / $itensConsiderados) * 100);

        $temPendenteImpeditivo = $itens->where('impeditivo', true)->where('status', 'pendente')->isNotEmpty();

        $statusGeral = 'conforme';
        if ($temPendenteImpeditivo || $pendentes > 0) {
            $statusGeral = 'com_pendencias';
        } elseif ($atencao > 0) {
            $statusGeral = 'com_alertas';
        }

        $mensagem = match ($statusGeral) {
            'conforme'       => 'Documento formalmente conforme com os critérios verificados.',
            'com_alertas'    => 'Documento sem pendências impeditivas, mas com alertas formais.',
            'com_pendencias' => 'Documento possui pendências formais que devem ser verificadas.',
            default          => 'Análise concluída.',
        };

        $analise->update([
            'status_geral'            => $statusGeral,
            'percentual_conformidade' => $percentual,
            'total_itens'             => $total,
            'itens_conformes'         => $conformes,
            'itens_atencao'           => $atencao,
            'itens_pendentes'         => $pendentes,
            'itens_nao_aplicaveis'    => $naoAplicaveis,
            'mensagem_geral'          => $mensagem,
        ]);
    }

    private function registrarFalha(TrabalhoDocumento $documento, string $mensagem): DocumentoAnalise
    {
        $documento->update(['status_analise' => 'falhou']);

        return DocumentoAnalise::create([
            'trabalho_documento_id'   => $documento->id,
            'status_geral'            => 'falha',
            'percentual_conformidade' => 0,
            'mensagem_geral'          => $mensagem,
            'analisado_em'            => Carbon::now(),
        ]);
    }

    // ── Normalização de texto ─────────────────────────────────────────────────

    private function textoContem(string $texto, array $termos): bool
    {
        $textoNorm = $this->normalizarBusca($texto);

        foreach ($termos as $termo) {
            if (str_contains($textoNorm, $this->normalizarBusca($termo))) {
                return true;
            }
        }

        return false;
    }

    private function normalizarBusca(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        // Mapeamento explícito de acentos — mais confiável que iconv TRANSLIT (comportamento varia por SO)
        $acentos  = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $simples  = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
        $texto = str_replace($acentos, $simples, $texto);

        return trim(preg_replace('/\s+/', ' ', $texto));
    }
}
