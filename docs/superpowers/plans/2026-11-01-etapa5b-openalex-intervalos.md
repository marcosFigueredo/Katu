# Etapa 5b — Conferência da produção no OpenAlex e intervalos de confiança — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Conferir no OpenAlex se cada publicação declarada pelo estudante é encontrada (informativo, sem efeito na nota) e mostrar à CPA o intervalo de confiança de 95% do índice de cada trabalho e do programa, avisando quando o intervalo cruza a faixa de outra classe.

**Architecture:** Colunas novas em `producao_publicacoes` e um `ProducaoOpenAlexService` (busca por DOI; sem DOI, por título e ano) chamado por um job na fila depois que o estudante adiciona a publicação, e por um comando para pendentes. Intervalos por bootstrap dentro do `IndiceQualidadeService`: no trabalho, reamostrando os avaliadores; no programa, reamostrando as defesas. Gerador pseudoaleatório com semente fixa para o resultado não mudar a cada recarga.

**Tech Stack:** Laravel 12, fila `database`, `Http` (testes com `Http::fake`), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` — item 5 das Etapas (decisões de 2026-11-01).

## Global Constraints

- OpenAlex **só confere**: "não encontrada" não invalida a produção, não muda pontuação nem índice, não bloqueia nada. A publicação pode não ter DOI.
- Consulta externa só ao `api.openalex.org`, com `User-Agent` `Katu/1.0 (mailto:<mail.from.address>)`, timeouts 5 s/15 s; falha de rede grava `falha` (tentável de novo), nunca erro para o usuário.
- Intervalos: **só para a CPA** (não aparecem para orientador nem estudante). Bootstrap com 500 reamostras, percentis 2,5 e 97,5, semente fixa por banca/período.
- Nomes em português; commits com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. DOI digitado com prefixo `https://doi.org/` ou maiúsculas → mesma busca.
2. Sem DOI e título muito diferente no OpenAlex → "não encontrada", não um falso "encontrada".
3. OpenAlex fora do ar → `falha`, a página do estudante continua abrindo.
4. Trabalho com um único avaliador ou programa com uma única defesa → intervalo degenerado (lo = hi), sem erro.
5. Recarregar a tela da CPA → mesmos intervalos.

---

### Task 1: Conferência da produção no OpenAlex

**Files:**
- Create: `database/migrations/2026_11_01_130000_add_openalex_conferencia_to_producao_publicacoes.php`
- Create: `app/Services/ProducaoOpenAlexService.php`, `app/Jobs/VerificarProducaoOpenAlexJob.php`, `app/Console/Commands/VerificarProducaoOpenAlex.php`
- Modify: `app/Models/ProducaoPublicacao.php` (fillable/casts, `OPENALEX_SITUACOES`), `app/Http/Controllers/ProducaoAssociadaController.php::store` (despacha o job após o commit, só para publicação)
- Modify: `resources/views/meu-trabalho/producao.blade.php`, `resources/views/bancas/_conferencia-estudante.blade.php` (selo por publicação), `resources/views/cpa/index.blade.php` (coluna "Publicações" — total e quantas encontradas)
- Test: `tests/Feature/ProducaoOpenAlexTest.php`

**Interfaces:**
- Colunas: `openalex_situacao` enum `encontrada|nao_encontrada|falha` null, `openalex_citacoes` unsigned int null, `openalex_verificada_em` timestamp null (`openalex_id` já existe).
- `ProducaoPublicacao::OPENALEX_SITUACOES = ['encontrada' => 'Encontrada no OpenAlex', 'nao_encontrada' => 'Não encontrada no OpenAlex', 'falha' => 'OpenAlex indisponível; nova tentativa depois']`.
- `ProducaoOpenAlexService::verificar(ProducaoPublicacao $p): void` — com DOI: `GET https://api.openalex.org/works/https://doi.org/{doi}` (404 → não encontrada); sem DOI: `GET https://api.openalex.org/works?search={titulo}&per-page=5` e aceita a obra com similaridade de título ≥ 0,9 (`similar_text` em minúsculas sem acento) e, se houver ano, |ano − publication_year| ≤ 1. Grava situação, `openalex_id`, `cited_by_count`, data. Exceção de rede/HTTP ≠ 404 → `falha`.
- Job `VerificarProducaoOpenAlexJob(int $publicacaoId)`, `tries = 1`.
- Comando `producao:verificar-openalex` — verifica as publicações com situação nula ou `falha`.
- Selo: "Encontrada no OpenAlex · N citações" (verde) / "Não encontrada no OpenAlex — não invalida a produção" (cinza) / "Conferência pendente".

- [ ] **Step 1: Failing tests**: `test_com_doi_encontrada_grava_id_e_citacoes`; `test_doi_com_prefixo_e_normalizado`; `test_doi_inexistente_fica_nao_encontrada`; `test_sem_doi_busca_por_titulo_e_ano`; `test_titulo_diferente_nao_casa`; `test_falha_de_rede_grava_falha`; `test_adicionar_publicacao_despacha_job` (`Queue::fake`); `test_selo_aparece_para_estudante_e_nao_muda_pontuacao` (pontuação igual antes e depois de "nao_encontrada"); `test_comando_verifica_pendentes`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=ProducaoOpenAlexTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: produção declarada conferida no OpenAlex (informativo, sem efeito na nota)`

---

### Task 2: Intervalos de confiança para a CPA

**Files:**
- Modify: `app/Services/IndiceQualidadeService.php`
- Modify: `resources/views/cpa/index.blade.php`
- Test: `tests/Feature/IntervaloConfiancaTest.php`

**Interfaces:**
- Refatorar `dimensoesDaBanca` em `notasPorAvaliador(Banca): array` (`[membro_id => [item_id => ['codigo','peso','nota']]]`) + `dimensoesDe(array $porAvaliador): array`; comportamento atual inalterado.
- `indiceTrabalho(Banca $defesa, bool $comIntervalo = false)` — com `true`, acrescenta `'intervalo' => [lo, hi]|null` e `'fronteira' => ?int` (classe vizinha que o intervalo alcança, pela faixa do período; null se o intervalo fica dentro da classe). Bootstrap: 500 reamostras dos avaliadores (com reposição), produção fixa, `mt_srand(crc32('trabalho-'.$banca->id))`.
- `indicePrograma` passa a montar os dados por defesa e acrescenta `'intervalo'` e `'fronteira'` do programa: 500 reamostras das defesas, `mt_srand(crc32('programa-'.$periodo->id))`; os trabalhos entram com `comIntervalo: true`.
- Tela da CPA: "IC 95%: a–b" sob o índice do programa e na lista; selo "fronteira com {classe}" quando houver.

- [ ] **Step 1: Failing tests**: `test_intervalo_do_trabalho_contem_o_indice` (3 avaliadores 6/8/10 → lo ≤ índice ≤ hi e lo < hi); `test_um_avaliador_intervalo_degenerado`; `test_intervalo_e_reprodutivel` (duas chamadas → iguais); `test_fronteira_quando_cruza_faixa` (notas 8 e 9 com cortes padrão → índice < 8,5 e fronteira 1); `test_intervalo_do_programa`; `test_orientador_nao_ve_intervalo` (banca finalizada não mostra "IC 95%"); `test_cpa_ve_intervalo`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=IntervaloConfiancaTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS; `--filter=IndiceQualidade` → PASS.
- [ ] **Step 5: Commit** — `feat: intervalo de confiança de 95% do índice do trabalho e do programa, informativo à CPA`

---

### Task 3: Documentação

**Files:** `CLAUDE.md` (OpenAlex na produção; intervalos), `docs/superpowers/operacao-fluxo-secretaria.md` (fila precisa estar rodando para a conferência; comando `producao:verificar-openalex`).
- [ ] **Step 1:** Docs. **Step 2:** `php artisan test --compact` → tudo passa. **Step 3: Commit** — `docs: etapa 5b — OpenAlex e intervalos`
