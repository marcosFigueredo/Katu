# Dados para o Lattes das bancas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cada membro de uma banca finalizada vê, na lista "Bancas do {sigla} de que participou", um quadro com os dados da participação nos campos do Lattes; áreas CNPq e setor CNAE vêm de listas únicas mantidas pela Secretaria e escolhidas pelo estudante.

**Architecture:** Duas tabelas de referência globais (`areas_cnpq` hierárquica, `setores_cnae`) com seeder a partir de CSVs oficiais em `database/data/`, e uma tela de manutenção para a Secretaria. O trabalho ganha campos para o Lattes e um pivô de áreas; o programa ganha a homepage dos trabalhos. Um `DadosLattesBancaService` monta os campos; a área do docente lista as bancas finalizadas e abre o quadro.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8 (3307) / SQLite nos testes, Blade + Bootstrap, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-29-dados-lattes-bancas-design.md`

## Global Constraints

- Nomes em português; rotas `recurso.acao` em `['auth','verified']`; autorização por `users.categoria`/vínculo no controller.
- Listas **únicas para o sistema** (sem `programa_id`); qualquer Secretaria inclui, edita e ativa/desativa; o estudante só vê itens ativos; item em uso não é apagado.
- Dados oficiais, nunca digitados de memória: CNPq (Árvore do Conhecimento, até subárea) e CNAE 2.0 (divisões, IBGE `servicodados.ibge.gov.br/api/v2/cnae/divisoes`), conferidos com o nível do formulário do Lattes.
- Quadro do Lattes só para banca `status = finalizada` e só para quem participou (avaliador ativo da banca ou orientador vigente).
- Campo sem valor aparece como "—"; cada campo tem botão de copiar.
- Rótulos fixos de natureza: "Mestrado", "Doutorado", "Exame de qualificação de mestrado", "Exame de qualificação de doutorado" (as naturezas "Curso de aperfeiçoamento/especialização" e "Graduação" existem só na lista).
- Migrations com `down()`, testadas no SQLite e rodadas no MySQL (migrate, rollback, migrate). Commits em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; `git add` só dos arquivos da tarefa (há trabalho não commitado de outra frente no working tree).

## Review Focus

1. Docente que é avaliador de uma banca e orientador de outra → cada banca aparece uma vez na lista, com o quadro certo (teste na Tarefa 4).
2. Banca cancelada ou ainda em avaliação → não aparece na lista; URL direta do quadro → 403 (teste na Tarefa 4).
3. Item de área/CNAE desativado depois de escolhido pelo estudante → o trabalho mantém a escolha e o quadro continua mostrando; o seletor do estudante não oferece mais o item (teste na Tarefa 3).
4. Estudante tenta gravar área inexistente ou inativa por POST forjado → erro de validação, nada gravado (teste na Tarefa 3).
5. Programa sem homepage, trabalho sem áreas/CNAE/título em inglês → quadro abre com "—" nesses campos, sem erro (teste na Tarefa 4).

---

### Task 1: Listas de áreas do CNPq e setores CNAE (dados oficiais e tela da Secretaria)

**Files:**
- Create: `database/data/areas_cnpq.csv`, `database/data/setores_cnae.csv`, `database/migrations/2026_10_29_130000_create_areas_cnpq_e_setores_cnae_tables.php`, `app/Models/AreaCnpq.php`, `app/Models/SetorCnae.php`, `database/seeders/TabelasLattesSeeder.php`, `app/Http/Controllers/TabelaLattesController.php`, `resources/views/tabelas-lattes/index.blade.php`, `resources/views/tabelas-lattes/form.blade.php`
- Modify: `database/seeders/DatabaseSeeder.php` (chama `TabelasLattesSeeder`), `routes/web.php`, `resources/views/layouts/app.blade.php` (link "Áreas CNPq e CNAE" em Configurações)
- Test: `tests/Feature/TabelasLattesTest.php`

**Interfaces:**
- Produces:
  - `areas_cnpq`: `codigo` string(12) único, `nome`, `nivel` enum(`grande_area`,`area`,`subarea`), `pai_id` FK self null, `ativo` bool. `AreaCnpq::caminho(): string` → "Grande área › Área › Subárea".
  - `setores_cnae`: `codigo` string(10) único, `nome`, `secao` string(1) null, `ativo` bool.
  - `TabelasLattesSeeder` lê os CSVs (`codigo;nome;nivel;codigo_pai` e `codigo;nome;secao`) com `firstOrCreate` por `codigo` (idempotente; só cria o que falta — não renomeia nem reativa item que a Secretaria editou).
  - Rotas (só `sec`): `tabelas-lattes.index` (GET `/tabelas-lattes?lista=cnpq|cnae&busca=`), `tabelas-lattes.create`/`store`, `tabelas-lattes.edit`/`update` (`/tabelas-lattes/{lista}/{id}`), `tabelas-lattes.alternar` (PATCH ativa/desativa).

- [ ] **Step 1: Obter os dados oficiais.** IBGE: `curl -s https://servicodados.ibge.gov.br/api/v2/cnae/divisoes` → CSV `codigo;nome;secao` (divisão 2 dígitos, descrição, `secao.id`). CNPq: Árvore do Conhecimento (`lattes.cnpq.br/web/dgp/arvore-do-conhecimento`) até subárea → CSV `codigo;nome;nivel;codigo_pai` (códigos de 8 dígitos, ex. `40000001` Ciências da Saúde). Conferir o nível do setor com o formulário de bancas do Lattes; se diferir, registrar Ruling e seguir o Lattes. Se a fonte do CNPq não puder ser baixada, **parar e perguntar** (não digitar de memória).
- [ ] **Step 2: Testes que falham:** `test_seeder_carrega_as_listas_e_e_idempotente` (contagens > 0; 9 grandes áreas; rodar 2x não duplica); `test_secretaria_busca_inclui_edita_e_desativa_area_e_setor`; `test_item_em_uso_nao_e_apagado_so_desativado` (não há rota de exclusão); `test_docente_e_discente_recebem_403`; `test_mudanca_vale_para_todos_os_programas` (Secretaria de outro programa vê o item editado).
- [ ] **Step 3: Rodar** `php artisan test tests/Feature/TabelasLattesTest.php` → FAIL.
- [ ] **Step 4: Implementar** migration, models, seeder, controller, telas e menu.
- [ ] **Step 5: Rodar** → PASS; suíte → sem regressão; MySQL migrate/rollback/migrate; `php artisan db:seed --class=TabelasLattesSeeder`.
- [ ] **Step 6: Commit** — `feat: listas de áreas do CNPq e setores CNAE mantidas pela Secretaria`.

---

### Task 2: Homepage dos trabalhos no programa; país, estado e município da instituição

**Files:**
- Create: `database/migrations/2026_10_29_131000_add_homepage_trabalhos_e_pais_instituicao.php`
- Modify: `app/Models/Programa.php` (fillable), `app/Http/Controllers/ProgramaController.php:50` (regra `homepage_trabalhos` `nullable|url|max:255`), `resources/views/programas/form.blade.php` (campo "Homepage dos trabalhos", após "Site"); `app/Models/Instituicao.php` (fillable `pais`), `app/Http/Controllers/InstituicaoController.php:28` (regra `pais` `required|string|max:100`), `resources/views/instituicoes/edit.blade.php` e o formulário de criação da instituição (campos **País**, **Estado**, **Município** — o rótulo "Cidade" vira "Município"; coluna continua `cidade`)
- Test: testes existentes dos formulários de programa e de instituição

**Interfaces:** Produces `programas.homepage_trabalhos` (string null); `instituicoes.pais` (string(100), default `'Brasil'`, preenchido nas linhas existentes).

- [ ] **Step 1: Testes que falham:** programa salva e mostra a homepage; URL inválida → erro em `homepage_trabalhos`; instituição salva país, estado e município, e o formulário mostra o rótulo "Município"; instituição nova sem país informado fica "Brasil" na tela (valor inicial do campo).
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar.** **Step 4: Rodar** → PASS; suíte; MySQL migrate/rollback/migrate.
- [ ] **Step 5: Commit** — `feat: homepage dos trabalhos no programa; país, estado e município da instituição`.

---

### Task 3: Dados para o Lattes no cadastro do trabalho (estudante)

**Files:**
- Create: `database/migrations/2026_10_29_132000_add_dados_lattes_to_trabalhos_academicos.php`, `app/Http/Controllers/TrabalhoLattesController.php`, `resources/views/meu-trabalho/lattes.blade.php`
- Modify: `app/Models/TrabalhoAcademico.php` (fillable, `areasCnpq(): BelongsToMany`, `setorCnae(): BelongsTo`), `routes/web.php`, `resources/views/meu-trabalho/index.blade.php` (card "Dados para o Lattes" ao lado de "Impacto da Dissertação", linha ~395)
- Test: `tests/Feature/TrabalhoLattesTest.php`

**Interfaces:**
- Consumes: `AreaCnpq`, `SetorCnae` (Tarefa 1).
- Produces: colunas em `trabalhos_academicos`: `titulo_en` string(500) null, `outras_informacoes` text null, `outras_informacoes_en` text null, `visibilidade` enum(`publica`,`privada`) default `publica`, `setor_cnae_id` FK null `nullOnDelete`; pivô `trabalho_area_cnpq` (`trabalho_academico_id`, `area_cnpq_id`, único). Rotas (só `discente`, o próprio trabalho): `meu-trabalho.lattes.edit` (GET `/meu-trabalho/lattes`), `meu-trabalho.lattes.update` (PUT). Sucesso → `meu-trabalho.lattes.edit` com `success` "Dados para o Lattes salvos.".

- [ ] **Step 1: Testes que falham:** `test_estudante_salva_areas_setor_textos_e_visibilidade`; `test_titulo_em_ingles_vem_da_submissao_latex` (submissão vigente com `elementos['titulo_en']` → campo preenchido no GET); `test_area_inativa_ou_inexistente_e_recusada` (Review Focus 4); `test_item_desativado_depois_continua_no_trabalho_mas_some_do_seletor` (Review Focus 3); `test_visibilidade_padrao_e_publica`; `test_outro_perfil_recebe_403`.
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar** (validação: `areas` array de ids `exists` em `areas_cnpq` ativo e nível `subarea` ou `area`; `setor_cnae_id` `exists` ativo; textos `max:5000`; `visibilidade` in). **Step 4: Rodar** → PASS; suíte; MySQL.
- [ ] **Step 5: Commit** — `feat: estudante informa áreas do CNPq, setor CNAE e textos para o Lattes`.

---

### Task 4: Bancas de que o docente participou e quadro do Lattes

**Files:**
- Create: `app/Services/DadosLattesBancaService.php`, `resources/views/avaliacoes/lattes.blade.php`
- Modify: `app/Http/Controllers/AvaliacaoController.php` (`index` → `$bancasParticipadas`; nova ação `lattes(Banca $banca)`), `routes/web.php` (`GET /avaliacoes/{banca}/lattes` → `avaliacoes.lattes`), `resources/views/dashboards/docente.blade.php` (seção "Bancas do {sigla} de que participou"; "Bancas que você orienta" só se houver)
- Test: `tests/Feature/DadosLattesBancaTest.php`

**Interfaces:**
- Consumes: Tarefas 1–3.
- Produces: `DadosLattesBancaService::campos(Banca $banca): list<array{rotulo: string, valor: string}>` na ordem do spec (Natureza, Tipo, Título, Ano, País, Idioma, Homepage do trabalho, Nome do candidato, Instituição, Curso, Participantes da banca, Palavras-chave, Áreas, Setor de aplicação, Outras informações, Título em inglês, Other information, Visibilidade). Regras de origem: tabela do spec. País = `instituicoes.pais` (Tarefa 2). Idioma pela submissão LaTeX vigente (`pt` → "Português", `en` → "Inglês"; sem submissão → "Português"). Curso = "{ProgramaCurso::nome} — {Programa::nome}". Participantes = orientador, coorientadores vigentes, avaliadores ativos e discente, um por linha com o papel. Vazio → "—".

- [ ] **Step 1: Testes que falham:** `test_campos_da_defesa_de_mestrado` (natureza "Mestrado", tipo "Acadêmico", ano, participantes com orientador e discente, áreas no caminho, homepage do programa); `test_natureza_da_qualificacao_de_doutorado`; `test_campos_vazios_aparecem_com_travessao` (Review Focus 5); `test_lista_mostra_bancas_finalizadas_de_que_participou_uma_vez_cada` (avaliador numa, orientador noutra; Review Focus 1); `test_banca_nao_finalizada_ou_cancelada_fica_fora_e_quadro_da_403` (Review Focus 2); `test_quem_nao_participou_recebe_403`; `test_externo_nao_ve_bancas_que_voce_orienta`.
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar** (botão copiar com `navigator.clipboard.writeText`). **Step 4: Rodar** → PASS; suíte.
- [ ] **Step 5: Commit** — `feat: bancas de que o docente participou com os dados para o Lattes`.

---

### Task 5: Dados de teste, documentação e verificação manual

**Files:**
- Modify: `database/seeders/CenarioTesteSeeder.php` (trabalho da Juliana com 2 áreas do CNPq, setor CNAE "Atividades de atenção à saúde humana" se existir, título em inglês, outras informações PT/EN; programa com homepage fictícia), `CLAUDE.md` (módulo "Dados para o Lattes" e as listas), `docs/superpowers/operacao-fluxo-secretaria.md` (tela das listas; homepage do programa)
- Test: `tests/Feature/CenarioTesteSeederTest.php`

- [ ] **Step 1: Teste que falha:** o seeder deixa o trabalho da Juliana com áreas e título em inglês.
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar.** **Step 4: Rodar** → PASS; suíte; `db:seed --class=TabelasLattesSeeder` e `--class=CenarioTesteSeeder` no MySQL; verificação por HTTP: Secretaria abre e edita as listas; `discente.completo` salva os dados; `colaborador@ppgmsb.test` vê a qualificação finalizada da Juliana em "Bancas do PPGMSB de que participou" e o quadro preenchido.
- [ ] **Step 5: Commit** — `docs: dados para o Lattes; cenário de teste com áreas e textos`.
