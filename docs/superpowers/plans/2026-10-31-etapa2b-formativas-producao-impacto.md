# Etapa 2b — Atividades formativas, produção associada e tipo de impacto — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar a submissão enriquecida da defesa: o estudante informa o tipo de impacto, as 4 atividades formativas (conferidas pela Secretaria), a produção associada (publicações, extensão, popularização — com mínimo de publicações por curso) e as Informações adicionais; a banca vê essas informações e os dados da pesquisa ao avaliar.

**Architecture:** Quatro blocos do estudante em Meu trabalho, cada um com tabela própria ligada ao `trabalho_academico_id`, exigidos em `PedidoHomologacaoService::enviar` só no pedido de **defesa**. Enquanto o pedido de defesa está `aguardando` ou `homologada`, os quatro ficam só leitura (`PedidoHomologacaoService::defesaEnviada`). A Secretaria confere as atividades cumpridas na tela de homologação; conferência pendente é pendência de homologação. A banca vê um painel "Informações do estudante" nos formulários de avaliação. Nada de pontuação aqui — o cálculo das notas das dimensões 4 e 7 é a Etapa 5.

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Bootstrap 5, PHPUnit (SQLite em memória), MySQL 8 local.

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` — seções "Dimensão 3 › Tipo de impacto declarado", "Dimensão 4 — Produção associada", "Dimensão 7 › Componentes", "Declarações do estudante › B, C, D", "Etapas de implementação › 2".

## Global Constraints

- Nomes em português; rotas `recurso.acao`; todas as rotas em `['auth', 'verified']`; autorização por `users.categoria` no controller (discente → 403 para outros perfis).
- Trabalho do discente resolvido como em `TrabalhoDadosPesquisaController::trabalho()` (vínculos → primeiro trabalho; 404 se não houver).
- Views de componente: usar `@use('App\Models\...')`, nunca `use` dentro de `@php`.
- Confirmações com modal Bootstrap, nunca `confirm()`.
- Dev DB descartável: sem backfill nem dados legados.
- Commits em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; só adicionar ao stage os arquivos da tarefa.
- Suíte: `php artisan test --compact` (sem `--parallel`).
- As 4 atividades formativas, exatamente: Proficiência em Língua Estrangeira · Publicação de Produção Científica · Atividade Extensionista · Popularização das Ciências.
- Os 7 tipos de impacto existentes (científico, metodológico, tecnológico, social, ambiental, formativo, institucional) não mudam.

## Decisões deste plano

Do usuário (2026-10-31):
- **Sem defesa sem publicação:** a Secretaria define, por curso, o mínimo de publicações para enviar a defesa (ex.: 1 no mestrado, 4 no doutorado); contam todos os tipos (artigo, livro, capítulo, anais).
- **Atividades formativas são definidas pela Secretaria** (valem para qualquer curso): tipo, quantidade exigida e detalhes. O estudante lança as realizadas e as inclui no pedido de qualificação ou de defesa; o que a Secretaria já sabe ela mesma registra. **Faltando atividade, o estudante não consegue enviar à homologação.**
- **Publicações:** o estudante indica; a Secretaria marca, para análise futura: saiu da dissertação · situação (publicada, aceita, submetida) · estudante primeiro autor · orientador coautor · coautoria internacional · conta para o mínimo do curso.
- **Informações adicionais** na submissão da defesa: 7 perguntas, algumas com desdobramento quando a resposta é "Sim"; na pergunta 5 (pessoas ou animais) o estudante anexa o PDF da certidão do comitê de ética. Não há texto oficial da IEEE.
- **A banca vê** os dados da pesquisa, o impacto, a produção, as atividades formativas e as Informações adicionais (com os PDFs de ética). **O espelho continua privado à CPA** (nem banca, nem orientador, nem Secretaria).
- **Certificado de participação na banca:** dívida para depois — gerado automaticamente após o encerramento, enviado por e-mail, assinado pelo coordenador do curso, com destaque para a portaria de nomeação no Diário Oficial. Fora deste plano; registrado na spec pela Task 7.

Do planejador (para revisão):
1. **Trava:** depois do envio da defesa à homologação (banca de defesa não cancelada `aguardando` ou `homologada`), impacto, produção e Informações adicionais ficam só leitura; voltam a ser editáveis se o pedido for devolvido. Lançamentos de atividade travam enquanto houver qualquer pedido `aguardando`, e lançamento confirmado não pode ser excluído.
2. **Evidência das atividades:** descrição + link opcional; sem upload.
3. **Atividades na homologação:** a Secretaria confere cada lançamento e registra as que ela informa; homologação bloqueada até todas as atividades exigidas da etapa estarem confirmadas na quantidade exigida. A defesa exige também as atividades da qualificação.
4. **Produção:** o estudante confirma "a lista está completa"; qualquer alteração depois desfaz a confirmação. Publicações do OpenAlex aparecem como sugestões; canal de artigo não é perguntado (fator de impacto na Etapa 5). O envio exige o mínimo pela lista do estudante; a homologação exige o mínimo pelas publicações que a Secretaria marcou como "conta para o mínimo".
5. **Informações adicionais em português**, traduzidas fielmente; "Academia.edu Journals" vira "O PPGMSB". Respostas que coincidem com os Dados da pesquisa vêm pré-marcadas.

## Review Focus

1. Estudante abre/edita impacto, produção ou Informações adicionais com pedido de defesa `aguardando` (ou lança atividade com qualquer pedido `aguardando`) → só leitura e POST/PUT/DELETE recusados sem alterar nada.
2. Pedido devolvido → blocos voltam a editar; lançamento confirmado continua sem poder ser excluído.
3. Qualificação não exige impacto, produção, Informações adicionais nem o mínimo de publicações; exige só as atividades de `etapa = qualificacao`.
4. Excluir publicação/ação de outro trabalho, ou baixar o documento de aprovação ética de outro trabalho, pela URL → 404.
5. Avaliador vê o painel; dados de pesquisa do painel são os da cópia da banca (`dados_pesquisa_enviados`), não os atuais; atividades aparecem (com a conferência); o espelho nunca aparece.

---

### Task 1: Tipo de impacto na defesa + trava do pedido de defesa

**Files:**
- Modify: `app/Services/PedidoHomologacaoService.php`
- Modify: `app/Http/Controllers/TrabalhoImpactoController.php`
- Modify: `resources/views/trabalho-impacto/edit.blade.php`, `resources/views/meu-trabalho/index.blade.php`
- Create: `tests/Concerns/CriaItensDaDefesa.php`, `tests/Feature/TrabalhoImpactoTest.php`
- Modify: `tests/Feature/PedidoHomologacaoTest.php`, `tests/Feature/EspelhoDiscenteTest.php` (fixtures)

**Interfaces:**
- Produces: `PedidoHomologacaoService::defesaEnviada(TrabalhoAcademico $trabalho): bool` — existe banca `tipo = defesa`, `status != cancelada`, `homologacao_status IN (aguardando, homologada)`.
- Produces: trait `Tests\Concerns\CriaItensDaDefesa::criarItensDaDefesa(TrabalhoAcademico $t): void` — cria tudo que o pedido de defesa exige além do espelho (Task 1: impacto; Tasks 2, 4 e 5 acrescentam as atividades padrão com lançamentos, produção confirmada com 1 publicação e Informações adicionais).
- Produces: mensagem de trava `'O pedido de defesa já foi enviado à homologação; estes dados não podem mais ser alterados.'` (chave de erro `travado`).

- [ ] **Step 1: Testes (falham)** em `TrabalhoImpactoTest`:
  - `test_tipo_aplicavel_exige_descricao` — PUT com `impacto_social_aplicavel=1` e descrição vazia → erro `impacto_social_descricao`; nada gravado.
  - `test_salva_e_limpa_descricao_de_tipo_nao_aplicavel` — PUT com `impacto_ambiental_aplicavel` ausente e descrição "x" → grava `impacto_ambiental_descricao = null`.
  - `test_pedido_de_defesa_aguardando_trava_edicao` — banca de defesa `aguardando`: GET mostra a mensagem de trava e nenhum `<form` com `trabalho-impacto.update`; PUT → `assertSessionHasErrors('travado')`, linha inalterada.
  - `test_pedido_devolvido_libera_edicao` — mesma banca com `devolvida` → PUT sem erros.
  - Em `PedidoHomologacaoTest`: `test_pedido_de_defesa_exige_tipo_de_impacto` — defesa com espelho e sem `TrabalhoImpacto` → `'Preencha o impacto da dissertação em Meu trabalho antes de enviar a defesa à homologação.'`; com impacto → `aguardando`. `test_pedido_de_qualificacao_nao_exige_itens_da_defesa` — qualificação sem impacto/atividades/produção → `aguardando`.
- [ ] **Step 2:** `php artisan test --compact --filter='TrabalhoImpactoTest|exige_tipo_de_impacto|nao_exige_itens_da_defesa'` → FAIL.
- [ ] **Step 3: Implementar.** `defesaEnviada`; no controller, validação `exclude_unless:impacto_{x}_aplicavel,1|required|string|max:2000` para cada descrição (mensagem `'Descreva o impacto marcado.'`), descrição gravada `null` quando não aplicável, trava no `update` (ValidationException `travado`) e flag `$travado` na view (alerta + campos `disabled`, sem botão salvar); trocar `resolverTrabalho()` pelo padrão de `TrabalhoDadosPesquisaController::trabalho()`. Em `enviar`, **depois** do gate do espelho e só para defesa: sem `TrabalhoImpacto` → erro `homologacao` acima. No card do índice: selo Pendente/Preenchido e o texto "Exigido para enviar a defesa à homologação." Criar o trait com o impacto (científico aplicável + descrição) e usá-lo nos testes de defesa existentes que esperam `aguardando` (`PedidoHomologacaoTest` defesa, `EspelhoDiscenteTest::test_pedido_de_defesa_exige_espelho`).
- [ ] **Step 4:** mesmo filtro → PASS; depois `php artisan test --compact` → verde.
- [ ] **Step 5: Commit** `feat: tipo de impacto exigido no pedido de defesa; dados da defesa travam após o envio`.

### Task 2: Atividades formativas — lista da Secretaria e lançamentos do estudante

**Files:**
- Create: `database/migrations/2026_10_31_110000_create_atividades_formativas_tables.php`, `app/Models/AtividadeFormativa.php`, `app/Models/AtividadeRealizada.php`, `app/Http/Controllers/AtividadeFormativaController.php` (Secretaria), `app/Http/Controllers/AtividadesRealizadasController.php` (estudante), `app/Services/AtividadesFormativasService.php`, `resources/views/atividades-formativas/index.blade.php`, `resources/views/meu-trabalho/atividades-formativas.blade.php`, `tests/Feature/AtividadesFormativasTest.php`
- Modify: `app/Models/TrabalhoAcademico.php`, `app/Models/Programa.php`, `routes/web.php`, `app/Services/PedidoHomologacaoService.php`, `resources/views/meu-trabalho/index.blade.php`, menu de Configurações da Secretaria, `tests/Concerns/CriaItensDaDefesa.php`

**Interfaces:**
- Consumes: mensagem `travado` (Task 1).
- Produces: tabela `atividades_formativas` (lista do programa; vale para todos os cursos): `programa_id` FK cascade, `nome` string 150, `detalhes` text null (o que conta), `quantidade_exigida` tinyint unsigned (≥ 1), `etapa` enum `qualificacao|defesa` (pedido em que passa a ser exigida; a defesa exige também as da qualificação), `informada_por` enum `estudante|secretaria`, `ativo` bool default true, `ordem` smallint, timestamps.
- Produces: tabela `atividades_realizadas`: `trabalho_academico_id` FK cascade, `atividade_formativa_id` FK restrict, `descricao` string 1000, `link` string 500 null, `conferencia` enum `confirmada|nao_confirmada` null, `conferida_por` FK users null, `conferida_em` datetime null, timestamps.
- Produces: `AtividadeFormativa::PADROES` — as 4 iniciais, `quantidade_exigida = 1`, `etapa = defesa`: Proficiência em Língua Estrangeira (`informada_por = secretaria`), Publicação de Produção Científica, Atividade Extensionista, Popularização das Ciências (`estudante`); `AtividadeFormativa::criarPadroes(Programa $p): void` (idempotente), chamado quando o programa não tem nenhuma atividade ao abrir a tela da Secretaria.
- Produces: `AtividadesFormativasService::exigidas(TrabalhoAcademico $t, string $tipoBanca): Collection` (atividades ativas do programa para a etapa: qualificação → `etapa = qualificacao`; defesa → todas) e `AtividadesFormativasService::faltantes(TrabalhoAcademico $t, string $tipoBanca, bool $soConfirmadas = false): array<string>` — uma frase por atividade com menos lançamentos que o exigido, no formato `'Atividade Extensionista (1 de 2)'`; conta os lançamentos que não estão `nao_confirmada` (ou só os `confirmada` quando `$soConfirmadas`). Na chamada do estudante, só atividades `informada_por = estudante`.
- Produces: rotas da Secretaria `atividades-formativas.index|store|update` e `atividades-formativas.inativar` (POST); rotas do estudante `meu-trabalho.atividades.index` (GET `/meu-trabalho/atividades-formativas`), `meu-trabalho.atividades.store` (POST), `meu-trabalho.atividades.destroy` (DELETE `/meu-trabalho/atividades-formativas/{atividadeRealizada}`).

- [ ] **Step 1: Testes (falham)** em `AtividadesFormativasTest`:
  - `test_secretaria_ve_as_quatro_atividades_iniciais_e_cadastra_outra` — primeira visita cria as 4 de `PADROES`; POST cria "Estágio docência" (quantidade 2, etapa defesa, informada por estudante); docente → 403; atividade de outro programa no `update` → 404.
  - `test_secretaria_altera_quantidade_e_inativa` — `quantidade_exigida = 0` → erro; inativada some da tela do estudante e deixa de ser exigida.
  - `test_estudante_ve_so_as_que_ele_informa_e_lanca_realizacao` — tela mostra "Atividade Extensionista" com "0 de 1" e não oferece lançamento para a Proficiência (mostra "informada pela Secretaria"); POST `atividade_formativa_id`, `descricao`, `link` → linha criada; link inválido → erro; atividade `informada_por = secretaria` ou de outro programa → erro de validação.
  - `test_estudante_exclui_lancamento_nao_confirmado` — DELETE apaga; lançamento `confirmada` → erro, não apaga; lançamento de outro trabalho → 404.
  - `test_trava_com_pedido_aguardando` — qualquer banca do trabalho `aguardando` → POST e DELETE recusados (`travado`).
  - Em `PedidoHomologacaoTest`: `test_pedido_exige_atividades_formativas_da_etapa` — defesa com "Atividade Extensionista" exigindo 1 e nenhum lançamento → erro `homologacao` `'Faltam atividades formativas para enviar à homologação: Atividade Extensionista (0 de 1).'`; com o lançamento → passa. Qualificação: só as de `etapa = qualificacao` são exigidas (lista padrão é toda de defesa → qualificação passa).
- [ ] **Step 2:** `php artisan test --compact --filter='AtividadesFormativasTest|exige_atividades_formativas_da_etapa'` → FAIL.
- [ ] **Step 3: Implementar** migration, models (casts, rótulos `ETAPAS`, `INFORMADA_POR`, `CONFERENCIA`), service, controllers e views (tela da Secretaria em Configurações com tabela + modal de inclusão/edição; tela do estudante com uma linha por atividade exigível: nome, detalhes, "X de N", lançamentos com selo da conferência e botão excluir, formulário de lançamento), gate em `enviar` para qualificação e defesa (depois dos dados da pesquisa, antes da verificação da composição); card no índice; o trait cria as 4 atividades padrão e um lançamento para cada uma informada pelo estudante. A trava aqui é "há banca do trabalho `aguardando`" (não só defesa), porque as atividades entram nos dois pedidos.
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: Secretaria define as atividades formativas; estudante lança as realizadas e sem elas não envia à homologação`.

### Task 3: Conferência das atividades e das publicações pela Secretaria

**Files:**
- Modify: `app/Http/Controllers/HomologacaoBancaController.php`, `app/Services/HomologacaoBancaService.php`, `resources/views/bancas/homologacao-show.blade.php`, `routes/web.php`
- Create: migration `2026_10_31_125000_add_conferencia_to_producao_publicacoes.php`
- Test: `tests/Feature/ConferenciaSecretariaTest.php`

> Executar **depois da Task 4** (precisa de `producao_publicacoes`); a numeração segue a ordem de leitura.

**Interfaces:**
- Consumes: `AtividadeRealizada`, `AtividadesFormativasService` (Task 2); `ProducaoPublicacao`, `PedidoHomologacaoService::minimoPublicacoes` (Task 4).
- Produces: colunas em `producao_publicacoes` (marcações da Secretaria, para análise futura): `derivada_dissertacao` bool null, `situacao` enum `publicada|aceita|submetida` null, `discente_primeiro_autor` bool null, `coautoria_orientador` bool null, `coautoria_internacional` bool null, `conta_minimo` bool null, `conferida_por` FK users null, `conferida_em` datetime null.
- Produces: rotas `bancas.homologacao.atividades` (POST `/bancas/{banca}/homologacao/atividades`) → `conferirAtividades`, e `bancas.homologacao.publicacoes` (POST `/bancas/{banca}/homologacao/publicacoes`) → `conferirPublicacoes`; `podeHomologar` passa a devolver `atividades_ok` e `publicacoes_ok`.

- [ ] **Step 1: Testes (falham)**:
  - `test_secretaria_confere_lancamentos_e_registra_as_que_ela_informa` — homologação mostra cada atividade exigida com "X de N" e os lançamentos; POST `conferencias[{id}]=confirmada` grava `conferencia`, `conferida_por`, `conferida_em`; POST `registrar[{atividade_formativa_id}]=1` para a Proficiência cria um lançamento `descricao = 'Registrada pela Secretaria'` já `confirmada`.
  - `test_atividades_pendentes_bloqueiam_homologacao` — pendências `'Há 1 lançamento(s) de atividade formativa sem conferência.'` e, depois de marcar `nao_confirmada` ou sem registrar a da Secretaria, `'Atividades formativas incompletas: Proficiência em Língua Estrangeira (0 de 1).'` (via `faltantes(..., soConfirmadas: true)`, todas as atividades); `pode` false até tudo confirmado.
  - `test_secretaria_marca_publicacoes_na_defesa` — POST `publicacoes[{id}][situacao]=publicada`, os 5 booleanos → gravados com `conferida_por/em`; `situacao` fora da lista → erro.
  - `test_publicacoes_pendentes_ou_abaixo_do_minimo_bloqueiam_homologacao_da_defesa` — pendência `'Há 1 publicação(ões) sem conferência da Secretaria.'`; conferida com `conta_minimo = false` e mínimo 1 → `'Publicações que contam para o mínimo do curso: 0 de 1.'`.
  - `test_qualificacao_mostra_atividades_da_etapa_e_nao_publicacoes`.
  - `test_so_secretaria_do_programa_confere` — docente → 403; secretaria de outro programa → 404; ids de outro trabalho ignorados.
  - `test_estudante_nao_exclui_publicacao_conferida` — publicação com `conferida_em` → DELETE do estudante dá erro e não apaga.
- [ ] **Step 2:** `php artisan test --compact --filter=ConferenciaSecretariaTest` → FAIL.
- [ ] **Step 3: Implementar** migration, os dois cards da homologação ("Atividades formativas" nas duas etapas; "Publicações" só na defesa, uma linha por publicação com a situação e as 5 marcações), as pendências e o `pode` em `podeHomologar`. Alterar uma publicação conferida não é possível pelo estudante (a Task 4 só permite incluir e excluir; excluir publicação conferida → erro).
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: Secretaria confere atividades e marca as publicações na homologação`.

### Task 4: Produção associada (estudante)

**Files:**
- Create: migration `2026_10_31_120000_create_producao_associada_tables.php`; models `ProducaoPublicacao`, `ProducaoExtensao`, `ProducaoPopularizacao`; `app/Http/Controllers/ProducaoAssociadaController.php`; `resources/views/meu-trabalho/producao.blade.php`; `tests/Feature/ProducaoAssociadaTest.php`
- Modify: `app/Models/TrabalhoAcademico.php`, `routes/web.php`, `app/Services/PedidoHomologacaoService.php`, `resources/views/meu-trabalho/index.blade.php`, `tests/Concerns/CriaItensDaDefesa.php`

**Interfaces:**
- Consumes: `defesaEnviada`, mensagem `travado` (Task 1); `TrabalhoAcademico::indicador()` (`dados_json` com `id, doi, titulo, ano, tipo, host_venue`).
- Produces (tabelas, todas com `trabalho_academico_id` FK cascade e timestamps):
  - `producao_publicacoes`: `tipo` (`artigo|livro|capitulo|anais`), `canal` string 30 null, `titulo` string 500, `ano` smallint, `veiculo` string 300 (revista, editora ou evento), `doi` string 255 null, `issn` string 20 null, `openalex_id` string 100 null.
  - `producao_extensoes`: `tipo` (`curso|oficina|evento|prestacao_servico|projeto`), `titulo`, `publico` int unsigned, `parceiro` string 300, `carga_horaria` int unsigned, `inicio` date, `fim` date null, `registro_proex` string 60 null, `produto_entregue` string 500 null, `link` string 500 null.
  - `producao_popularizacoes`: `tipo` (`palestra|feira|imprensa|video|podcast|rede_social|material_didatico`), `titulo`, `veiculo` string 300, `alcance` int unsigned, `data` date, `link` string 500 null.
  - `trabalhos_academicos.producao_confirmada_em` datetime null.
  - `programa_cursos.min_publicacoes_defesa` unsigned tinyint, default 0; `ProgramaCurso::PADROES` ganha `min_publicacoes_defesa` (mestrado 1, doutorado 4), usado por `CursoProgramaService::criar` e quando o vínculo não tem curso.
- Produces: `PedidoHomologacaoService::minimoPublicacoes(TrabalhoAcademico $t): int` — do curso do vínculo do discente; sem curso, `PADROES[nivel]`.
- Produces: consts de rótulos em cada model; `ProducaoPublicacao::CANAIS = ['livro' => [...], 'capitulo' => [...], 'anais' => [...]]` com as chaves `universitaria_regional|comercial_nacional|internacional` (livro, capítulo) e `local_regional|nacional|internacional` (anais); artigo sem canal. Relações `publicacoes()`, `extensoes()`, `popularizacoes()` em `TrabalhoAcademico`.
- Produces: rotas `meu-trabalho.producao.index` (GET `/meu-trabalho/producao`), `meu-trabalho.producao.store` (POST `/meu-trabalho/producao/{tipo}`, `tipo ∈ publicacao|extensao|popularizacao`), `meu-trabalho.producao.destroy` (DELETE `/meu-trabalho/producao/{tipo}/{id}`), `meu-trabalho.producao.confirmar` (POST `/meu-trabalho/producao/confirmar`).

- [ ] **Step 1: Testes (falham)** em `ProducaoAssociadaTest`:
  - `test_adiciona_publicacao_extensao_e_popularizacao` — um POST válido de cada → uma linha em cada tabela.
  - `test_canal_conforme_o_tipo` — livro sem canal → erro `canal`; artigo com canal → gravado `canal = null`; anais com `canal=internacional` ok, com `comercial_nacional` → erro.
  - `test_sugestoes_do_openalex` — `TrabalhoIndicador` com `dados_json` de 1 obra → GET mostra o título e o botão "Adicionar"; obra já adicionada (mesmo `openalex_id` ou DOI) não aparece como sugestão.
  - `test_excluir_item_de_outro_trabalho_e_404` — sem apagar.
  - `test_confirmar_lista_e_alteracao_desfaz_confirmacao` — POST confirmar com lista vazia → `producao_confirmada_em` preenchido; adicionar ou excluir item → volta a null.
  - `test_trava_com_pedido_de_defesa_aguardando` — POST, DELETE e confirmar recusados (`travado`), nada muda; e `test_outro_perfil_nao_acessa` (403).
  - Em `PedidoHomologacaoTest`: `test_pedido_de_defesa_exige_producao_confirmada` → `'Confirme a produção associada em Meu trabalho antes de enviar a defesa à homologação.'`; `test_pedido_de_defesa_exige_minimo_de_publicacoes_do_curso` — curso mestrado com `min_publicacoes_defesa = 1`, produção confirmada sem publicação → `'Para enviar a defesa, o seu curso exige pelo menos 1 publicação na produção associada; você informou 0.'`; com 1 publicação → `aguardando`. Curso com mínimo 0 passa sem publicação.
  - `ProgramaCursoTest` (existente ou novo `tests/Feature/MinimoPublicacoesCursoTest.php`): `test_secretaria_define_minimo_de_publicacoes_do_curso` — PUT do curso com `min_publicacoes_defesa = 4` grava; curso novo de doutorado nasce com 4 e de mestrado com 1; valor fora de 0..20 → erro.
  - Na tela de produção, o estudante vê `'Seu curso exige pelo menos 1 publicação para a defesa.'` e quantas já informou.
- [ ] **Step 2:** `php artisan test --compact --filter='ProducaoAssociadaTest|exige_producao_confirmada|exige_minimo_de_publicacoes|MinimoPublicacoesCurso|define_minimo_de_publicacoes'` → FAIL.
- [ ] **Step 3: Implementar** migration e models; controller com validação por tipo (datas `fim >= inicio`, anos 1950..ano atual+1, `doi` sem prefixo `https://doi.org/`), um `match ($tipo)` escolhendo model e regras, 404 para `tipo` desconhecido ou item fora do trabalho; view em 3 seções (lista + formulário em modal por seção), sugestões do OpenAlex preenchendo o formulário de publicação, aviso do mínimo do curso e bloco "Confirmo que esta lista está completa"; campo "Publicações mínimas para a defesa" na edição do curso (`ProgramaCursoController@update`, `programas/cursos.blade.php`); gates em `enviar` depois das atividades (primeiro confirmação, depois mínimo); card no índice; trait confirma a produção e cria 1 publicação (artigo).
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: estudante informa a produção associada; curso define o mínimo de publicações para a defesa`.

### Task 5: Informações adicionais (submissão da defesa)

**Files:**
- Create: migration `2026_10_31_130000_create_trabalho_informacoes_adicionais_table.php`, `app/Models/TrabalhoInformacoesAdicionais.php`, `app/Http/Controllers/InformacoesAdicionaisController.php`, `resources/views/meu-trabalho/informacoes-adicionais.blade.php`, `tests/Feature/InformacoesAdicionaisTest.php`
- Modify: `app/Models/TrabalhoAcademico.php`, `routes/web.php`, `app/Services/PedidoHomologacaoService.php`, `resources/views/meu-trabalho/index.blade.php`, `tests/Concerns/CriaItensDaDefesa.php`

**Interfaces:**
- Consumes: `defesaEnviada`, `travado` (Task 1); `TrabalhoDadosPesquisa` (pré-marcação).
- Produces: tabela `trabalho_informacoes_adicionais` (`trabalho_academico_id` unique FK cascade; `conflito_interesses` bool, `conflito_descricao` text null; `disponibilidade_dados` enum `nenhum_dado_novo|no_trabalho|sob_solicitacao|repositorio_publico`, `dados_repositorio` string 500 null; `amostras_fisicas` bool, `amostras_declaracao` text null; `aprovacao_etica` bool, `aprovacao_etica_path` string null, `aprovacao_etica_nome` string null; `participantes` bool, `certidao_etica_path` string null, `certidao_etica_nome` string null; `uso_ia` bool, `ia_ferramentas` string 500 null, `ia_etapas` text null; `originalidade_confirmada` bool; `preenchido_em` datetime; timestamps). `TrabalhoAcademico::informacoesAdicionais(): HasOne`.
- Produces: rotas `meu-trabalho.informacoes.edit` (GET) / `.update` (PUT) em `/meu-trabalho/informacoes-adicionais`; `meu-trabalho.informacoes.etica` (GET `/meu-trabalho/informacoes-adicionais/aprovacao-etica`) — download do PDF pelo estudante; Task 6 reaproveita o mesmo arquivo para banca e Secretaria.
- Produces: `TrabalhoInformacoesAdicionais::DISPONIBILIDADE` com os rótulos abaixo.

Texto das perguntas (exato; `*` = obrigatória; desdobramento só quando a resposta indicada):
1. **Conflito de interesses \*** — "Você tem algum potencial conflito de interesses a declarar? Isso inclui relações financeiras, pessoais ou profissionais que possam influenciar a forma como a pesquisa é apresentada ou interpretada." Sim/Não. **Sim →** "Descreva o conflito de interesses." (texto, obrigatório).
2. **Disponibilidade de dados \*** — "Você tem dados suplementares que sustentam os resultados para compartilhar?" Marca uma: `nenhum_dado_novo` "Nenhum dado novo foi gerado ou analisado neste estudo" · `no_trabalho` "Os dados disponíveis estão incluídos no trabalho ou em seus materiais suplementares" · `sob_solicitacao` "Os conjuntos de dados originais que sustentam os resultados estão disponíveis e serão compartilhados pelo autor mediante solicitação razoável" · `repositorio_publico` "Os conjuntos de dados originais estão publicamente disponíveis no seguinte repositório". **Repositório →** link (URL, obrigatório).
3. **Disponibilidade de amostras \*** — "O estudo envolveu o uso de amostras físicas?" Sim/Não. **Sim →** "Informe se as amostras estão disponíveis para outros pesquisadores e como podem ser acessadas. Se não puderem ser compartilhadas, explique brevemente as restrições." (texto, obrigatório).
4. **Declaração de ética do PPGMSB \*** — "O estudo exige aprovação ética?" Sim/Não. **Sim →** "Envie o documento original de aprovação." (PDF até 10 MB em `storage/app/private/informacoes-adicionais/`; obrigatório se ainda não houver arquivo; reenvio substitui o anterior).
5. **Consentimento informado \*** — "O estudo envolveu participantes humanos ou animais?" Sim/Não. **Sim →** "Envie a certidão do Comitê de Ética em Pesquisa (pessoas) ou da Comissão de Ética no Uso de Animais (animais)." (PDF até 10 MB, mesmas regras do documento da pergunta 4).
6. **Uso de IA na preparação do trabalho \*** — introdução: "O PPGMSB segue a posição do COPE sobre o uso de Inteligência Artificial (IA) e de tecnologias assistidas por IA na preparação de trabalhos. Os autores devem declarar qualquer uso de IA ou de tecnologias assistidas por IA na preparação do trabalho." Aviso: "Importante: ferramentas de IA não podem ser creditadas como autoras. A responsabilidade pela exatidão, originalidade e integridade do trabalho é inteiramente dos autores. Recomenda-se guardar registros com data (versões de rascunho, prompts, comentários de coautores, protocolos de pesquisa), pois o programa pode solicitar documentação de autenticidade." Pergunta: "Você usou alguma ferramenta de IA generativa?" Sim/Não. **Sim →** "Nome e versão da ferramenta (ex.: ChatGPT-4, Claude, Grammarly, DeepL Translator v3)" (texto curto, obrigatório) e "Em que etapas ou seções do trabalho foram usadas" (texto, obrigatório).
7. **Declaração de originalidade \*** — caixa obrigatória "Confirmo que este trabalho é original, inédito e não está em avaliação em outro lugar."

- [ ] **Step 1: Testes (falham)** em `InformacoesAdicionaisTest`:
  - `test_salva_tudo_nao_com_originalidade` — todas "Não", `disponibilidade_dados=nenhum_dado_novo`, originalidade marcada → linha com `preenchido_em`; desdobramentos null.
  - `test_desdobramentos_obrigatorios_quando_sim` — Sim em 1, 3, 4 (sem arquivo), 5 (sem caixa), 6 (sem campos) e `repositorio_publico` sem link → erros `conflito_descricao`, `amostras_declaracao`, `aprovacao_etica_arquivo`, `certidao_etica_arquivo`, `ia_ferramentas`, `ia_etapas`, `dados_repositorio`.
  - `test_originalidade_obrigatoria` — sem a caixa → erro `originalidade_confirmada`.
  - `test_campos_sem_efeito_sao_ignorados_e_limpos` — trocar Sim→Não com texto antigo no formulário salva sem erro e grava null (mesma técnica `exclude_unless` dos dados da pesquisa); trocar ética ou participantes para Não apaga o arquivo do disco (`Storage::fake('local')`).
  - `test_envio_e_download_dos_pdfs_de_etica` — os dois PDFs ficam em `informacoes-adicionais/`; GET `meu-trabalho.informacoes.etica` com `?documento=aprovacao|certidao` baixa cada um; arquivo não PDF → erro.
  - `test_pre_marca_a_partir_dos_dados_da_pesquisa` — sem informações adicionais e com dados da pesquisa `seres_humanos=true`, `dados_disponibilidade=publico`, `dados_link=…` → a tela vem com "Sim" em 4 e 5, `repositorio_publico` e o link preenchidos.
  - `test_trava_com_pedido_de_defesa_aguardando` e `test_outro_perfil_nao_acessa` (403).
  - Em `PedidoHomologacaoTest`: `test_pedido_de_defesa_exige_informacoes_adicionais` → `'Responda as Informações adicionais em Meu trabalho antes de enviar a defesa à homologação.'`.
- [ ] **Step 2:** `php artisan test --compact --filter='InformacoesAdicionaisTest|exige_informacoes_adicionais'` → FAIL.
- [ ] **Step 3: Implementar** migration, model, controller (validação com `exclude_unless`; arquivo `aprovacao_etica_arquivo` `file|mimes:pdf|max:10240`, `required` só quando ética = Sim e não há arquivo gravado; `certidao_etica_arquivo` igual, com participantes = Sim), view com as 7 perguntas na ordem e com o texto acima (desdobramentos aparecem com Alpine ao marcar Sim), pré-marcação (`dados_disponibilidade`: `nao_se_aplica→nenhum_dado_novo`, `no_trabalho→no_trabalho`, `sob_solicitacao→sob_solicitacao`, `publico→repositorio_publico` com o link; `seres_humanos||animais` → Sim em 4 e 5), gate em `enviar` depois da produção, card no índice; trait cria a linha com tudo Não.
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: Informações adicionais na submissão da defesa (conflito de interesses, dados, amostras, ética, consentimento, IA, originalidade)`.

### Task 6: Banca vê as informações do estudante

**Files:**
- Create: `resources/views/avaliacoes/partials/informacoes-estudante.blade.php`, `tests/Feature/InformacoesEstudanteBancaTest.php`
- Modify: `app/Http/Controllers/AvaliacaoController.php` (`form` e a tela da banca), `app/Http/Controllers/InformacoesAdicionaisController.php` (download para banca e Secretaria), `routes/web.php`, `resources/views/avaliacoes/form.blade.php`, `resources/views/avaliacoes/banca.blade.php`, `resources/views/bancas/homologacao-show.blade.php`, `resources/views/meu-trabalho/dados-pesquisa.blade.php`

**Interfaces:**
- Consumes: `bancas.dados_pesquisa_enviados`, `TrabalhoImpacto`, relações de produção (Task 4), `TrabalhoInformacoesAdicionais` (Task 5), rótulos de `TrabalhoDadosPesquisa`.
- Produces: rota `bancas.aprovacao-etica` (GET `/bancas/{banca}/aprovacao-etica?documento=aprovacao|certidao`) — PDFs de ética do trabalho da banca para membros ativos, orientador vigente e Secretaria do programa; demais → 403.

- [ ] **Step 1: Testes (falham)**:
  - `test_avaliador_ve_dados_da_pesquisa_da_copia_da_banca` — banca homologada com `dados_pesquisa_enviados` (`seres_humanos=true`, `caae='12345678.9.0000.0057'`); dados atuais com outro CAAE → form do avaliador mostra o CAAE da cópia e não o atual.
  - `test_avaliador_da_defesa_ve_impacto_producao_e_informacoes_adicionais` — mostra a descrição do impacto social, o título da publicação, a resposta de uso de IA, os links dos PDFs de ética e a descrição de um lançamento de atividade formativa; não mostra "Espelho".
  - `test_qualificacao_mostra_dados_da_pesquisa_e_atividades` — sem blocos de impacto, produção e informações adicionais.
  - `test_sem_copia_mostra_aviso` — banca sem `dados_pesquisa_enviados` → "Dados da pesquisa não enviados com este pedido."
  - `test_documento_de_etica_so_para_banca_orientador_e_secretaria` — membro, orientador e Secretaria → 200; outro docente e o discente por esta rota → 403; secretaria de outro programa → 404.
  - `test_tela_de_dados_da_pesquisa_diz_que_a_banca_ve_a_copia` — GET mostra "é essa cópia que a banca vê".
- [ ] **Step 2:** `php artisan test --compact --filter='InformacoesEstudanteBancaTest|banca_ve_a_copia'` → FAIL.
- [ ] **Step 3: Implementar** o partial (card recolhível "Informações do estudante": dados da pesquisa da cópia; na defesa, tipos de impacto aplicáveis com descrição, as três listas de produção e as atividades formativas com "X de N" e a conferência, e as respostas das Informações adicionais com links para os PDFs de ética), incluído no formulário de avaliação, na tela da banca e na homologação da defesa; parágrafo de abertura da tela de dados da pesquisa: "…cada pedido guarda uma cópia do que estava preenchido no envio, e é essa cópia que a banca vê."
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: banca vê dados da pesquisa, impacto, produção e informações adicionais do estudante`.

### Task 7: Cenário de teste e documentação

**Files:**
- Modify: `database/seeders/CenarioTesteSeeder.php`, `tests/Feature/CenarioTesteSeederTest.php`, `CLAUDE.md`, `docs/superpowers/operacao-fluxo-secretaria.md`, `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` (fechar a pergunta da IEEE — "não há texto oficial; substituída pelas Informações adicionais"; registrar o mínimo de publicações por curso, as decisões 1–5 do planejador e, na Etapa 3, o **certificado de participação na banca**: gerado automaticamente após o encerramento, enviado por e-mail, assinado pelo coordenador do curso, com destaque para a portaria de nomeação no Diário Oficial)

- [ ] **Step 1: Teste (falha)** `test_trabalho_da_juliana_tem_itens_da_defesa` — impacto (científico e social aplicáveis), as 4 atividades padrão do programa com lançamentos da estudante para publicação e extensão (sem conferência), 1 publicação (artigo, DOI `10.1016/j.annepidem.2025.06.012`), 1 extensão, 1 popularização, `producao_confirmada_em` preenchido e Informações adicionais com uso de IA = Sim (ferramenta "Claude", etapas "revisão de texto").
- [ ] **Step 2:** `php artisan test --compact --filter=test_trabalho_da_juliana_tem_itens_da_defesa` → FAIL.
- [ ] **Step 3:** seeder (idempotente, `updateOrCreate`); docs: bullet "Submissão enriquecida" e pontos de atenção do `CLAUDE.md`, roteiro e linha da Juliana no doc de operação.
- [ ] **Step 4:** filtro → PASS; suíte → verde; `php artisan migrate --force` e `php artisan db:seed --class=CenarioTesteSeeder` no MySQL sem erro.
- [ ] **Step 5: Commit** `docs: atividades formativas, produção associada e impacto na defesa`.
