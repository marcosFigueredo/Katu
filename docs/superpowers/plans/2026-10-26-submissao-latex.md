# Submissão do trabalho em LaTeX — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Objetivo:** permitir que o discente de um programa com template LaTeX envie o projeto `.zip`. O sistema compila, extrai os elementos vitais, confere com o cadastro, verifica as referências, passa pela homologação única da Secretaria (com as perguntas sim/não da CPA) e envia aos avaliadores o PDF com linhas numeradas.

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
3. **Reenvio após "não ok" ou devolução**: a nova submissão substitui a anterior como a vigente, sem apagar o histórico, e só a vigente pode ir à homologação. O teste fica na Tarefa 9.
4. **OpenAlex fora do ar**: as referências ficam `nao_verificada` e a submissão continua utilizável. O teste fica na Tarefa 10.
5. **Template sem algum elemento no mapa**, por exemplo uma classe antiga sem comando para a banca: a extração usa as regras genéricas, e o que não for achado aparece como "não encontrado". Nada pode quebrar. O teste fica na Tarefa 3.
6. **Pergunta da CPA inativada depois de respondida**: o histórico da homologação mostra o texto como estava. O teste fica na Tarefa 11.

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
- Criar: `app/Services/Latex/ExtratorElementosLatexService.php`, `app/Services/Latex/ElementosVitais.php` (constantes dos códigos), `app/Services/Latex/MapaTemplateOllamaService.php`
- Modificar: `app/Http/Controllers/ProgramaTemplateLatexController.php` (gravar mapa e motor no upload)
- Fixtures: `tests/fixtures/latex/abntex2/main.tex` (abnTeX2 mínimo: `\titulo{}`, `\autor{}`, `\orientador{}`, `\coorientador{}`, `\begin{resumo}...\textbf{Palavras-chave}: a. b.\end{resumo}`, `\begin{resumo}[Abstract]...\textbf{Keywords}: x. y.\end{resumo}`) e `tests/fixtures/latex/article/main.tex` (`\title{}`, `\author{}`, `\begin{abstract}`, sem banca). O caso do PPGMSB usa `docs/classppgmsb.zip` (`classppgmsb/dissertacao.tex`), extraído pela Tarefa 2.
- Teste: `tests/Unit/ExtratorElementosLatexServiceTest.php`

**Interfaces:**
- Consome: `ProjetoLatex::fonteExpandido()`.
- Produz:
  - `ElementosVitais` com as constantes `TITULO_PT`, `TITULO_EN`, `RESUMO_PT`, `PALAVRAS_CHAVE_PT`, `ABSTRACT_EN`, `PALAVRAS_CHAVE_EN`, `AUTOR`, `ORIENTADOR`, `COORIENTADORES`, `MEMBROS_BANCA`, `DATA_DEFESA`, `LOCAL_DEFESA` (valores `'titulo_pt'`, …).
  - `ExtratorElementosLatexService::descobrirMapa(string $fonte): array`, no formato `[codigo => ['tipo' => 'comando'|'ambiente'|'ambiente_opcao'|'secao'|'padrao_comando', 'nome' => string, 'opcao'? => string]]`. Só regras, sem IA.
  - `ExtratorElementosLatexService::extrair(string $fonte, ?array $mapa = null): array`, no formato `[codigo => string|array<string>|null]`. `COORIENTADORES` e `MEMBROS_BANCA` são listas de nomes; ausente = `null`. Com `$mapa`, ele é usado primeiro e as regras genéricas servem de reserva para os códigos não resolvidos.
  - `ExtratorElementosLatexService::detectarMotor(string $fonte): string`: `% !TEX program = xelatex|lualatex|pdflatex` (comentário mágico, lido antes de remover comentários), ou `xelatex` se houver `\usepackage{fontspec}` ou `unicode-math`; senão `pdflatex`.
  - `MapaTemplateOllamaService::sugerir(string $fonte, array $codigosFaltantes): array` (arquivo `app/Services/Latex/MapaTemplateOllamaService.php`), no mesmo formato do mapa. Chamado só para os códigos que as regras não resolveram. Erro de rede ou JSON inválido devolve `[]`. Usa `config('ollama.url')`, `config('ollama.model')` e `config('ollama.timeout')`.
  - **Integração com o cadastro do template (Tarefa 4, já pronta):** em `ProgramaTemplateLatexController::store`, depois de validar o zip, calcular `mapa_elementos` (`descobrirMapa` + `sugerir` para os faltantes) e `motor` (`detectarMotor`) sobre o `fonteExpandido()` do template, e gravar no registro. Nada disso aparece na tela (decisão do usuário).

- [ ] **Passo 1: testes que falham:**
  - `test_extrai_ppgmsb`: título PT começa com "Este é um título"; título EN começa com "This is a dissertation"; `palavras_chave_pt === 'modelagem; simulação; otimização'`; abstract começa com "With the marvelous"; orientador `'Antonio Guimarães Rosa'`; `membros_banca` contém `'Profa. Helena Souza Lima'` e tem 5 itens; `data_defesa === '01/01/2023'`; `local_defesa === 'Alagoinhas/BA'`.
  - `test_extrai_abntex2`: resumo, abstract (de `resumo[Abstract]`), palavras-chave PT e keywords EN tirados de dentro dos ambientes; `orientador` e `coorientadores` preenchidos.
  - `test_extrai_article_sem_banca`: `membros_banca === null` e `orientador === null`, sem exceção.
  - `test_descobrir_mapa_ppgmsb` → `mapa['resumo_pt'] === ['tipo'=>'ambiente','nome'=>'resumo']`, `mapa['abstract_en']['nome'] === 'newAbstract'`, `mapa['membros_banca'] === ['tipo'=>'padrao_comando','nome'=>'Membro[A-Z]Nome']`.
  - `test_ignora_comentarios`: `% \TituloBR{falso}` antes do real não é extraído.
  - `test_mapa_com_codigo_inexistente_cai_nas_regras`.
  - `test_detectar_motor` (`% !TEX program = xelatex` → `xelatex`; `\usepackage{fontspec}` → `xelatex`; nada → `pdflatex`).
  - Em `tests/Feature/ProgramaTemplateLatexTest.php` (já existe, da Tarefa 4): `test_upload_grava_mapa_e_motor` (template PPGMSB → `mapa_elementos['resumo_pt']['nome'] === 'resumo'`, `motor === 'pdflatex'`) e `test_ollama_sugere_codigos_faltantes` (`Http::fake` devolvendo um mapa para `membros_banca` num zip com template `article` → o mapa gravado contém a sugestão; com o Ollama falhando, o upload continua funcionando).
- [ ] **Passo 2:** `php artisan test --filter="ExtratorElementosLatexServiceTest|ProgramaTemplateLatexTest"` → FALHA.
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

### Tarefa 4: Cadastro do template LaTeX do programa (tela da Secretaria)

> **Decisão do usuário (2026-10-26):** a Secretaria só cadastra o modelo oficial. A tela tem apenas essa ação. Não há mapa, confirmação nem escolha de motor; isso fica para a Tarefa 3. Esta tarefa é executada **antes** da Tarefa 3.

**Arquivos:**
- Criar: migration `database/migrations/2026_10_26_100000_create_programa_templates_latex_table.php`, `app/Models/ProgramaTemplateLatex.php`, `app/Http/Controllers/ProgramaTemplateLatexController.php`, `resources/views/programas/template-latex.blade.php`
- Modificar: `app/Models/Programa.php` (`templateLatex(): HasOne`, `temTemplateLatex(): bool`), `routes/web.php`, `resources/views/layouts/app.blade.php` (o item "Base de Conhecimento (IA)" passa a ser "Template LaTeX" e aponta para `programa-template.index`; nenhum link para a tela de Normas)
- Teste: `tests/Feature/ProgramaTemplateLatexTest.php`

**Interfaces:**
- Consome: `ProjetoLatexZipService::extrair(string $zipPath, string $destino, ?string $principalPreferido = null): ProjetoLatex`, que lança `ProjetoLatexInvalidoException`. **`extrair()` apaga todo o conteúdo de `$destino` quando falha**: passe sempre um diretório temporário novo e único (ex.: `storage_path('app/private/tmp/'.Str::uuid())`) e apague-o ao terminar, com sucesso ou não.
- Produz:
  - Tabela `programa_templates_latex`: `programa_id` (unique, FK), `arquivo_path`, `arquivo_nome_original`, `hash_sha256`, `tex_principal`, `classe_documento` (nullable), `motor` (string nullable; preenchido pela Tarefa 3), `mapa_elementos` (json nullable; preenchido pela Tarefa 3), `enviado_por` (FK users), `enviado_em`, timestamps.
  - `Programa::templateLatex()`; `Programa::temTemplateLatex(): bool`, verdadeiro quando o programa tem registro de template.
  - Rotas:
    - `programa-template.index` (GET);
    - `programa-template.store` (POST, campo `arquivo`, `.zip` até 50 MB). Valida extraindo para um diretório temporário com `ProjetoLatexZipService` (preferido: `dissertacao.tex`; se ambíguo, a mensagem da exceção vai para o usuário). Guarda o zip em `programas/{programa_id}/template-latex/template.zip` (disco `local`), substitui o anterior e grava `tex_principal` e `classe_documento`;
    - `programa-template.download` (GET; Secretaria do programa ou discente com vínculo ativo no programa).
  - Constante de tela: título "Template LaTeX do programa".

- [ ] **Passo 1: testes que falham:**
  - `test_secretaria_envia_template` (`Storage::fake('local')`, `UploadedFile` com o conteúdo de `docs/classppgmsb.zip` → registro com `tex_principal === 'dissertacao.tex'`, `classe_documento === 'classPPGMSB'`, arquivo existe no disco; a tela mostra o nome do arquivo e "dissertacao.tex");
  - `test_reenvio_substitui_template` (dois envios → um registro, `hash_sha256` atualizado);
  - `test_zip_invalido_mostra_erro` (zip sem `.tex` principal → erro de validação em `arquivo` com a mensagem da exceção; nenhum registro);
  - `test_arquivo_nao_zip_e_recusado` (PDF → erro de validação);
  - `test_tela_nao_mostra_normas` (`assertDontSee('Normas e Procedimentos')`);
  - `test_menu_da_secretaria_aponta_para_template` (GET dashboard → `assertSee(route('programa-template.index'))` e `assertDontSee('Base de Conhecimento')`);
  - `test_docente_nao_acessa` (403);
  - `test_secretaria_de_outro_programa_nao_ve_template` (isolamento: vê a tela do próprio programa, sem o template do outro, e não baixa o do outro);
  - `test_discente_do_programa_baixa_template` e `test_discente_de_outro_programa_nao_baixa` (403);
  - `test_diretorio_temporario_e_removido` (depois do envio, `storage_path('app/private/tmp')` não guarda resíduo do upload).
- [ ] **Passo 2:** `php artisan test --filter=ProgramaTemplateLatexTest` → FALHA.
- [ ] **Passo 3: implementar** migration, model, controller e view, no padrão visual das telas da Secretaria (Bootstrap; usar `resources/views/programas/documentos-referencia.blade.php` como referência de layout). Texto da tela: "Template LaTeX do programa". Explicação: "Envie o modelo oficial (.zip) do projeto LaTeX do programa. Com o template cadastrado, os discentes enviam o trabalho em LaTeX e o sistema compila, extrai os elementos vitais e confere com o cadastro. Sem template, o programa usa o envio de PDF, sem análise por IA." Mostra o template atual (arquivo, `.tex` principal, classe, enviado por/em), o botão de download e o formulário de envio ou substituição.
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA; `php artisan migrate` no MySQL local roda sem erro.
- [ ] **Passo 5: commit** `feat: Secretaria cadastra o template LaTeX oficial do programa`.

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

---

> **Replanejamento (2026-10-26, tarde).** As Tarefas 7 a 12 abaixo substituem as antigas 7 a 10, conforme a spec revista: regras 8, 9 e 11, "Critérios da CPA" e "Homologação única — decisões complementares". Resumo: existe **uma só homologação**, a da Secretaria, que vale para os dois fluxos (LaTeX e PDF). O discente propõe a banca e envia o trabalho num mesmo pedido. A Secretaria responde as perguntas sim/não da CPA e vê a composição; o botão Homologar só libera com 100% "sim" e composição válida. Ao homologar, os avaliadores e o orientador/coorientadores recebem e-mail. A homologação antiga de banca (antes do envio do trabalho) e as evidências da CPA (`banca_evidencias`) deixam de existir.
>
> **Máquina de estados de `bancas.homologacao_status` nas bancas propostas pelo discente:** `proposta` (o discente edita e envia o trabalho) → `aguardando` (na fila da Secretaria; o discente não edita) → `homologada` | `devolvida` (o discente edita e reenvia, voltando a `aguardando`). Banca cadastrada direto pela Secretaria continua nascendo `homologada`, como hoje (fora do escopo).
>
> **O documento do pedido:** `bancas.documento_homologacao_id` (FK nullable → `trabalho_documentos`) aponta o PDF que a Secretaria vê e que vai aos avaliadores. No fluxo PDF, é o documento enviado. No fluxo LaTeX, é um `TrabalhoDocumento` criado na hora do envio à homologação, com cópia do PDF numerado e `submissao_latex_id`.

### Tarefa 7: Critérios da CPA como perguntas sim/não (Secretaria)

**Arquivos:**
- Criar: migration `2026_10_26_130000_criterios_cpa_perguntas_sim_nao.php`
- Modificar: `app/Models/CriterioCpa.php`, `app/Http/Controllers/CriterioCpaController.php`, `resources/views/criterios-cpa/index.blade.php`, `resources/views/criterios-cpa/form.blade.php`, `app/Services/CriterioCpaService.php`, `app/Models/Banca.php` (remove `evidencias()`), `routes/web.php`
- Remover: `app/Models/BancaEvidencia.php` e a tabela `banca_evidencias` (na mesma migration)
- Teste: `tests/Feature/CriterioCpaPerguntasTest.php`. Apague os testes antigos que testam evidências; adapte os demais que quebrarem.

**Interfaces:**
- Produz:
  - `criterios_cpa` passa a ter: `programa_id`, `criado_por`, `pergunta` (text; renomeia `titulo`), `etapa` **nullable** (`qualificacao|defesa`; null = as duas), `nivel` nullable (`mestrado|doutorado`; null = os dois), `ordem`, `ativo`, `inativado_em` (datetime nullable), timestamps. Saem: `codigo`, `descricao`, `tipo_evidencia`, `obrigatorio`, `versao`. Derrube antes o índice único `criterios_cpa_programa_codigo_versao_unique` (o SQLite exige).
  - `CriterioCpa::inativar(): void` grava `ativo = false` e `inativado_em = now()`. Não há reativação nem exclusão: a pergunta nunca é apagada (`destroy` sai da rota).
  - `CriterioCpaService::perguntasPara(Banca $banca): Collection`: as ativas do programa do trabalho, com `etapa` null ou igual a `$banca->tipo`, e `nivel` null ou igual a `$banca->trabalhoAcademico->nivel`, ordenadas por `ordem` e `id`. `pendenciasObrigatorias` e `criteriosAtivosPara` saem.
  - Rotas: `criterios-cpa.index|create|store|edit|update` (resource sem `show`/`destroy`) e `criterios-cpa.inativar` (POST `{criterio}`). Só `sec`, isolado pelo programa da Secretaria (como hoje).
- Tela: a lista mostra Pergunta, Etapa ("Qualificação", "Defesa" ou "Qualificação e defesa"), Nível ("Mestrado", "Doutorado" ou "Mestrado e doutorado"), Criada em (d/m/Y), Inativada em (d/m/Y ou "—") e Status. O formulário tem só pergunta, etapa (três opções), nível (três opções) e ordem. A edição só altera perguntas ativas.

- [ ] **Passo 1: testes que falham:**
  - `test_secretaria_cadastra_pergunta_com_etapa_e_nivel`;
  - `test_inativar_grava_data_e_nao_apaga` (o registro continua, `ativo` false, `inativado_em` preenchido);
  - `test_perguntas_para_filtra_etapa_nivel_e_ativas` (qualificação + mestrado: entram as de etapa null/qualificacao e nível null/mestrado; ficam fora defesa, doutorado e inativas);
  - `test_secretaria_nao_ve_pergunta_de_outro_programa` (404);
  - `test_discente_nao_acessa` (403).
- [ ] **Passo 2:** `php artisan test --filter=CriterioCpaPerguntasTest` → FALHA.
- [ ] **Passo 3: implementar.** `HomologacaoBancaService::homologar` deixa de chamar `pendenciasObrigatorias` (a regra nova entra na Tarefa 11); `HomologacaoBancaController::index` deixa de carregar `evidencias.criterio`, e a view `bancas/homologacao.blade.php` deixa de mostrar evidências.
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA.
- [ ] **Passo 5: commit** `feat: critérios da CPA como perguntas sim/não com etapa, nível e data de inativação`.

### Tarefa 8: Pedido do discente — banca proposta + trabalho, enviado à homologação (fluxo PDF)

**Arquivos:**
- Criar: migration `2026_10_26_140000_add_documento_homologacao_to_bancas_table.php`, `app/Services/PedidoHomologacaoService.php`
- Modificar: `app/Http/Controllers/BancaController.php` (modo discente), `app/Http/Controllers/TrabalhoDocumentoController.php`, `resources/views/meu-trabalho/index.blade.php`, `routes/web.php`
- Teste: `tests/Feature/PedidoHomologacaoTest.php`

**Interfaces:**
- Produz:
  - `bancas.documento_homologacao_id` (FK nullable → `trabalho_documentos`, `nullOnDelete`, índice nomeado `bancas_doc_homologacao_fk`) e `bancas.homologacao_tentativa` (unsigned int, default 1).
  - `BancaController@store` e `@update` no modo discente gravam `homologacao_status = 'proposta'` (não mais `aguardando`) e `libera_envio_trabalho = false`. O discente edita a banca em `proposta` e `devolvida`; em `aguardando` e `homologada`, não (403).
  - `PedidoHomologacaoService::bancaDoPedido(TrabalhoAcademico $t): ?Banca`: a banca não cancelada mais recente com `homologacao_status` em `proposta|devolvida`.
  - `PedidoHomologacaoService::enviar(Banca $banca, TrabalhoDocumento $documento): void`: exige `homologacao_status` em `proposta|devolvida`, o ORCID do discente preenchido e a composição válida (`VerificacaoBancaService::exigir(..., 'homologacao')`); grava `documento_homologacao_id` e `homologacao_status = 'aguardando'`. Se vinha de `devolvida`, incrementa `homologacao_tentativa`. Usado também pela Tarefa 9.
  - `TrabalhoDocumentoController@store` (PDF): passa a exigir `bancaDoPedido` em vez de `libera_envio_trabalho`. Mensagem sem banca: "Proponha a banca antes de enviar o trabalho."
  - Rota `meu-trabalho.enviar-homologacao` (POST `{documento}`; dono) → `PedidoHomologacaoService::enviar`. **Remove** a rota `meu-trabalho.enviar-banca` e o método `enviarParaBanca`; o envio aos avaliadores passa a acontecer só na homologação (a Tarefa 11 reaproveita o corpo desse método).
  - `BancaNotificacaoService::enviarCadastro` deixa de ser chamado na proposta e na homologação (`HomologacaoBancaController::homologar` para de chamá-lo). A Tarefa 11 define os e-mails da homologação.
- Tela `meu-trabalho`: mostra a banca do pedido e o estado ("Proposta — envie o trabalho", "Aguardando homologação", "Devolvida — veja a não conformidade", "Homologada"). Com documento enviado e banca em `proposta|devolvida`, aparece "Enviar à homologação". Em `devolvida`, mostra `homologacao_observacoes`.

- [ ] **Passo 1: testes que falham:**
  - `test_discente_propoe_banca_que_fica_proposta_e_fora_da_fila` (não aparece em `bancas.homologacao`);
  - `test_discente_envia_pdf_com_banca_proposta`;
  - `test_envio_pdf_sem_banca_proposta_e_recusado`;
  - `test_enviar_homologacao_coloca_banca_aguardando_com_documento`;
  - `test_enviar_homologacao_exige_composicao_valida`;
  - `test_discente_nao_edita_banca_aguardando`;
  - `test_reenvio_apos_devolucao_incrementa_tentativa`;
  - `test_rota_enviar_banca_nao_existe_mais`.
- [ ] **Passo 2:** `php artisan test --filter=PedidoHomologacaoTest` → FALHA.
- [ ] **Passo 3: implementar.** Ajuste os testes existentes que dependiam de `aguardando` na proposta, de `enviar-banca` ou do `BancaCadastradaMail` na homologação, sem enfraquecer o que verificam.
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA.
- [ ] **Passo 5: commit** `feat: discente propõe a banca e envia o trabalho num pedido único à homologação`.

### Tarefa 9: Submissão LaTeX do discente e processamento

**Arquivos:**
- Criar: migration `2026_10_26_150000_create_trabalho_submissoes_latex_table.php` (inclui a coluna nullable `trabalho_documentos.submissao_latex_id`), `app/Models/TrabalhoSubmissaoLatex.php`, `app/Jobs/ProcessarSubmissaoLatexJob.php`, `app/Http/Controllers/SubmissaoLatexController.php`, `resources/views/meu-trabalho/submissao-latex.blade.php`
- Modificar: `resources/views/meu-trabalho/index.blade.php` (com `temTemplateLatex()`, mostra o envio de `.zip` e o link do template no lugar do formulário de PDF), `app/Models/TrabalhoAcademico.php` (`submissoesLatex(): HasMany`, `submissaoLatexVigente(): HasOne` = mais recente), `app/Models/TrabalhoDocumento.php`, `routes/web.php`
- Teste: `tests/Feature/SubmissaoLatexTest.php`

**Interfaces:**
- Consome: Tarefas 2, 3, 5, 6 e 8 (`PedidoHomologacaoService`), `Programa::temTemplateLatex()`.
- Produz:
  - Tabela `trabalho_submissoes_latex`:
    - identificação: `trabalho_academico_id`, `banca_id` (a banca do pedido), `enviado_por_id`, `tipo_documento` (`qualificacao|dissertacao|tese`), `idioma` (`pt|en`);
    - arquivos: `zip_path`, `zip_hash_sha256`, `tex_principal`, `pdf_banca_path`, `pdf_conferencia_path`, `bbl_path`, `motor`;
    - processamento: `status` (`processando|ok|nao_ok|erro`), `elementos` json, `conformidade` json, `log_resumido` text, `avisos` json, `numeracao_linhas` bool;
    - referências: `refs_status` (`pendente|processando|concluida|erro`), `refs_total`, `refs_com_ano`, `refs_recentes_pct` decimal(5,2), `refs_atualizadas` bool, `refs_orientador`; timestamps.
  - `ProcessarSubmissaoLatexJob::__construct(TrabalhoSubmissaoLatex $submissao)`; `handle(ProjetoLatexZipService, ExtratorElementosLatexService, CompiladorLatex, ConformidadeCadastroService)`. Etapas:
    1. extrair o zip; o preferido é o `tex_principal` do template;
    2. extrair os elementos, com o mapa do template;
    3. detectar o idioma: `diss=en|tese=en` nas opções da classe, ou a última língua de `\usepackage[...]{babel}` igual a `english` → `en`;
    4. conferir com o cadastro, comparando os membros com a **banca do pedido** (proposta);
    5. compilar a versão da banca com numeração. Se falhar, compilar sem numeração: se essa passar, `numeracao_linhas = false` e aviso "Numeração de linhas indisponível com esta classe"; se não, `nao_ok`;
    6. `status = ok` se compilou e `bloqueia()` é false; senão `nao_ok`;
    7. atualizar `trabalho.titulo`, `resumo` e `palavras_chave` com o extraído;
    8. se compilou, despachar `VerificarReferenciasSubmissaoJob` (Tarefa 10; até ela existir, proteja o despacho com `class_exists`).

    Uma exceção inesperada leva a `status = erro`, com a mensagem no `log_resumido`. `failed()` faz o mesmo. O diretório de saída da compilação (`ResultadoCompilacao::$diretorioSaida`) é limpo pelo job em sucesso **e** em falha, depois de copiar os PDFs e o `.bbl` para `storage/app/private/submissoes-latex/{id}/`.
  - Rotas:
    - `meu-trabalho.submissao-latex.store` (POST: `tipo_documento`, `zip` até 100 MB; exige `PedidoHomologacaoService::bancaDoPedido`);
    - `meu-trabalho.submissao-latex.show` (GET `{submissao}`; dono);
    - `meu-trabalho.submissao-latex.pdf` (GET `{submissao}/{versao}` com `versao` em `banca|conferencia`; `banca`: dono, Secretaria do programa ou membro da banca; `conferencia`: só o dono, porque traz o apêndice das referências);
    - `meu-trabalho.submissao-latex.enviar-homologacao` (POST `{submissao}`: só se `status === ok` e for a vigente; cria o `TrabalhoDocumento` com cópia do `pdf_banca_path`, `submissao_latex_id` e `status_analise = 'concluida'`, e chama `PedidoHomologacaoService::enviar`).

- [ ] **Passo 1: testes que falham** (compilador falso registrado com `$this->app->instance(CompiladorLatex::class, ...)` devolvendo sucesso e um PDF fake; `Queue::fake()` só onde indicado):
  - `test_programa_com_template_mostra_envio_zip_e_esconde_pdf`;
  - `test_envio_zip_cria_submissao_e_despacha_job`;
  - `test_envio_zip_sem_banca_proposta_e_recusado`;
  - `test_job_marca_ok_e_atualiza_trabalho` (zip do template PPGMSB com a banca proposta e a orientação batendo com o `.tex`);
  - `test_job_marca_nao_ok_sem_resumo`;
  - `test_job_marca_nao_ok_quando_nao_compila` (compilador falso com `sucesso` false e log);
  - `test_job_limpa_diretorio_de_saida_tambem_na_falha`;
  - `test_sem_numeracao_quando_lineno_falha_mas_documento_compila`;
  - `test_enviar_homologacao_exige_ok_e_cria_documento`;
  - `test_reenvio_torna_nova_submissao_vigente_e_antiga_nao_envia`;
  - `test_discente_nao_ve_submissao_de_outro`;
  - `test_conferencia_so_para_o_dono`;
  - `test_programa_sem_template_continua_no_pdf`.
- [ ] **Passo 2:** `php artisan test --filter=SubmissaoLatexTest` → FALHA.
- [ ] **Passo 3: implementar.** A tela da submissão mostra, sem recarregar a página inteira:
  - status, com polling via `fetch` a cada 5 s enquanto estiver `processando`;
  - checklist (✔ encontrado com o conteúdo, ✖ não encontrado, ⚠ divergente "no .tex" × "no sistema");
  - avisos e log resumido, num `<details>`;
  - downloads das versões;
  - botão "Enviar à homologação" quando `ok`.
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA.
- [ ] **Passo 5: commit** `feat: discente envia projeto LaTeX; compilação, extração e conformidade em fila`.

### Tarefa 10: Verificação das referências

O relatório **nunca** é mostrado à Secretaria (regra 8): vai só ao discente (apêndice da versão de conferência e tela da submissão) e aos avaliadores (Tarefa 11).

**Arquivos:**
- Criar: migration `2026_10_26_160000_create_submissao_referencias_table.php`, `app/Models/SubmissaoReferencia.php`, `app/Services/Latex/LeitorBblService.php`, `app/Services/Latex/VerificacaoReferenciasService.php`, `app/Jobs/VerificarReferenciasSubmissaoJob.php`, `resources/views/partials/relatorio-referencias.blade.php`
- Fixtures: `tests/fixtures/latex/bbl/apacite.bbl`, `tests/fixtures/latex/bbl/biblatex.bbl`, `tests/fixtures/latex/bbl/plain.bbl` (3 a 5 entradas cada; ao menos uma com DOI e uma sem)
- Teste: `tests/Unit/LeitorBblServiceTest.php`, `tests/Feature/VerificacaoReferenciasTest.php`

**Interfaces:**
- Consome: `bbl_path` da submissão (Tarefa 9), `OrcidWork` (obras do orientador), `NomePessoa::equivalentes`, `NomePessoa::limparLatex` e `config('ollama.*')`.
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
    5. `do_orientador` por DOI ∈ `OrcidWork` do orientador, ou por autor do OpenAlex com o ORCID do orientador ou nome equivalente;
    6. no fim, grava `refs_*` na submissão conforme a D8.

    Usa `Http::timeout(15)->withUserAgent('Katu/1.0 (mailto:'.config('mail.from.address').')')` e cache de 30 dias por DOI e por título (`Cache::remember`).
  - `VerificarReferenciasSubmissaoJob::__construct(TrabalhoSubmissaoLatex $s)`. Ao terminar, monta o apêndice ("Relatório de verificação das referências": tabela chave/status/DOI/ano/observação, mais os totais de atualidade e de produção do orientador), compila a versão de conferência via `CompiladorLatex` e grava `pdf_conferencia_path`. Se o apêndice não compilar, a versão de conferência é a da banca e um aviso é registrado. Limpa o diretório de saída em sucesso e em falha.
  - `TrabalhoAcademico::metricasReferencias(): ?array`: os `refs_*` da submissão mais recente com `refs_status = concluida`, para os relatórios (D8).
  - Partial `partials/relatorio-referencias` (`$submissao`): totais por status, atualidade, produção do orientador e a lista, com "não localizada — verificar". Usado na tela da submissão do discente e na tela dos avaliadores (Tarefa 11).

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
    - `test_divergencia_de_ano_registrada`;
    - `test_trabalho_expoe_metricas_da_ultima_submissao`.
- [ ] **Passo 2:** `php artisan test --filter="LeitorBblServiceTest|VerificacaoReferenciasTest"` → FALHA.
- [ ] **Passo 3: implementar.**
- [ ] **Passo 4:** os testes PASSAM.
- [ ] **Passo 5: commit** `feat: verificação das referências citadas (Crossref/OpenAlex), produção do orientador e atualidade`.

### Tarefa 11: Homologação única pela Secretaria e envios

**Arquivos:**
- Criar: migration `2026_10_26_170000_create_homologacao_respostas_cpa_table.php`, `app/Models/HomologacaoRespostaCpa.php`, `app/Mail/HomologacaoDevolvidaMail.php` + view, `app/Mail/BancaAgendadaOrientacaoMail.php` + view, `resources/views/bancas/homologacao-show.blade.php`
- Modificar: `app/Services/HomologacaoBancaService.php`, `app/Http/Controllers/HomologacaoBancaController.php`, `resources/views/bancas/homologacao.blade.php` (a fila), `app/Mail/TrabalhoEnviadoBancaMail.php`, `resources/views/avaliacoes/banca.blade.php`, `routes/web.php`
- Teste: `tests/Feature/HomologacaoUnicaTest.php`

**Interfaces:**
- Consome: `CriterioCpaService::perguntasPara` (Tarefa 7), `bancas.documento_homologacao_id` e `homologacao_tentativa` (Tarefa 8), `TrabalhoSubmissaoLatex` (Tarefa 9), o partial de referências (Tarefa 10) e `VerificacaoBancaService::verificarBanca`.
- Produz:
  - Tabela `homologacao_respostas_cpa`: `banca_id` (FK), `criterio_cpa_id` (FK), `tentativa` (unsigned int), `pergunta` (text, cópia do texto na hora da resposta), `resposta` (boolean nullable), `respondido_por` (FK users nullable), `respondido_em` (datetime nullable), timestamps. Único `(banca_id, criterio_cpa_id, tentativa)` com nome `homol_resp_cpa_unique`.
  - `HomologacaoBancaService::respostasAtuais(Banca $b): Collection`: para cada pergunta de `perguntasPara($b)`, a resposta da tentativa atual (cria a linha com `resposta = null` se não existir, copiando `pergunta`).
  - `HomologacaoBancaService::responder(Banca $b, array $respostas, User $u): void` (`[criterio_id => bool]`; salva parcial).
  - `HomologacaoBancaService::podeHomologar(Banca $b): array` → `['pode' => bool, 'cpa_nao' => list<string>, 'cpa_sem_resposta' => int, 'composicao' => array]`. Pode quando todas as respostas da tentativa atual são `true` (nenhuma pergunta = ok) **e** a composição é válida **e** `homologacao_status === 'aguardando'` **e** há `documento_homologacao_id`.
  - `HomologacaoBancaService::homologar(Banca $b, User $u): void`: exige `podeHomologar`; grava a homologação como hoje (`libera_envio_trabalho = true` continua, para as telas de avaliação); envia `TrabalhoEnviadoBancaMail` a cada avaliador ativo, com link assinado de 7 dias para `meu-trabalho.download` do `documento_homologacao_id` (corpo do antigo `enviarParaBanca`), e `BancaAgendadaOrientacaoMail` (data, horário e local) ao orientador e aos coorientadores ativos.
  - `HomologacaoBancaService::devolver(Banca $b, User $u, string $observacoes): void`: monta a não conformidade (perguntas com "não", regras de composição que falharam, observação), grava em `homologacao_observacoes` e envia `HomologacaoDevolvidaMail` ao discente com esse texto. A tentativa seguinte começa quando o discente reenvia (a Tarefa 8 incrementa `homologacao_tentativa`); as respostas antigas ficam.
  - `TrabalhoEnviadoBancaMail`: parâmetro opcional `?TrabalhoSubmissaoLatex $submissao = null`; com ele, o e-mail traz o resultado da verificação automática (checklist e divergências) e o resumo das referências (totais por status, atualidade e produção do orientador), com o aviso "Referências não localizadas devem ser verificadas".
  - Rotas: `bancas.homologacao` (fila: só `aguardando`, programa da Secretaria), `bancas.homologacao.show` (GET `{banca}`), `bancas.homologacao.responder` (POST `{banca}`), `bancas.homologar` e `bancas.devolver` (existentes).
  - Tela de detalhe da Secretaria: dados do discente e da banca, link do PDF, resultado da composição, perguntas da CPA com rádios Sim/Não e "Salvar respostas", e, no fluxo LaTeX, o checklist e as divergências da submissão. **Nada das referências.** O botão "Homologar" fica desabilitado enquanto `podeHomologar` for falso, com o motivo ao lado. "Devolver" pede a observação.
  - `avaliacoes/banca.blade.php`: quando o `documento_homologacao_id` vier de uma submissão LaTeX, mostra o checklist da verificação automática e inclui o partial das referências.

- [ ] **Passo 1: testes que falham** (`Mail::fake()`):
  - `test_fila_mostra_so_aguardando_do_programa`;
  - `test_secretaria_responde_parcialmente_e_salva`;
  - `test_homologar_bloqueado_com_um_nao`;
  - `test_homologar_bloqueado_com_pergunta_sem_resposta`;
  - `test_homologar_bloqueado_com_composicao_invalida`;
  - `test_sem_perguntas_ativas_homologa_so_com_composicao`;
  - `test_resposta_guarda_texto_da_pergunta_mesmo_apos_inativar`;
  - `test_homologar_envia_aos_avaliadores_e_a_orientacao` (`TrabalhoEnviadoBancaMail` para cada avaliador; `BancaAgendadaOrientacaoMail` para orientador e coorientador; nenhum `BancaCadastradaMail`);
  - `test_homologar_latex_envia_relatorio_aos_avaliadores` (`$m->submissao !== null`);
  - `test_tela_da_secretaria_nao_mostra_referencias` (não contém "Relatório de verificação das referências" nem "atualidade");
  - `test_devolver_lista_nao_conformidade_e_avisa_discente`;
  - `test_nova_tentativa_comeca_sem_respostas_e_mantem_historico`;
  - `test_docente_nao_acessa` (403);
  - `test_banca_ve_relatorio_de_referencias_na_tela_de_avaliacao`.
- [ ] **Passo 2:** `php artisan test --filter=HomologacaoUnicaTest` → FALHA.
- [ ] **Passo 3: implementar.**
- [ ] **Passo 4:** os testes PASSAM; a suíte inteira PASSA.
- [ ] **Passo 5: commit** `feat: homologação única pela Secretaria com CPA sim/não e envio aos avaliadores e à orientação`.

### Tarefa 12: Documentação e verificação manual

**Arquivos:**
- Modificar: `CLAUDE.md` e `AGENTS.md`:
  - módulos "Submissão LaTeX" e "Homologação única";
  - critérios da CPA como perguntas sim/não (etapa, nível, inativação);
  - tabela de e-mails: `BancaCadastradaMail` não sai mais na proposta nem na homologação; `TrabalhoEnviadoBancaMail` e `BancaAgendadaOrientacaoMail` saem na homologação; `HomologacaoDevolvidaMail` na devolução;
  - requisito de TeX Live/`latexmk` em produção e a sandbox da compilação como pendência de infraestrutura;
  - flags de `config/ia.php`;
  - dívidas técnicas: RAG de conformidade desligado (análise de conteúdo a redesenhar) e carta institucional aos avaliadores pendente.
- Modificar: `README.md` (instalação do TeX Live e `IA_*` no `.env`) e `docs/superpowers/operacao-fluxo-secretaria.md` (roteiro: cadastrar template e perguntas da CPA; discente propõe a banca e envia; Secretaria homologa).

- [ ] **Passo 1:** atualizar os documentos.
- [ ] **Passo 2:** `php artisan test` → toda a suíte PASSA; `php artisan test --group=latex` → PASSA, ou registra o pacote MiKTeX que falta.
- [ ] **Passo 3:** `php artisan migrate` no MySQL local.
- [ ] **Passo 4: verificação manual** com MySQL, fila e servidor rodando:
  1. a `secretaria@ppgmsb.test` cadastra `docs/classppgmsb.zip` e duas perguntas da CPA (uma só para qualificação);
  2. o `discente.completo@ppgmsb.test` propõe a banca e envia o zip ajustado (nomes do cadastro) → ok → envia à homologação;
  3. a Secretaria responde "não" a uma pergunta → o botão fica bloqueado → devolve;
  4. o discente reenvia; a Secretaria responde tudo "sim" e homologa;
  5. os e-mails dos avaliadores e da orientação aparecem em `storage/logs/laravel.log`.
- [ ] **Passo 5: commit** `docs: submissão LaTeX, homologação única, CPA sim/não e requisitos de TeX Live`.
