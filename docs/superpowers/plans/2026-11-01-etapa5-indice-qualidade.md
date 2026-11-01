# Etapa 5 (versão de teste) — Índice de qualidade e CPA mínima — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Calcular o índice de qualidade de cada dissertação/tese (banca + produção associada) e do programa no período (trabalhos + autoavaliação do orientador + avaliação do orientando + espelho), classificar em 5 níveis, deixar a CPA definir os pesos pelo SWARA e ver os resultados, e mostrar ao orientador o perfil e o nível do trabalho do orientando.

**Architecture:** Uma CPA mínima (`membros_cpa`, designada pela Secretaria) com área própria. Períodos de avaliação (`periodos_qualidade`, com as faixas das classes) e pesos (`pesos_qualidade`, gerados pelo `SwaraService::calcular` já existente). Um `IndiceQualidadeService` calcula na hora (sem gravar) a partir das notas que já guardam a dimensão congelada.

**Tech Stack:** Laravel 12, Blade + Bootstrap, PHPUnit (SQLite em memória).

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` — seção "Índice de qualidade — dois níveis" e "O que o app entrega a cada um".

## Global Constraints

- A CPA **não avalia**: define pesos e vê resultados. **Não é o `admin`.** Membro da CPA = usuário docente designado pela Secretaria do programa, com mandato vigente (`inicio_em <= hoje` e `fim_em` nulo ou `>= hoje`).
- Índice do trabalho: só **defesas finalizadas**; fontes = notas da banca (formulários padrão e confidencial, avaliações `enviada` de avaliadores ativos) + produção associada. Autoavaliação, avaliação do orientando e espelho **não entram** no índice do trabalho.
- Índice do programa: trabalhos do período + autoavaliação do orientador + avaliação do orientando + espelho.
- 5 classes: `1 => 'Alta qualidade'`, `2 => 'Boa qualidade'`, `3 => 'Suficiente'`, `4 => 'Pouca qualidade'`, `5 => 'Baixa qualidade'`. Faixas padrão: ≥ 8,5 · ≥ 7,0 · ≥ 5,5 · ≥ 4,0 · abaixo.
- Regras de corte do trabalho (nessa ordem): `reprovada_por_plagio` → 5; `resultado = reprovado` → no mínimo 4; alguma dimensão < 4 → no mínimo 3; classe 1 exige todas as dimensões ≥ 7 (senão 2).
- Sem pesos definidos no período → pesos iguais.
- Privacidade mantida: Secretaria não vê resultados; estudante continua vendo só nota e resultado; orientador vê perfil e nível só do próprio orientando (orientador vigente, tipo orientador).
- Nomes em português; rotas `recurso.acao` em `['auth','verified']`; commits em português com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Defesa sem nenhuma nota em alguma dimensão: a dimensão fica de fora e os pesos são renormalizados — o índice não pode virar 0 nem NaN.
2. Nota 0 em uma dimensão: a média geométrica não pode quebrar (log de 0); piso de 0,5.
3. Membro da CPA com mandato encerrado ou de outro programa → 403 na área da CPA.
4. Coorientador, avaliador ou outro docente abrindo a banca → não vê o quadro do nível.
5. SWARA com s negativo ou não numérico → validação, não pesos negativos.

---

### Task 1: CPA mínima (designação e acesso)

**Files:**
- Create: `database/migrations/2026_11_01_120000_create_membros_cpa_table.php`, `app/Models/MembroCpa.php`
- Create: `app/Http/Controllers/MembroCpaController.php` (Secretaria), `resources/views/cpa/membros.blade.php`
- Modify: `app/Models/User.php` (`programaCpa(): ?Programa` — programa em que o usuário tem mandato vigente)
- Modify: `resources/views/layouts/app.blade.php` (Secretaria → Configurações → "Membros da CPA"; docente com mandato → item "CPA" que leva a `cpa.index`)
- Modify: `routes/web.php`
- Test: `tests/Feature/MembroCpaTest.php`

**Interfaces:**
- `membros_cpa`: `programa_id` fk, `user_id` fk, `inicio_em` date, `fim_em` date null, `numero_ato` string 150, `registrado_por` fk users null, timestamps.
- `MembroCpa::scopeVigentes($q)`.
- Rotas Secretaria: `membros-cpa.index` (GET `/membros-cpa`), `membros-cpa.store` (POST), `membros-cpa.encerrar` (POST `/membros-cpa/{membroCpa}/encerrar` → `fim_em = hoje`). Só docentes do programa da Secretaria; membro de outro programa → 403.
- `User::programaCpa()` usado pelas Tasks 2 e 4 para autorizar (`abort_unless($programa, 403)`).

- [ ] **Step 1: Failing tests**: `test_secretaria_designa_docente_com_mandato_e_ato`; `test_encerrar_mandato`; `test_so_docente_do_programa_pode_ser_membro`; `test_secretaria_de_outro_programa_nao_encerra` (403); `test_programa_cpa_so_com_mandato_vigente` (futuro, encerrado, vigente); `test_menu_cpa_aparece_so_para_membro_vigente`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=MembroCpaTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: CPA mínima — Secretaria designa membros com mandato e número do ato`

---

### Task 2: Períodos de avaliação e pesos pelo SWARA (área da CPA)

**Files:**
- Create: `database/migrations/2026_11_01_121000_create_periodos_e_pesos_qualidade.php`, `app/Models/PeriodoQualidade.php`, `app/Models/PesoQualidade.php`
- Create: `app/Http/Controllers/CpaPeriodoController.php`, `resources/views/cpa/periodos.blade.php`, `resources/views/cpa/pesos.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CpaPeriodoPesosTest.php`

**Interfaces:**
- `periodos_qualidade`: `programa_id`, `nome` string 100, `inicio_em` date, `fim_em` date, `corte_alta` decimal(4,2) default 8.5, `corte_boa` 7.0, `corte_suficiente` 5.5, `corte_pouca` 4.0, `criado_por`, timestamps.
- `pesos_qualidade`: `periodo_qualidade_id` fk cascade, `grupo` enum `dimensao|fonte`, `chave` string 40 (código da dimensão ou da fonte), `ordem` tinyint, `s_j` decimal(6,4), `peso` decimal(8,6), `definido_por` fk users null, timestamps; unique (`periodo_qualidade_id`,`grupo`,`chave`).
- `PeriodoQualidade::FONTES = ['trabalhos' => 'Avaliação dos trabalhos (banca e produção)', 'autoavaliacao_orientador' => 'Autoavaliação do orientador', 'avaliacao_orientando' => 'Avaliação do orientando', 'espelho' => 'Espelho do estudante']`.
- `PeriodoQualidade::pesos(string $grupo): array` — `[chave => peso]`; vazio → pesos iguais sobre as chaves do grupo (8 dimensões ou 4 fontes).
- `PeriodoQualidade::doPrograma(Programa $p, \DateTimeInterface $data): ?self` — período que contém a data.
- `PeriodoQualidade::classe(float $indice): int` — 1..5 pelas faixas.
- Rotas CPA: `cpa.periodos.index`/`store`/`update` e `cpa.pesos.edit` (GET `/cpa/periodos/{periodo}/pesos/{grupo}`), `cpa.pesos.update` (PUT). Formulário de pesos: a ordem (1..n, sem repetir) e `s_j` (≥ 0, ≤ 1) de cada item; calcula com `SwaraService::calcular` (ids = chaves) e grava `peso = w_j`. Mostra os pesos resultantes em %.
- Validação de período: `fim_em` ≥ `inicio_em`; cortes decrescentes (alta > boa > suficiente > pouca, entre 0 e 10).

- [ ] **Step 1: Failing tests**: `test_membro_cria_periodo_com_faixas_padrao`; `test_faixas_fora_de_ordem_sao_recusadas`; `test_swara_das_dimensoes_grava_pesos_que_somam_1` (ordem rigor…integridade, s 0,10 em todos → peso de rigor 0,1914… e soma 1 ± 0,0001); `test_ordem_repetida_ou_s_negativo_e_recusado`; `test_sem_swara_pesos_iguais` (8 × 0,125; 4 × 0,25); `test_classe_pelas_faixas` (8,5→1; 8,49→2; 7→2; 5,5→3; 4→4; 3,99→5); `test_nao_membro_e_membro_encerrado_recebem_403`; `test_membro_nao_mexe_em_periodo_de_outro_programa`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=CpaPeriodoPesosTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: CPA define períodos de avaliação, faixas das classes e pesos pelo SWARA`

---

### Task 3: Índice do trabalho

**Files:**
- Create: `app/Services/IndiceQualidadeService.php`
- Test: `tests/Feature/IndiceQualidadeTrabalhoTest.php`

**Interfaces:**
- `IndiceQualidadeService::CLASSES` (Global Constraints).
- `dimensoesDaBanca(Banca $defesa): array` — `[codigo => ?float]` para os 8 códigos de `DimensaoQualidade::PADRAO`. Para cada item: nota normalizada `nota / nota_maxima × 10` de cada avaliador; **mediana** entre avaliadores; na dimensão, média dos itens ponderada pelo `peso` do item (pesos que a CPA/Secretaria definiu no barema). Sem item → null.
- `pontuacaoProducao(TrabalhoAcademico $t): ?float` — 0–10. Pontos: publicação conferida com `derivada_dissertacao` e `situacao` publicada (1,0) ou aceita (0,75); +0,5 se `discente_primeiro_autor`; +0,5 se `coautoria_internacional`; cada extensão +0,5; cada popularização +0,25. Nota = `min(10, 10 × pontos ÷ (2 × mínimo do curso))` (mínimo de `PedidoHomologacaoService::minimoPublicacoes`). Sem nenhum registro → 0 (produção declarada é exigida na defesa).
- Dimensão `producao` = média simples entre a parte da banca e a pontuação de produção quando as duas existem; senão a que existir.
- `indiceTrabalho(Banca $defesa): array` — `['dimensoes' => [codigo => ?float], 'indice' => ?float, 'classe' => ?int, 'periodo' => ?PeriodoQualidade, 'regras' => string[]]`. Pesos das dimensões do período da data da defesa (`data_hora`, senão `updated_at`); sem período → iguais. Índice = `exp(Σ wᵢ·ln(max(xᵢ, 0,5)) / Σ wᵢ)` só nas dimensões não nulas, arredondado a 2 casas. Classe pelas faixas do período (sem período: padrão) e regras de corte; `regras` lista as aplicadas, ex. `'Reprovada por plágio'`, `'Dimensão abaixo de 4: no máximo Suficiente'`.
- Defesa não finalizada → `indice` e `classe` null.

- [ ] **Step 1: Failing tests** (montar a defesa com `CriaCenarioDeBanca`, finalizar e gravar notas com dimensão):
  - `test_mediana_dos_avaliadores_por_item` — 3 avaliadores com 6, 8, 10 em um item de rigor (máx 10) → rigor 8,0.
  - `test_nota_maxima_diferente_e_normalizada` — item máx 5 com nota 4 → 8,0.
  - `test_dimensao_sem_item_fica_de_fora_e_pesos_renormalizam` — só rigor 8 e originalidade 6 com pesos iguais → índice `round(sqrt(48), 2)` = 6,93.
  - `test_nota_zero_nao_quebra` — dimensão com 0 → índice finito > 0.
  - `test_pontuacao_de_producao` — mínimo 1, um artigo publicado com primeiro autor (1,5) → 7,5; nada → 0.
  - `test_classes_e_regras_de_corte` — índice 9 com uma dimensão 6 → classe 2 com regra; plágio → 5; reprovado com índice 8 → 4; dimensão 3,5 com índice 7,5 → 3.
  - `test_pesos_do_periodo_sao_usados` — rigor peso 0,75 e originalidade 0,25 → `round(exp(0,75·ln 8 + 0,25·ln 6), 2)`.
  - `test_defesa_nao_finalizada_sem_indice`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=IndiceQualidadeTrabalhoTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: índice de qualidade do trabalho (banca e produção) em cinco classes`

---

### Task 4: Índice do programa e telas (CPA e orientador)

**Files:**
- Modify: `app/Services/IndiceQualidadeService.php`
- Create: `app/Http/Controllers/CpaQualidadeController.php`, `resources/views/cpa/index.blade.php`, `resources/views/avaliacoes/partials/nivel-trabalho.blade.php`
- Modify: `resources/views/avaliacoes/banca.blade.php` (inclui o quadro do nível quando defesa finalizada e usuário é o orientador vigente tipo orientador)
- Modify: `routes/web.php`
- Test: `tests/Feature/IndiceQualidadeProgramaTest.php`

**Interfaces:**
- `indicePrograma(PeriodoQualidade $p): array` — `['trabalhos' => [banca_id => indiceTrabalho], 'fontes' => [fonte => [codigo => ?float]], 'dimensoes' => [codigo => ?float], 'indice' => ?float, 'classe' => ?int, 'distribuicao' => [classe => n]]`. Defesas finalizadas do programa com data no período. Por fonte e dimensão, a **mediana** entre os trabalhos: `trabalhos` = dimensões dos índices dos trabalhos; `autoavaliacao_orientador` = notas normalizadas (`nota/nota_maxima×10`) agrupadas pela dimensão gravada, média por avaliação e mediana entre avaliações; `avaliacao_orientando` = média dos 5 elementos (dimensão `autonomia`), mediana entre avaliações; `espelho` = como a autoavaliação. Dimensão do programa = média das fontes disponíveis ponderada pelos pesos de `fonte` (renormalizados). Índice = média geométrica ponderada pelos pesos de `dimensao`; classe só pelas faixas.
- Rota `cpa.index` (GET `/cpa`, `?periodo=id`, padrão: período que contém hoje ou o mais recente): índice e classe do programa, perfil das 8 dimensões com as 4 fontes lado a lado, distribuição por classe e a lista de trabalhos (estudante, título, data, índice, classe, perfil).
- Quadro do orientador: "Como a banca interpretou o trabalho" — nível (classe e nome) e as 8 dimensões (barras 0–10), com a nota de que o nível usa os pesos definidos pela CPA.

- [ ] **Step 1: Failing tests**: `test_indice_do_programa_combina_fontes_com_pesos` (dois trabalhos; trabalhos rigor 8 e 6 → 7; autoavaliação rigor 9; pesos trabalhos 0,5 e autoavaliação 0,5 → rigor do programa 8); `test_fonte_sem_dados_fica_de_fora`; `test_distribuicao_por_classe`; `test_cpa_ve_qualidade_do_programa` (assertSee classe e nome do estudante); `test_secretaria_e_docente_sem_mandato_nao_veem` (403); `test_orientador_ve_nivel_do_trabalho_na_banca_finalizada`; `test_coorientador_avaliador_e_discente_nao_veem_o_nivel`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=IndiceQualidadeProgramaTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: índice de qualidade do programa; CPA vê a qualidade das dissertações e o orientador o nível do trabalho`

---

### Task 5: Cenário de teste e documentação

**Files:**
- Modify: `database/seeders/CenarioTesteSeeder.php` — Luciana (colaboradora) como membro da CPA (ato fictício), período "Quadriênio 2025–2028" (2025-01-01 a 2028-12-31) com faixas padrão.
- Modify: `CLAUDE.md` (módulo Índice de qualidade e CPA mínima; pontos de atenção), `docs/superpowers/operacao-fluxo-secretaria.md` (roteiro: finalizar a defesa da Juliana e ver o nível como Beatriz e a qualidade como Luciana), spec (Etapa 5 versão de teste feita; ainda faltam OpenAlex, Ollama, correção de rigor dos avaliadores, intervalos).
- [ ] **Step 1:** Seeder e docs.
- [ ] **Step 2: Run** `php artisan migrate:fresh --seed`, `db:seed --class=UsuariosTesteSeeder`, `db:seed --class=CenarioTesteSeeder` sem erro; `php artisan test --compact` todos passam.
- [ ] **Step 3: Commit** — `docs: etapa 5 (teste) — índice de qualidade e CPA mínima`
