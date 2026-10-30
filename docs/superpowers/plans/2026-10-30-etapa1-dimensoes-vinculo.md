# Etapa 1 — Dimensões e vínculo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** As 8 dimensões da qualidade existem no sistema; toda pergunta de barema é ligada a uma dimensão (barema só ativa com vínculo completo); toda nota de avaliação guarda a dimensão do momento em que foi dada.

**Architecture:** Tabela `dimensoes_qualidade` (8 linhas fixas, seeder). `barema_itens.dimensao_qualidade_id` (nulo no banco, obrigatório no formulário e na ativação). `banca_avaliacao_notas` e `autoavaliacao_orientador_notas` ganham `dimensao_qualidade_id` copiado do item ao salvar ("congelado").

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8 / SQLite nos testes, Blade + Bootstrap, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` (seções "Regras gerais", "As 8 dimensões", "Modelo para cada dimensão" item 5, "Etapas" item 1)

## Global Constraints

- As 8 dimensões, nesta ordem e com estes nomes: 1 Rigor científico · 2 Originalidade · 3 Relevância e impacto · 4 Produção associada · 5 Autonomia do estudante · 6 Aderência ao programa · 7 Processo formativo · 8 Integridade e conformidade. Códigos: `rigor`, `originalidade`, `relevancia`, `producao`, `autonomia`, `aderencia`, `processo`, `integridade`.
- Quem lança os baremas é a **Secretaria** (a CPA concebe o conteúdo). Nenhuma tela de CPA nesta etapa.
- Uma pergunta (item) pertence a **uma** dimensão.
- A dimensão gravada na nota **não muda** se o item for reclassificado depois.
- Migrations com `down()`, testadas no SQLite e rodadas no MySQL (migrate, rollback, migrate). Commits em português com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; `git add` só dos arquivos da tarefa (há trabalho não commitado de outra frente).

## Review Focus

1. Barema já ativo cujo item perde a dimensão (edição) → o barema não pode ficar ativo com item sem dimensão: a edição do item exige dimensão, então isso não acontece (teste na Tarefa 1).
2. Ativar barema com item **inativo** sem dimensão → permitido (só itens ativos contam) (teste na Tarefa 1).
3. Item reclassificado depois de avaliado → notas antigas mantêm a dimensão antiga; notas novas usam a nova (teste na Tarefa 2).
4. Nota salva como rascunho e depois enviada → dimensão gravada nas duas gravações, sem duplicar linha (teste na Tarefa 2).
5. Homologação com barema ativo que tenha item sem dimensão (dado antigo) → tratado como barema incompleto: "Barema de … não cadastrado." não serve; mensagem própria "Barema de … com perguntas sem dimensão." (teste na Tarefa 1).

---

### Task 1: Dimensões e vínculo das perguntas

**Files:**
- Create: `database/migrations/2026_10_30_100000_create_dimensoes_qualidade_e_vinculo.php`, `app/Models/DimensaoQualidade.php`, `database/seeders/DimensoesQualidadeSeeder.php`
- Modify: `app/Models/BaremaItem.php` (fillable, `dimensao(): BelongsTo`), `app/Http/Controllers/BaremaItemController.php:20` (regra `dimensao_qualidade_id` required exists), `resources/views/baremas/edit.blade.php` (select "Dimensão" nos formulários de incluir/editar item; selo da dimensão na lista), `app/Http/Controllers/BaremaController.php:100-103` (ativar barema exige todos os itens ativos com dimensão), `app/Services/BaremasDaBancaService.php` (`faltantes` acusa barema ativo com item sem dimensão), `database/seeders/DatabaseSeeder.php`, `database/seeders/CenarioTesteSeeder.php` (itens de teste com dimensão), `tests/Concerns/CriaBaremasAtivos.php` (itens com dimensão)
- Test: `tests/Feature/DimensoesQualidadeTest.php`

**Interfaces:**
- Produces: `dimensoes_qualidade` (`codigo` único, `nome`, `ordem`, `descricao` null); `DimensaoQualidade::doCodigo(string): self`; `barema_itens.dimensao_qualidade_id` FK null `nullOnDelete`; `BaremaItem::dimensao()`.
- Mensagens: item sem dimensão → "Escolha a dimensão da pergunta."; ativar barema incompleto → "Todas as perguntas ativas precisam de uma dimensão antes de ativar o barema."; homologação → "Barema de {tipo} com perguntas sem dimensão."

- [ ] **Step 1: Testes que falham:** seeder cria as 8 dimensões na ordem; incluir/editar item sem dimensão → erro; ativar barema com item ativo sem dimensão → erro e barema segue inativo; item inativo sem dimensão não impede; tela do barema mostra a dimensão de cada pergunta; homologação recusa com barema ativo que tem pergunta sem dimensão (Review Focus 5).
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar.** Seeder de cenário: dimensões dos itens de teste por conteúdo (ex.: "metodologia" → rigor; "originalidade" → originalidade; "redação/apresentação" → rigor ou relevância conforme o caso; autoavaliação → processo; confidenciais de autonomia/arguição → autonomia).
- [ ] **Step 4: Rodar** → PASS; suíte; MySQL migrate/rollback/migrate; `db:seed --class=DimensoesQualidadeSeeder` e `--class=CenarioTesteSeeder`.
- [ ] **Step 5: Commit** — `feat: dimensões da qualidade e vínculo obrigatório de cada pergunta de barema`.

---

### Task 2: Nota gravada com a dimensão congelada

**Files:**
- Create: `database/migrations/2026_10_30_101000_add_dimensao_to_notas.php`
- Modify: `app/Models/BancaAvaliacaoNota.php`, `app/Models/AutoavaliacaoOrientadorNota.php` (fillable), `app/Http/Controllers/AvaliacaoController.php:294` (grava `dimensao_qualidade_id` do item), `app/Http/Controllers/AutoavaliacaoOrientadorController.php` (idem)
- Test: `tests/Feature/NotaDimensaoCongeladaTest.php`

**Interfaces:** Consumes `BaremaItem::dimensao_qualidade_id` (Tarefa 1). Produces `banca_avaliacao_notas.dimensao_qualidade_id` e `autoavaliacao_orientador_notas.dimensao_qualidade_id` (FK null `nullOnDelete`).

- [ ] **Step 1: Testes que falham:** avaliador salva notas → cada nota tem a dimensão do item; reclassificar o item depois não muda a nota antiga e a próxima avaliação grava a nova (Review Focus 3); rascunho e envio gravam a dimensão sem duplicar (Review Focus 4); autoavaliação grava a dimensão.
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar.** **Step 4: Rodar** → PASS; suíte; MySQL.
- [ ] **Step 5: Commit** — `feat: notas das avaliações guardam a dimensão do momento`.

---

### Task 3: Documentação

**Files:** Modify `CLAUDE.md` (módulo Baremas: dimensão obrigatória por pergunta; notas com dimensão congelada), `docs/superpowers/operacao-fluxo-secretaria.md` (ao lançar barema, escolher a dimensão de cada pergunta conforme a CPA definiu).

- [ ] **Step 1:** Atualizar os dois arquivos. **Step 2:** Suíte inteira verde. **Step 3: Commit** — `docs: dimensões nos baremas`.
