# Submissão do trabalho em LaTeX — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Objetivo:** permitir que o discente de um programa com template LaTeX envie o projeto `.zip`. O sistema compila, extrai os elementos vitais, confere com o cadastro, verifica as referências, passa pela homologação documental da Secretaria e envia à banca o PDF com linhas numeradas.

**Arquitetura:** serviços pequenos e independentes:
- `ProjetoLatexZipService`: extração segura do zip;
- `ExtratorElementosLatexService`: leitura dos elementos no fonte;
- `CompiladorLatex`: interface com a implementação `latexmk`;
- `ConformidadeCadastroService`: conferência com o cadastro;
- `LeitorBblService` + `VerificacaoReferenciasService`: leitura e verificação das referências.

Esses serviços são orquestrados por jobs em fila. A compilação fica atrás de uma interface, falsificada nos testes. As telas seguem o padrão Blade + Bootstrap do app.

**Stack:** Laravel 12, PHP 8.2, MySQL (testes em SQLite), `ZipArchive`, `latexmk` (MiKTeX no dev, TeX Live na produção), OpenAlex/Crossref via `Http`, Ollama (`config('ollama.*')`).

**Spec:** `docs/superpowers/specs/2026-10-26-submissao-latex-design.md`. Ler antes de começar; as decisões D1–D12 citadas aqui estão lá.

## Restrições globais

- Nomes em português; rotas `recurso.acao` dentro de `['auth','verified']`; autorização por `users.categoria` no controller (sem gates/policies).
- Telas da Secretaria filtram pelo programa dela (`$this->programaDaSecretaria()` / `garantirProgramaDaSecretaria()`).
- Arquivos em `storage/app/private/` (disco `local`); downloads por rota autenticada ou URL assinada.
- Nomes de índices e FKs com até 64 caracteres (MySQL). Para nomes longos, use `constrained(..., indexName: '...')` e índices nomeados.
- Testes não dependem de LaTeX nem de rede: `CompiladorLatex` falsificado e `Http::fake()`. O único teste com compilação real é marcado com `@group latex` e chama `markTestSkipped` quando não há `latexmk` no PATH.
- Bloqueiam o "ok" (regra 7 da spec): não compilar; ausência de título PT, resumo PT, abstract EN, palavras-chave PT, keywords EN, nomes dos membros da banca, nome do orientador e nome do coorientador (se o trabalho tiver coorientador ativo no app). As exceções de qualificação e de trabalho em inglês estão na D10.
- Referência não encontrada = `nao_localizada` ("não localizada — verificar"); falha de rede = `nao_verificada`. Nunca "inexistente".
- Atualidade (D8): conta só referências com ano conhecido; "recente" quando `ano >= ano_envio - 1`; "atualizadas" quando o percentual for maior que 70.
- Compilação: `-no-shell-escape`, `-interaction=nonstopmode`, `-halt-on-error`, tempo limite de 180 s, diretório temporário próprio. Zip: rejeitar caminho absoluto ou com `..`; no máximo 2000 arquivos e 200 MB descompactados; ignorar `__MACOSX/`, `.DS_Store` e `desktop.ini`.

## Pontos de atenção na revisão

1. **Zip com o projeto dentro de uma pasta** (`classppgmsb/dissertacao.tex`, como o template real): o `.tex` principal deve ser encontrado em subpasta. O teste fica na Tarefa 2.
2. **Nomes com título acadêmico e acentos** ("Profa. Dra. Ana Paula Menezes" × "Ana Paula Menezes"; "Gildásio" × "Gildasio") devem bater, sem divergência. O teste fica na Tarefa 6.
3. **Reenvio após "não ok" ou devolução**: a nova submissão substitui a anterior como a vigente, sem apagar o histórico, e só a vigente pode ir à homologação. O teste fica na Tarefa 7.
4. **OpenAlex fora do ar**: as referências ficam `nao_verificada` e a submissão continua utilizável. O teste fica na Tarefa 8.
5. **Template sem algum elemento no mapa**, por exemplo uma classe antiga sem comando para a banca: a extração usa as regras genéricas, e o que não for achado aparece como "não encontrado". Nada pode quebrar. O teste fica na Tarefa 3.

---

### Tarefa 1: Desligar o RAG e a análise de resumo por IA (D5, D6, D7)

**Arquivos:**
- Criar: `config/ia.php`
- Modificar: `app/Http/Controllers/TrabalhoDocumentoController.php` (bloco do `store` que dispara `AnalisarConformidadeRagJob`), `app/Services/DocumentoAnaliseService.php` (criação do `DocumentoAnaliseOllama`, por volta da linha 229), `app/Http/Controllers/ProgramaDocumentoReferenciaController.php`, `resources/views/meu-trabalho/analise.blade.php` (bloco do Ollama só se `$analise->ollama` existir), `.env.example`
- Teste: `tests/Feature/IaDesligadaTest.php`; ajustar `tests/Feature/EnvioManuscritoDisparaConformidadeRagTest.php` e `tests/Feature/ProgramaDocumentoReferenciaControllerTest.php` para ligar a flag em `setUp` (`config(['ia.conformidade_rag' => true])`)

**Interfaces:**
- Produz: `config('ia.conformidade_rag')` (env `IA_CONFORMIDADE_RAG`, padrão `false`) e `config('ia.analise_resumo')` (env `IA_ANALISE_RESUMO`, padrão `false`).

- [ ] **Passo 1: testes que falham** em `IaDesligadaTest`:
  - `test_envio_pdf_nao_dispara_rag_com_flag_desligada`: `Queue::fake()`, normas `concluido`, POST `meu-trabalho.store` → `Queue::assertNotPushed(AnalisarConformidadeRagJob::class)`;
  - `test_envio_pdf_nao_cria_analise_ollama_com_flag_desligada`: depois do POST, `DocumentoAnaliseOllama::count() === 0`;
  - `test_tela_de_normas_responde_404_com_flag_desligada`: secretaria GET `programa-documentos-referencia.index` → 404.
- [ ] **Passo 2:** `php artisan test --filter=IaDesligadaTest` → FALHA.
- [ ] **Passo 3: implementar.** Ler as flags nos três pontos. No controller de referência, `abort_unless(config('ia.conformidade_rag'), 404)` em `index` e `store`. Na view de análise, o bloco de IA só aparece se `$analise->ollama` não for nulo.
- [ ] **Passo 4:** `php artisan test` → toda a suíte PASSA.
- [ ] **Passo 5: commit** `feat: desliga RAG de conformidade e análise de resumo por IA (flags em config/ia.php)`.

### Tarefa 2: Extração segura do projeto LaTeX

**Arquivos:**
- Criar: `app/Services/Latex/ProjetoLatexZipService.php`, `app/Services/Latex/ProjetoLatex.php` (objeto de valor), `app/Exceptions/ProjetoLatexInvalidoException.php`
- Teste: `tests/Unit/ProjetoLatexZipServiceTest.php` (monta zips com `ZipArchive` num diretório temporário)

**Interfaces:**
- Produz:
  - `ProjetoLatexZipService::extrair(string $zipPath, string $destino, ?string $principalPreferido = null): ProjetoLatex`. Lança `ProjetoLatexInvalidoException` com mensagem em português.
  - `ProjetoLatex` com `public readonly string $raiz` (diretório onde está o `.tex` principal), `public readonly string $texPrincipal` (nome do arquivo relativo à raiz), `fonteExpandido(): string` (fonte com `\input{}`/`\include{}` substituídos recursivamente; profundidade máxima 20; extensão `.tex` implícita; arquivo ausente vira linha vazia) e `classeDocumento(): ?string`.

- [ ] **Passo 1: testes que falham:**
  - `test_extrai_projeto_em_subpasta_e_acha_principal` (zip com `proj/main.tex` + `proj/cap/intro.tex` → `texPrincipal === 'main.tex'` e `raiz` termina em `proj`);
  - `test_rejeita_entrada_com_ponto_ponto` (`../evil.tex` → exceção);
  - `test_rejeita_caminho_absoluto`;
  - `test_rejeita_zip_sem_documentclass` (mensagem contém "Nenhum arquivo .tex principal");
  - `test_ambiguidade_resolvida_por_nome_preferido` (dois arquivos com `\documentclass`, preferido `dissertacao.tex` → escolhe esse);
  - `test_ambiguidade_sem_preferencia_gera_erro` (`a.tex` e `b.tex` → mensagem contém "mais de um");
  - `test_fonte_expandido_inclui_input_e_include`;
  - `test_ignora_macosx_e_ds_store`;
  - `test_classe_documento_lida_do_principal` (`\documentclass[diss]{classPPGMSB}` → `'classPPGMSB'`).
- [ ] **Passo 2:** `php artisan test --filter=ProjetoLatexZipServiceTest` → FALHA.
- [ ] **Passo 3: implementar.** Principal = arquivo com `\documentclass` e `\begin{document}` (ignorando linhas comentadas com `%`). Desempate, nesta ordem: `$principalPreferido`, `main.tex`, `dissertacao.tex`, `tese.tex`; se continuar ambíguo, exceção. Aplicar os limites das restrições globais.
- [ ] **Passo 4:** os testes PASSAM.
- [ ] **Passo 5: commit** `feat: extração segura do projeto LaTeX enviado em zip`.

### Tarefa 3: Extração dos elementos vitais (D1)

**Arquivos:**
- Criar: `app/Services/Latex/ExtratorElementosLatexService.php`, `app/Services/Latex/ElementosVitais.php` (constantes dos códigos)
- Fixtures: `tests/fixtures/latex/abntex2/main.tex` (abnTeX2 mínimo: `\titulo{}`, `\autor{}`, `\orientador{}`, `\coorientador{}`, `\begin{resumo}...\textbf{Palavras-chave}: a. b.\end{resumo}`, `\begin{resumo}[Abstract]...\textbf{Keywords}: x. y.\end{resumo}`) e `tests/fixtures/latex/article/main.tex` (`\title{}`, `\author{}`, `\begin{abstract}`, sem banca). O caso do PPGMSB usa `docs/classppgmsb.zip` (`classppgmsb/dissertacao.tex`), extraído pela Tarefa 2.
- Teste: `tests/Unit/ExtratorElementosLatexServiceTest.php`

**Interfaces:**
- Consome: `ProjetoLatex::fonteExpandido()`.
- Produz:
  - `ElementosVitais` com as constantes `TITULO_PT`, `TITULO_EN`, `RESUMO_PT`, `PALAVRAS_CHAVE_PT`, `ABSTRACT_EN`, `PALAVRAS_CHAVE_EN`, `AUTOR`, `ORIENTADOR`, `COORIENTADORES`, `MEMBROS_BANCA`, `DATA_DEFESA`, `LOCAL_DEFESA` (valores `'titulo_pt'`, …).
  - `ExtratorElementosLatexService::descobrirMapa(string $fonte): array`, no formato `[codigo => ['tipo' => 'comando'|'ambiente'|'ambiente_opcao'|'secao'|'padrao_comando', 'nome' => string, 'opcao'? => string]]`. Só regras, sem IA.
  - `ExtratorElementosLatexService::extrair(string $fonte, ?array $mapa = null): array`, no formato `[codigo => string|array<string>|null]`. `COORIENTADORES` e `MEMBROS_BANCA` são listas de nomes; ausente = `null`. Com `$mapa`, ele é usado primeiro e as regras genéricas servem de reserva para os códigos não resolvidos.

- [ ] **Passo 1: testes que falham:**
  - `test_extrai_ppgmsb`: título PT começa com "Este é um título"; título EN começa com "This is a dissertation"; `palavras_chave_pt === 'modelagem; simulação; otimização'`; abstract começa com "With the marvelous"; orientador `'Antonio Guimarães Rosa'`; `membros_banca` contém `'Profa. Helena Souza Lima'` e tem 5 itens; `data_defesa === '01/01/2023'`; `local_defesa === 'Alagoinhas/BA'`.
  - `test_extrai_abntex2`: resumo, abstract (de `resumo[Abstract]`), palavras-chave PT e keywords EN tirados de dentro dos ambientes; `orientador` e `coorientadores` preenchidos.
  - `test_extrai_article_sem_banca`: `membros_banca === null` e `orientador === null`, sem exceção.
  - `test_descobrir_mapa_ppgmsb` → `mapa['resumo_pt'] === ['tipo'=>'ambiente','nome'=>'resumo']`, `mapa['abstract_en']['nome'] === 'newAbstract'`, `mapa['membros_banca'] === ['tipo'=>'padrao_comando','nome'=>'Membro[A-Z]Nome']`.
  - `test_ignora_comentarios`: `% \TituloBR{falso}` antes do real não é extraído.
  - `test_mapa_com_codigo_inexistente_cai_nas_regras`.
- [ ] **Passo 2:** `php artisan test --filter=ExtratorElementosLatexServiceTest` → FALHA.
- [ ] **Passo 3: implementar.**
  - Remover comentários (`%` não escapado) antes de tudo.
  - Ler argumentos com chaves balanceadas.
  - Regras: nomes de comando sem diferenciar maiúsculas, para cada código (ex.: `TITULO_PT`: `TituloBR|titulo|tituloPT`; `TITULO_EN`: `TituloEN|title|tituloestrangeiro|titleEN`; `AUTOR`: `NomeAutor|autor|author`; `ORIENTADOR`: `OrientadorNome|orientador|advisor|supervisor`; coorientadores: `Coorientador[A-Z]?Nome|coorientador|coadvisor`; membros: `Membro[A-Z]Nome|avaliador[A-Z]?|examinador[A-Z]?`; `DATA_DEFESA`: `DataDefesa|dataDefesa|data`; `LOCAL_DEFESA`: `LocalDefesa|localDefesa|local`).
  - Ambientes: `resumo` → resumo PT; `abstract`/`newAbstract`/`resumo[Abstract]` → abstract EN.
  - Seção como reserva: `\chapter*{Resumo}`/`{Abstract}` até o próximo `\chapter`.
  - Palavras-chave dentro do ambiente por regex `Palavras-chave|Keywords` seguido de `:`.
  - Normalizar espaços; remover `\textbf` e afins só no rótulo.
- [ ] **Passo 4:** os testes PASSAM.
- [ ] **Passo 5: commit** `feat: extrator genérico de elementos vitais do fonte LaTeX`.

### Tarefa 4: Template LaTeX do programa (tela da Secretaria)

**Arquivos:**
- Criar: migration `database/migrations/2026_10_26_100000_create_programa_templates_latex_table.php`, `app/Models/ProgramaTemplateLatex.php`, `app/Http/Controllers/ProgramaTemplateLatexController.php`, `app/Services/Latex/MapaTemplateOllamaService.php`, `resources/views/programas/template-latex.blade.php`
- Modificar: `app/Models/Programa.php` (`templateLatex(): HasOne`), `routes/web.php`, `resources/views/layouts/app.blade.php` (o item de menu da base de conhecimento passa a apontar para `programa-template.index`, com o rótulo "Template LaTeX")
- Teste: `tests/Feature/ProgramaTemplateLatexTest.php`

**Interfaces:**
- Consome: `ProjetoLatexZipService::extrair`, `ExtratorElementosLatexService::descobrirMapa`.
- Produz:
  - tabela `programa_templates_latex`: `programa_id` (unique, FK), `arquivo_path`, `arquivo_nome_original`, `hash_sha256`, `tex_principal`, `classe_documento` (nullable), `motor` enum `pdflatex|xelatex|lualatex` (padrão `pdflatex`), `mapa_elementos` json, `mapa_confirmado` bool (padrão false), `enviado_por` FK users, `enviado_em`.
  - `Programa::templateLatex()`; `Programa::temTemplateLatex(): bool`, que exige `mapa_confirmado`.
  - Rotas: `programa-template.index` (GET), `programa-template.store` (POST, `.zip` até 50 MB), `programa-template.mapa` (PUT, confirma/edita o mapa e o motor), `programa-template.download` (GET; Secretaria do programa ou discente com vínculo no programa).
  - `MapaTemplateOllamaService::sugerir(string $fonte, array $codigosFaltantes): array`, no mesmo formato do mapa. Chamado só para os códigos que as regras não resolveram; erro de rede ou JSON inválido devolve `[]`.

- [ ] **Passo 1: testes que falham:**
  - `test_secretaria_envia_template_e_ve_mapa_descoberto` (`Storage::fake`, zip de `docs/classppgmsb.zip` → registro criado, `mapa_elementos['resumo_pt']['nome'] === 'resumo'`, `mapa_confirmado === false`; a tela mostra "Resumo" e "resumo");
  - `test_secretaria_confirma_mapa` (PUT → `mapa_confirmado === true`, `motor` salvo);
  - `test_zip_invalido_mostra_erro` (zip sem `.tex` → erro de validação com a mensagem da exceção);
  - `test_ollama_sugere_codigos_faltantes` (`Http::fake` devolvendo JSON de mapa para `membros_banca` num template article → mapa contém a sugestão);
  - `test_tela_nao_mostra_normas` (`assertDontSee('Normas e Procedimentos')`);
  - `test_docente_nao_acessa` (403);
  - `test_secretaria_de_outro_programa_nao_ve_template` (isolamento);
  - `test_discente_do_programa_baixa_template`.
- [ ] **Passo 2:** `php artisan test --filter=ProgramaTemplateLatexTest` → FALHA.
- [ ] **Passo 3: implementar** controller, model, migration e view. Texto da tela: "Template LaTeX do programa"; explicação: "Com o template cadastrado, os discentes enviam o projeto LaTeX (.zip) e o sistema compila, extrai os elementos vitais e confere com o cadastro. Sem template, o programa usa o envio de PDF, sem análise por IA." Mapa em tabela editável (código → tipo/nome).
- [ ] **Passo 4:** os testes PASSAM; `php artisan migrate` no MySQL local roda sem erro.
- [ ] **Passo 5: commit** `feat: Secretaria cadastra o template LaTeX do programa e confirma o mapa de elementos`.

### Tarefa 5: Compilador LaTeX

**Arquivos:**
- Criar: `app/Services/Latex/CompiladorLatex.php` (interface), `app/Services/Latex/LatexmkCompilador.php`, `app/Services/Latex/ResultadoCompilacao.php`
- Criar: `config/latex.php` (`binario` env `LATEXMK_BIN` padrão `latexmk`, `timeout` 180)
- Modificar: `app/Providers/AppServiceProvider.php` (bind da interface → `LatexmkCompilador`)
- Teste: `tests/Unit/LatexmkCompiladorTest.php`

**Interfaces:**
- Produz:
  - `CompiladorLatex::compilar(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, ?string $apendiceTex = null): ResultadoCompilacao`.
  - `ResultadoCompilacao` com `public readonly bool $sucesso`, `?string $pdfPath` (absoluto), `?string $bblPath`, `string $logResumido` (até 80 linhas: as que começam com `!`, as seguintes com `l.<n>`, e os avisos `LaTeX Warning`/`Class .* Warning`/`Package .* Warning`) e `array $avisos` (lista das linhas `Class .* Warning`).
  - `LatexmkCompilador::montarComando(...)`: público, para teste. Usa `Symfony\Component\Process\Process` com um array de argumentos (nunca string de shell): `latexmk -pdf|-pdfxe|-pdflua -no-shell-escape -interaction=nonstopmode -halt-on-error -outdir=<dir> [-usepretex=<pretex>] <principal>`.
  - Numeração: pretex `\RequirePackage{lineno}\AtBeginDocument{\linenumbers}`. Apêndice: pretex `\AtEndDocument{\clearpage\input{<arquivo>}}`, com `$apendiceTex` gravado num arquivo `.tex` dentro de `outdir`.

- [ ] **Passo 1: testes que falham:**
  - `test_comando_usa_no_shell_escape_e_motor` (xelatex → contém `-pdfxe` e `-no-shell-escape`);
  - `test_comando_com_numeracao_inclui_lineno`;
  - `test_resume_log_com_erro` (log fixture com `! Undefined control sequence.` → `logResumido` contém essa linha; `sucesso` false);
  - `test_compila_template_real` (`@group latex`: pula sem `latexmk`; compila `docs/classppgmsb.zip` com numeração → `sucesso` true e `pdfPath` existe). Se o template não compilar no MiKTeX por pacote faltando, o teste mostra o log; instalar o pacote é ação manual, documentada na Tarefa 10.
- [ ] **Passo 2:** `php artisan test --filter=LatexmkCompiladorTest` → FALHA.
- [ ] **Passo 3: implementar.** Tempo limite → `sucesso` false com o log "Tempo limite de compilação excedido (180 s)." Se a compilação com numeração falhar e a sem numeração passar, a orquestração da Tarefa 7 trata (D4).
- [ ] **Passo 4:** os testes PASSAM (`php artisan test --group=latex` roda o real).
- [ ] **Passo 5: commit** `feat: compilador LaTeX via latexmk sem shell-escape`.

### Tarefa 6: Conformidade com o cadastro

**Arquivos:**
- Criar: `app/Services/Latex/ConformidadeCadastroService.php`, `app/Support/NomePessoa.php`
- Teste: `tests/Unit/NomePessoaTest.php`, `tests/Feature/ConformidadeCadastroServiceTest.php`

**Interfaces:**
- Consome: saída de `ExtratorElementosLatexService::extrair` e as constantes de `ElementosVitais`.
- Produz:
  - `NomePessoa::normalizar(string $nome): string`: sem acento, minúsculas, sem os títulos `prof|profa|professor|professora|dr|dra|doutor|doutora|me|msc|ma|phd|esp` (com ou sem ponto), "Sobrenome, Nome" → "nome sobrenome", espaços colapsados.
  - `NomePessoa::equivalentes(string $a, string $b): bool`: igualdade após normalizar.
  - `ConformidadeCadastroService::conferir(array $elementos, TrabalhoAcademico $trabalho, ?Banca $banca, string $tipoDocumento, string $idioma): array` → lista de itens `['codigo','rotulo','status' => 'ok'|'ausente'|'divergente'|'aviso','valor_tex','valor_sistema','bloqueia' => bool]`.
  - `ConformidadeCadastroService::bloqueia(array $itens): bool`.
- Regras:
  - Ausência bloqueia conforme as restrições globais e a D10. Qualificação: os elementos EN são `aviso`. `idioma === 'en'`: título, abstract e keywords EN obrigatórios; os PT viram `aviso`.
  - Coorientador obrigatório só se existir `Orientacao` ativa com `tipo` de coorientação no trabalho (confirmar o valor de `tipo` usado por `OrientacaoCoorientadoresTest`).
  - Divergência nunca bloqueia (D3). Compara: título PT × `trabalho.titulo` (normalizado sem acento/caixa), autor × nome do discente, orientador e coorientadores × `Orientacao`, membros × `banca.membros` (papel `avaliador`, nome de `docenteProgramaVinculo.docenteProfile.user.name`), data × `banca.data_hora` (d/m/Y) e local × `banca.local`. A divergência de membros lista os que estão em um lado e faltam no outro.

- [ ] **Passo 1: testes que falham:**
  - `NomePessoaTest`: `equivalentes('Profa. Dra. Ana Paula Menezes', 'ana paula menezes')` → true; `equivalentes('Gildásio Moreira', 'Gildasio Moreira')` → true; `equivalentes('Souza, Helena', 'Helena Souza')` → true; `equivalentes('Ana Paula', 'Ana Maria')` → false.
  - `ConformidadeCadastroServiceTest`:
    - `test_tudo_presente_e_igual_nao_bloqueia`;
    - `test_sem_resumo_bloqueia`;
    - `test_sem_membros_banca_bloqueia`;
    - `test_sem_coorientador_bloqueia_so_quando_ha_coorientador_no_app`;
    - `test_membro_divergente_nao_bloqueia_e_lista_diferencas`;
    - `test_qualificacao_sem_abstract_e_aviso`;
    - `test_idioma_en_inverte_obrigatorios`.
- [ ] **Passo 2:** `php artisan test --filter="NomePessoaTest|ConformidadeCadastroServiceTest"` → FALHA.
- [ ] **Passo 3: implementar.**
- [ ] **Passo 4:** os testes PASSAM.
- [ ] **Passo 5: commit** `feat: conformidade dos elementos extraídos com o cadastro do app`.

### Tarefa 7: Submissão do discente e processamento

**Arquivos:**
- Criar: migration `2026_10_26_110000_create_trabalho_submissoes_latex_table.php`, `app/Models/TrabalhoSubmissaoLatex.php`, `app/Jobs/ProcessarSubmissaoLatexJob.php`, `app/Http/Controllers/SubmissaoLatexController.php`, `resources/views/meu-trabalho/submissao-latex.blade.php`
- Modificar: `resources/views/meu-trabalho/index.blade.php` (com `temTemplateLatex()`, mostra o envio de `.zip` e o link do template no lugar do formulário de PDF e do botão "Enviar à banca"), `app/Models/TrabalhoAcademico.php` (`submissoesLatex(): HasMany`, `submissaoLatexVigente(): HasOne` = mais recente), `routes/web.php`
- Teste: `tests/Feature/SubmissaoLatexTest.php`

**Interfaces:**
- Consome: Tarefas 2, 3, 5 e 6 e `Programa::temTemplateLatex()`.
- Produz:
  - Tabela `trabalho_submissoes_latex`:
    - identificação: `trabalho_academico_id`, `banca_id` (nullable), `enviado_por_id`, `tipo_documento` (`qualificacao|dissertacao|tese`), `idioma` (`pt|en`);
    - arquivos: `zip_path`, `zip_hash_sha256`, `tex_principal`, `pdf_banca_path`, `pdf_conferencia_path`, `bbl_path`, `motor` (copiado do template);
    - processamento: `status` (`processando|ok|nao_ok|erro`), `elementos` json, `conformidade` json, `log_resumido` text, `avisos` json, `numeracao_linhas` bool;
    - referências: `refs_status` (`pendente|processando|concluida|erro`), `refs_total`, `refs_com_ano`, `refs_recentes_pct` decimal(5,2), `refs_atualizadas` bool, `refs_orientador`;
    - homologação: `homologacao_status` nullable (`aguardando|homologada|devolvida`), `homologacao_observacoes`, `homologada_por`, `homologada_em`, `enviada_banca_em`; timestamps.
  - `ProcessarSubmissaoLatexJob::__construct(TrabalhoSubmissaoLatex $submissao)`; `handle(ProjetoLatexZipService, ExtratorElementosLatexService, CompiladorLatex, ConformidadeCadastroService)`. Etapas:
    1. extrair o zip; o preferido é o `tex_principal` do template;
    2. extrair os elementos, com o mapa do template;
    3. detectar o idioma: `diss=en|tese=en` nas opções da classe, ou a última língua de `\usepackage[...]{babel}` igual a `english` → `en`;
    4. conferir com o cadastro;
    5. compilar a versão da banca com numeração. Se falhar, compilar sem numeração: se essa passar, `numeracao_linhas = false` e aviso "Numeração de linhas indisponível com esta classe"; se não, `nao_ok`;
    6. `status = ok` se compilou e `bloqueia()` é false; senão `nao_ok`;
    7. atualizar `trabalho.titulo`, `resumo` e `palavras_chave` com o extraído;
    8. se compilou, despachar `VerificarReferenciasSubmissaoJob` (Tarefa 8).

    Uma exceção inesperada leva a `status = erro`, com a mensagem no `log_resumido`. `failed()` faz o mesmo.
  - Rotas:
    - `meu-trabalho.submissao-latex.store` (POST: `tipo_documento`, `zip` até 100 MB; exige banca liberada, como no `store` atual);
    - `meu-trabalho.submissao-latex.show` (GET `{submissao}`; dono);
    - `meu-trabalho.submissao-latex.pdf` (GET `{submissao}/{versao}` com `versao` em `banca|conferencia`; dono, Secretaria do programa ou membro da banca);
    - `meu-trabalho.submissao-latex.submeter` (POST: só se `status === ok` e for a vigente → `homologacao_status = aguardando`).

- [ ] **Passo 1: testes que falham** (compilador falso registrado com `$this->app->instance(CompiladorLatex::class, ...)` devolvendo sucesso e um PDF fake; `Queue::fake()` só onde indicado):
  - `test_programa_com_template_mostra_envio_zip_e_esconde_pdf`;
  - `test_envio_zip_cria_submissao_e_despacha_job`;
  - `test_job_marca_ok_e_atualiza_trabalho` (zip do template PPGMSB com banca e orientação do cadastro batendo com o `.tex`);
  - `test_job_marca_nao_ok_sem_resumo`;
  - `test_job_marca_nao_ok_quando_nao_compila` (compilador falso com `sucesso` false e log);
  - `test_sem_numeracao_quando_lineno_falha_mas_documento_compila`;
  - `test_submeter_exige_ok`;
  - `test_reenvio_torna_nova_submissao_vigente_e_antiga_nao_submete` (o POST `submeter` da antiga → erro);
  - `test_discente_nao_ve_submissao_de_outro`;
  - `test_programa_sem_template_continua_no_pdf`.
- [ ] **Passo 2:** `php artisan test --filter=SubmissaoLatexTest` → FALHA.
- [ ] **Passo 3: implementar.** A tela da submissão mostra, sem recarregar a página inteira:
  - status, com polling via `fetch` a cada 5 s enquanto estiver `processando`;
  - checklist (✔ encontrado com o conteúdo, ✖ não encontrado, ⚠ divergente "no .tex" × "no sistema");
  - avisos e log resumido, num `<details>`;
  - downloads das versões;
  - botão "Submeter à homologação" quando `ok`.
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA.
- [ ] **Passo 5: commit** `feat: discente envia projeto LaTeX; compilação, extração e conformidade em fila`.

### Tarefa 8: Verificação das referências

**Arquivos:**
- Criar: migration `2026_10_26_120000_create_submissao_referencias_table.php`, `app/Models/SubmissaoReferencia.php`, `app/Services/Latex/LeitorBblService.php`, `app/Services/Latex/VerificacaoReferenciasService.php`, `app/Jobs/VerificarReferenciasSubmissaoJob.php`
- Fixtures: `tests/fixtures/latex/bbl/apacite.bbl`, `tests/fixtures/latex/bbl/biblatex.bbl`, `tests/fixtures/latex/bbl/plain.bbl` (3 a 5 entradas cada; ao menos uma com DOI e uma sem)
- Teste: `tests/Unit/LeitorBblServiceTest.php`, `tests/Feature/VerificacaoReferenciasTest.php`

**Interfaces:**
- Consome: `bbl_path` da submissão (gravado pela Tarefa 7 a partir de `ResultadoCompilacao::$bblPath`), `OrcidWork` (obras do orientador) e `config('ollama.*')`.
- No trabalho (D8): `TrabalhoAcademico::metricasReferencias(): ?array`, que devolve os `refs_*` da submissão mais recente com `refs_status = concluida`, para os relatórios. Teste `test_trabalho_expoe_metricas_da_ultima_submissao` em `VerificacaoReferenciasTest`.
- Produz:
  - Tabela `submissao_referencias`: `submissao_id` (FK, `indexName: 'subm_refs_submissao_fk'`), `chave`, `texto`, `doi_tex`, `ano_tex`, `titulo_tex`, `status` (`localizada|nao_localizada|nao_verificada`), `fonte` (`openalex|crossref`, nullable), `doi_encontrado`, `ano_encontrado`, `divergencia_doi` bool, `divergencia_ano` bool, `do_orientador` bool.
  - `LeitorBblService::ler(string $bblPath): array` → `[['chave','texto','doi','ano','titulo']]`, com:
    - entradas separadas por `\bibitem` (bibtex) ou `\entry{chave}` (biblatex);
    - DOI por `10\.\d{4,9}/[^\s},]+`;
    - ano = primeiro `(19|20)\d{2}`, ou o campo `year`/`date` no biblatex;
    - texto limpo de comandos LaTeX.
  - `VerificacaoReferenciasService::verificar(TrabalhoSubmissaoLatex $s): void`:
    1. com DOI, consulta o Crossref `works/{doi}`, e o OpenAlex se o Crossref falhar;
    2. sem DOI, busca no OpenAlex por título (`works?search=` com o título; aceita só se a similaridade normalizada do título for ≥ 0,9);
    3. sem título legível, pede ao Ollama `{titulo, ano}` e repete a busca. Se o Ollama falhar, a referência fica `nao_localizada`;
    4. erro de rede ou timeout → `nao_verificada`;
    5. `do_orientador` por DOI ∈ `OrcidWork` do orientador, ou por autor do OpenAlex com o ORCID do orientador ou nome equivalente (`NomePessoa::equivalentes`);
    6. no fim, grava `refs_*` na submissão conforme a D8.

    Usa `Http::timeout(15)->withUserAgent('Katu/1.0 (mailto:'.config('mail.from.address').')')` e cache de 30 dias por DOI e por título (`Cache::remember`).
  - `VerificarReferenciasSubmissaoJob::__construct(TrabalhoSubmissaoLatex $s)`. Ao terminar, monta o apêndice ("Relatório de verificação das referências": tabela chave/status/DOI/ano/observação, mais os totais de atualidade e de produção do orientador), compila a versão de conferência via `CompiladorLatex::compilar($projeto, $motor, true, $apendice)` e grava `pdf_conferencia_path`. Se o apêndice não compilar, a versão de conferência é a da banca e um aviso é registrado.

- [ ] **Passo 1: testes que falham:**
  - `LeitorBblServiceTest`: cada fixture devolve o número certo de entradas, com DOI e ano extraídos.
  - `VerificacaoReferenciasTest`:
    - `test_doi_localizado_no_crossref`;
    - `test_sem_doi_localizado_por_titulo_no_openalex`;
    - `test_nao_localizada_quando_nenhuma_base_encontra`;
    - `test_nao_verificada_quando_rede_falha` (`Http::fake` com `ConnectionException`);
    - `test_marca_producao_do_orientador_por_doi_em_orcid_works`;
    - `test_atualidade` (ano do envio 2026; refs com anos 2026, 2025, 2025, 2020 e uma sem ano → `refs_com_ano === 4`, `refs_recentes_pct === 75.00`, `refs_atualizadas === true`);
    - `test_atualidade_igual_a_70_nao_e_atualizada`;
    - `test_divergencia_de_ano_registrada`.
- [ ] **Passo 2:** `php artisan test --filter="LeitorBblServiceTest|VerificacaoReferenciasTest"` → FALHA.
- [ ] **Passo 3: implementar.**
- [ ] **Passo 4:** os testes PASSAM.
- [ ] **Passo 5: commit** `feat: verificação das referências citadas (Crossref/OpenAlex), produção do orientador e atualidade`.

### Tarefa 9: Homologação documental e envio à banca (D12)

**Arquivos:**
- Criar: `app/Http/Controllers/HomologacaoDocumentalController.php`, `resources/views/homologacao-documental/index.blade.php`, `resources/views/homologacao-documental/show.blade.php`, `app/Mail/SubmissaoDevolvidaMail.php` + view do e-mail, `resources/views/partials/relatorio-referencias.blade.php` (reaproveitado pelo discente, pela Secretaria e pela banca)
- Modificar:
  - `app/Mail/TrabalhoEnviadoBancaMail.php`: parâmetro opcional `?TrabalhoSubmissaoLatex $submissao = null`; com ele, o e-mail traz o resumo da verificação das referências (totais por status, atualidade e produção do orientador) e o aviso "Referências não localizadas devem ser verificadas".
  - `resources/views/avaliacoes/banca.blade.php`: inclui o partial do relatório quando o documento da banca vier de uma submissão LaTeX.
  - `app/Models/TrabalhoDocumento.php`: coluna nullable `submissao_latex_id` (migration nesta tarefa).
  - `routes/web.php` e `resources/views/layouts/app.blade.php`: item de menu "Homologação documental" para a Secretaria.
- Teste: `tests/Feature/HomologacaoDocumentalTest.php`

**Interfaces:**
- Consome: `TrabalhoSubmissaoLatex` (Tarefas 7 e 8).
- Produz rotas:
  - `homologacao-documental.index` (fila do programa da Secretaria: `homologacao_status = aguardando`);
  - `homologacao-documental.show` (`{submissao}`: checklist, divergências, relatório das referências, PDFs);
  - `homologacao-documental.homologar` (POST);
  - `homologacao-documental.devolver` (POST, `observacoes` obrigatório).
- Comportamento:
  - **Homologar** grava os dados da homologação e cria um `TrabalhoDocumento`. Esse documento recebe uma cópia do `pdf_banca_path` (`tipo_documento` da submissão, `status_analise = 'concluida'`, `submissao_latex_id` preenchido). Em seguida envia `TrabalhoEnviadoBancaMail` a cada avaliador (mesma seleção de `TrabalhoDocumentoController::enviarParaBanca`, link assinado de 7 dias para `meu-trabalho.download`) e grava `enviada_banca_em`.
  - **Devolver** grava `homologacao_status = devolvida` e as observações e envia `SubmissaoDevolvidaMail` ao discente.

- [ ] **Passo 1: testes que falham** (`Mail::fake()`):
  - `test_secretaria_ve_fila_so_do_seu_programa`;
  - `test_homologar_cria_documento_e_envia_aos_avaliadores_com_relatorio` (`Mail::assertSent(TrabalhoEnviadoBancaMail::class, fn ($m) => $m->submissao !== null)` para cada avaliador; `TrabalhoDocumento` com `submissao_latex_id`);
  - `test_devolver_exige_observacao_e_avisa_discente`;
  - `test_nao_homologa_submissao_que_nao_esta_aguardando`;
  - `test_docente_nao_acessa` (403);
  - `test_banca_ve_relatorio_de_referencias_na_tela_de_avaliacao`;
  - `test_fluxo_pdf_continua_com_botao_enviar_a_banca` (programa sem template → a tela mostra "Enviar à banca").
- [ ] **Passo 2:** `php artisan test --filter=HomologacaoDocumentalTest` → FALHA.
- [ ] **Passo 3: implementar.**
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA.
- [ ] **Passo 5: commit** `feat: homologação documental da submissão LaTeX e envio à banca com relatório de referências`.

### Tarefa 10: Documentação e verificação manual

**Arquivos:**
- Modificar: `CLAUDE.md` e `AGENTS.md`:
  - módulo "Submissão LaTeX";
  - e-mails novos na tabela (`SubmissaoDevolvidaMail`; `TrabalhoEnviadoBancaMail` agora disparado também pela homologação documental);
  - requisito de TeX Live/`latexmk` em produção;
  - flags de `config/ia.php`;
  - dívida técnica "RAG de conformidade desligado; análise de conteúdo a redesenhar".
- Modificar: `README.md` (instalação do TeX Live e `IA_*` no `.env`) e `docs/superpowers/operacao-fluxo-secretaria.md` (roteiro: cadastrar template, discente envia zip, homologação documental).

- [ ] **Passo 1:** atualizar os documentos.
- [ ] **Passo 2:** `php artisan test` → toda a suíte PASSA; `php artisan test --group=latex` → PASSA, ou registra o pacote MiKTeX que falta.
- [ ] **Passo 3: verificação manual** com MySQL, fila e servidor rodando:
  1. a `secretaria@ppgmsb.test` cadastra `docs/classppgmsb.zip` e confirma o mapa;
  2. o `discente.completo@ppgmsb.test`, com banca homologada, envia o zip ajustado (nomes do cadastro) → ok;
  3. confere o PDF numerado;
  4. submete à homologação;
  5. a Secretaria homologa;
  6. o e-mail dos avaliadores aparece em `storage/logs/laravel.log`.
- [ ] **Passo 4: commit** `docs: submissão LaTeX, requisitos de TeX Live e RAG como dívida técnica`.
