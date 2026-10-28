# Orientação no cadastro do discente, troca auditável e histórico — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Secretaria registra o orientador já no cadastro do discente, troca o orientador (só antes da qualificação) com processo SEI, justificativa e data automática, inclui/retira coorientadores, e todo período de orientação fica guardado; só a orientação vigente dá acesso.

**Architecture:** `orientacoes` vira um registro de períodos (`inicio_em`/`fim_em`); `ativo = true` ⇔ vigente (`fim_em` nulo). A relação `TrabalhoAcademico::orientacoes()` passa a devolver **só as vigentes** — assim os ~20 pontos que já procuram "o orientador" pela relação continuam certos sem edição — e `orientacoesHistorico()` devolve todas. Um `OrientacaoService` concentra as operações; a tela sai de "Orientações" (menu Bancas) e vai para a edição do discente em Usuários.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8 (local, porta 3307), Blade + Tailwind/Bootstrap, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-10-28-orientacao-historico-design.md`

## Global Constraints

- Nomes em português; rotas `recurso.acao` dentro de `['auth','verified']`; autorização por `users.categoria` no controller (sem gates/policies).
- Telas da Secretaria filtram pelo programa dela (`$this->programaDaSecretaria()` / `garantirProgramaDaSecretaria()`).
- Nomes de índices e FKs com até 64 caracteres (MySQL).
- Dados atuais são de teste e descartáveis: sem backfill; seeders/factories acompanham o esquema. Migrations com `down()` funcional; rodar `php artisan migrate` no MySQL local ao fim de cada tarefa que tenha migration.
- Troca de orientador: novo orientador, processo SEI e justificativa **obrigatórios**; data = hoje, automática; recusada se o trabalho tem banca `tipo = qualificacao` com `status` `em_avaliacao` ou `finalizada`, ou qualquer banca `tipo = defesa` não cancelada.
- Coorientador: inclusão/retirada sem SEI nem justificativa; limite `programas.max_coorientadores` contado sobre os **vigentes**; só vale para novas inclusões.
- Orientador: docente ativo do programa com `pode_orientar = true`. Coorientador: docente ativo do programa (interno ou externo). O orientador vigente não pode ser coorientador vigente.
- Nunca apagar linhas de `orientacoes`; encerrar = `ativo = false`, `fim_em = hoje`.
- **Trabalho paralelo:** os arquivos de Homologação (`HomologacaoBancaController`, `HomologacaoBancaService`, `Banca`, `AvaliacaoController`, `bancas/homologacao*.blade.php`, `bancas/index.blade.php`, `avaliacoes/banca.blade.php`, `routes/web.php`, e-mails de homologação) têm alterações não commitadas de outra frente. Este plano só toca `routes/web.php` desse grupo. Não editar os demais. Não rodar testes em paralelo com outro processo de testes (Storage::fake compartilhado).
- Commits em português, terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; `git add` só dos arquivos da tarefa.

## Review Focus

1. Troca para o mesmo docente que já é o orientador vigente → recusa com mensagem, nada gravado (teste na Tarefa 1).
2. Docente que saiu e volta depois (ex.: ex-coorientador vira orientador; ex-orientador volta como coorientador) → permitido; não pode esbarrar em índice único (teste na Tarefa 1).
3. Duas trocas no mesmo dia → a primeira linha fica com `inicio_em = fim_em = hoje`, nada se perde, só a última é vigente (teste na Tarefa 1).
4. Secretaria de outro programa tentando trocar/incluir via POST forjado → 403 (teste na Tarefa 2).
5. Discente sem orientador vigente tentando propor banca → mensagem "procure a Secretaria para registrar seu orientador" (teste na Tarefa 4).

---

### Task 1: Esquema de períodos, relação vigente e `OrientacaoService`

**Files:**
- Create: `database/migrations/2026_10_28_100000_add_periodo_to_orientacoes_table.php`
- Modify: `app/Models/Orientacao.php`, `app/Models/TrabalhoAcademico.php:55-58`, `app/Models/DocenteProgramaVinculo.php:39-42`, `app/Http/Controllers/RelatorioController.php:250` (só acrescentar `->where('ativo', true)`)
- Create: `app/Services/OrientacaoService.php`
- Test: `tests/Feature/OrientacaoServiceTest.php`

**Interfaces:**
- Produces:
  - Colunas em `orientacoes`: `inicio_em` date not null; `fim_em` date null; `processo_sei` string(50) null; `justificativa` text null; `registrado_por` FK `users` null, `nullOnDelete`, nome `orientacoes_registrado_por_fk`.
  - Índice único `orientacoes_trab_doc_unique` removido; no lugar, índice simples `orientacoes_trab_doc_idx` (`trabalho_academico_id`, `docente_programa_vinculo_id`). Criar o novo **antes** de derrubar o único (erro 1553 da FK no MySQL); no `down()`, recriar o único antes de derrubar o simples.
  - `Orientacao`: fillable com as colunas novas; casts `inicio_em`/`fim_em` => `date`; evento `creating` preenche `inicio_em = today()` quando nulo (mantém os testes/seeders atuais que criam sem data); `scopeVigentes($q)` => `where('ativo', true)`; `registradoPor(): BelongsTo`.
  - `TrabalhoAcademico::orientacoes(): HasMany` — só vigentes (`->where('ativo', true)`); `orientacoesHistorico(): HasMany` — todas, `orderBy('inicio_em')->orderBy('id')`; `orientadorVigente(): ?Orientacao`.
  - `DocenteProgramaVinculo::orientacoes()` — só vigentes.
  - `App\Services\OrientacaoService` (métodos lançam `Illuminate\Validation\ValidationException` com chave de campo; tudo em `DB::transaction` com `lockForUpdate()` nas orientações vigentes do trabalho):
    - `definirOrientadorInicial(TrabalhoAcademico $t, int $docenteVinculoId, User $secretaria): Orientacao` — recusa (`orientador_vinculo_id`) se já há orientador vigente.
    - `trocarOrientador(TrabalhoAcademico $t, int $novoVinculoId, string $processoSei, string $justificativa, User $secretaria): Orientacao` — exige orientador vigente (senão use o inicial); recusa mesmo docente; encerra o vigente; se o novo for coorientador vigente, encerra essa coorientação; cria o novo com `processo_sei`, `justificativa`, `registrado_por`. (A regra da qualificação e a devolução da banca entram na Tarefa 4.)
    - `incluirCoorientador(TrabalhoAcademico $t, int $docenteVinculoId, User $secretaria): Orientacao` — `ordem` = maior ordem vigente + 1.
    - `retirarCoorientador(Orientacao $coorientacao, User $secretaria): void` — só `tipo = coorientador` vigente.
    - Validações de elegibilidade (programa do trabalho, ativo, `pode_orientar` para orientador) e as mensagens abaixo ficam no serviço, para o controller e o cadastro reaproveitarem.

Mensagens (texto exato):
- `orientador_vinculo_id`: "O orientador deve ser docente ativo do programa com permissão de orientação."
- `orientador_vinculo_id` (já existe): "O discente já tem orientador. Use a troca de orientador."
- `orientador_vinculo_id` (mesmo): "O novo orientador é o mesmo que o atual."
- `processo_sei`: "Informe o número do processo SEI que validou a troca."
- `justificativa`: "Informe a justificativa da troca."
- `coorientador_vinculo_id` (limite): "O programa permite no máximo {N} coorientador(es) por discente."
- `coorientador_vinculo_id` (é o orientador): "O orientador não pode ser também coorientador."
- `coorientador_vinculo_id` (já é): "Este docente já é coorientador do discente."
- `coorientador_vinculo_id` (inelegível): "Selecione um docente ativo do programa do discente."

- [ ] **Step 1: Escrever os testes que falham** em `tests/Feature/OrientacaoServiceTest.php` (RefreshDatabase; montar programa, discente com trabalho, docentes com vínculo, como em `tests/Feature/OrientacaoCoorientadoresTest.php`):
  - `test_define_orientador_inicial_com_data_de_hoje`: `inicio_em == today()`, `fim_em` nulo, `registrado_por` = secretaria, `ativo` true.
  - `test_nao_define_inicial_quando_ja_ha_orientador`: ValidationException em `orientador_vinculo_id`.
  - `test_troca_encerra_o_anterior_e_preserva_o_periodo`: depois da troca, `orientacoesHistorico()` tem 2 linhas; a antiga com `fim_em == today()`, `ativo` false; a nova com `processo_sei`, `justificativa`, `inicio_em == today()`; `orientadorVigente()` é a nova; `orientacoes()` não contém a antiga.
  - `test_troca_exige_sei_e_justificativa`: strings vazias → erros em `processo_sei` e `justificativa`; nada muda no banco.
  - `test_troca_para_o_mesmo_orientador_e_recusada` (Review Focus 1).
  - `test_coorientador_promovido_a_orientador_encerra_a_coorientacao` e `test_ex_orientador_pode_voltar_como_coorientador` (Review Focus 2).
  - `test_duas_trocas_no_mesmo_dia_preservam_tudo` (Review Focus 3): 3 linhas, uma vigente.
  - `test_limite_de_coorientadores_conta_so_vigentes`: com `max_coorientadores = 1`, incluir, retirar, incluir outro → ok; incluir um segundo vigente → erro com "no máximo 1".
  - `test_orientador_nao_pode_ser_coorientador` e `test_externo_pode_ser_coorientador_mas_nao_orientador`.
  - `test_retirar_coorientador_mantem_o_registro`: linha continua no histórico com `fim_em` hoje.
  - `test_relacao_orientacoes_do_docente_so_traz_vigentes`.
- [ ] **Step 2: Rodar e ver falhar** — `php artisan test tests/Feature/OrientacaoServiceTest.php` → FAIL (classe/colunas inexistentes).
- [ ] **Step 3: Migration, models e serviço** conforme Interfaces.
- [ ] **Step 4: Rodar** `php artisan test tests/Feature/OrientacaoServiceTest.php` → PASS; depois a suíte inteira `php artisan test` → sem regressão (as falhas pré-existentes de `HomologacaoBancaTest` causadas pelo trabalho paralelo não commitado, se ainda houver, são anotadas e não corrigidas).
- [ ] **Step 5: MySQL local** — `php artisan migrate`, depois `php artisan migrate:rollback --step=1` e `php artisan migrate` → sem erro.
- [ ] **Step 6: Commit** — `feat: orientações com período, troca auditável e relação só com as vigentes`.

---

### Task 2: Bloco "Orientação" na edição do discente e saída do menu Bancas

**Files:**
- Create: `app/Http/Controllers/OrientacaoDiscenteController.php`, `resources/views/usuarios/partials/orientacao.blade.php`
- Modify: `resources/views/usuarios/edit.blade.php` (incluir o partial quando o usuário é discente com trabalho), `app/Http/Controllers/UsuarioController.php@edit` (carregar `trabalho`, `orientacoesHistorico` e as opções de docentes), `routes/web.php` (só as rotas desta tarefa; remover as três `orientacoes.*`), `resources/views/layouts/app.blade.php` (remover o link "Orientações" e `'orientacoes.*'` de `$bancasAberto` e o `elseif` de `$secaoAtual`), `app/Http/Controllers/RelatorioController.php:837` (link → `route('usuarios.index', ['classe' => 'sem_orientador'])`; a classe nasce na Tarefa 3 — até lá a lista ignora o valor)
- Delete: `app/Http/Controllers/OrientacaoController.php`, `resources/views/orientacoes/index.blade.php`, `resources/views/orientacoes/edit.blade.php`, `tests/Feature/SecretariaOrientacaoTest.php`, `tests/Feature/OrientacaoCoorientadoresTest.php` (os casos ainda válidos migram para os testes desta tarefa e da Tarefa 1; `test_programa_define_maximo_de_coorientadores` e `test_programa_novo_tem_um_coorientador_por_padrao` são do cadastro do programa — mover para `tests/Feature/OrientacaoDiscenteTelaTest.php` sem mudar)
- Test: `tests/Feature/OrientacaoDiscenteTelaTest.php`

**Interfaces:**
- Consumes: `OrientacaoService` (Tarefa 1), `TrabalhoAcademico::orientacoesHistorico()`, `orientadorVigente()`.
- Produces — rotas (todas `sec`, programa da Secretaria garantido pelo `programa_id` do trabalho):
  - `POST /usuarios/{usuario}/orientacao/orientador` → `usuarios.orientacao.definir` (campo `orientador_vinculo_id`)
  - `POST /usuarios/{usuario}/orientacao/troca` → `usuarios.orientacao.trocar` (campos `orientador_vinculo_id`, `processo_sei` max 50, `justificativa` max 2000)
  - `POST /usuarios/{usuario}/orientacao/coorientadores` → `usuarios.orientacao.coorientador.incluir` (campo `coorientador_vinculo_id`)
  - `DELETE /usuarios/{usuario}/orientacao/coorientadores/{orientacao}` → `usuarios.orientacao.coorientador.retirar`
  - Todas redirecionam para `usuarios.edit` com `success` ("Orientador registrado.", "Orientador trocado.", "Coorientador incluído.", "Coorientador retirado.").
- O partial mostra: orientador vigente (ou "Sem orientador" + formulário de inclusão); formulário "Trocar orientador" (novo orientador, processo SEI, justificativa; a data aparece como "hoje, automática"); coorientadores vigentes com botão "Retirar"; "Incluir coorientador" escondido quando o limite vigente foi atingido; **linha do tempo** com todas as linhas de `orientacoesHistorico()`: tipo, nome, `inicio_em`–`fim_em` (ou "atual"), e, quando houver, processo SEI e justificativa.

- [ ] **Step 1: Testes que falham** em `tests/Feature/OrientacaoDiscenteTelaTest.php`:
  - `test_edicao_do_discente_mostra_bloco_de_orientacao_e_linha_do_tempo` (após uma troca: vê nome antigo, nome novo, número SEI, justificativa).
  - `test_secretaria_troca_orientador_pela_tela` (POST → linhas no banco, redirect com success).
  - `test_troca_sem_sei_volta_com_erro` (`assertSessionHasErrors(['processo_sei'])`).
  - `test_inclui_e_retira_coorientador_pela_tela`.
  - `test_secretaria_de_outro_programa_recebe_403` em cada uma das 4 rotas (Review Focus 4); `test_docente_recebe_403`.
  - `test_menu_nao_tem_mais_orientacoes` (GET `usuarios.index` como sec: `assertDontSee('href="'.url('/orientacoes').'"', false)`) e `test_rota_antiga_nao_existe` (`$this->get('/orientacoes')->assertNotFound()`).
- [ ] **Step 2: Rodar e ver falhar** — `php artisan test tests/Feature/OrientacaoDiscenteTelaTest.php`.
- [ ] **Step 3: Controller, rotas, partial, menu, link do relatório; apagar o controller/views/testes antigos.**
- [ ] **Step 4: Rodar** o arquivo → PASS; `php artisan test` → sem regressão; `grep -rn "orientacoes\.\(index\|edit\|update\)" app resources routes tests` → nenhuma ocorrência.
- [ ] **Step 5: Commit** — `feat: orientação do discente gerida na edição em Usuários; sai a tela Orientações do menu Bancas`. Para `routes/web.php`, que tem alterações não commitadas de outra frente: só commitar se essas alterações já tiverem sido commitadas; caso contrário, parar e reportar `BLOCKED` em vez de commitar linhas alheias.

---

### Task 3: Orientador obrigatório no cadastro manual; selo e filtro "sem orientador"

**Files:**
- Modify: `app/Http/Controllers/UsuarioController.php` (`store` ~linhas 236-296 validação e 383-404 criação; `index` ~linhas 95-167 classe nova e contagem), `resources/views/usuarios/create.blade.php` (campo Orientador no bloco do discente), `resources/views/usuarios/index.blade.php` (aba/filtro e selo)
- Test: `tests/Feature/CadastroDiscenteOrientadorTest.php`

**Interfaces:**
- Consumes: `OrientacaoService::definirOrientadorInicial` (Tarefa 1).
- Produces: `store` valida `orientador_vinculo_id` `required_if:categoria,discente` + `exists` em `docente_programa_vinculos` (programa da Secretaria, `ativo`, `pode_orientar`); mensagem de obrigatório: "Selecione o orientador do discente."; cria a orientação pelo serviço **na mesma transação** do usuário. `index` aceita `classe=sem_orientador` (discentes do programa cujo trabalho não tem orientação vigente de tipo orientador), conta em `$contagens['sem_orientador']`, e a lista mostra o selo "sem orientador" ao lado de cada discente nessa situação (em qualquer aba).
- Importação por CSV **não muda**: discente importado nasce sem orientador.

- [ ] **Step 1: Testes que falham**:
  - `test_cadastro_de_discente_sem_orientador_e_recusado` (`assertSessionHasErrors(['orientador_vinculo_id'])`, nenhum usuário criado).
  - `test_cadastro_de_discente_cria_orientacao_vigente_com_data_de_hoje`.
  - `test_orientador_sem_pode_orientar_ou_de_outro_programa_e_recusado`.
  - `test_cadastro_de_docente_nao_exige_orientador`.
  - `test_discente_importado_aparece_com_selo_e_no_filtro_sem_orientador` (criar via `CadastroDiscenteService` ou o fluxo de importação existente em `tests/Feature/ImportacaoDiscentesTest.php`; GET `usuarios.index?classe=sem_orientador` vê o discente e o texto "sem orientador"; discente com orientador não aparece nesse filtro).
  - `test_formulario_de_cadastro_lista_so_docentes_que_podem_orientar`.
- [ ] **Step 2: Rodar e ver falhar** — `php artisan test tests/Feature/CadastroDiscenteOrientadorTest.php`.
- [ ] **Step 3: Implementar.** Ajustar testes existentes que cadastram discente por POST sem orientador (procurar `'categoria' => 'discente'` em `tests/Feature`) acrescentando um orientador válido.
- [ ] **Step 4: Rodar** o arquivo → PASS; `php artisan test` → sem regressão.
- [ ] **Step 5: Commit** — `feat: orientador obrigatório no cadastro do discente e filtro dos discentes sem orientador`.

---

### Task 4: Troca só antes da qualificação, devolução da banca aberta, e efeitos no acesso

**Files:**
- Modify: `app/Services/OrientacaoService.php` (`trocarOrientador`), `app/Http/Controllers/BancaController.php:403-406` (só o texto da mensagem), `resources/views/usuarios/partials/orientacao.blade.php` (esconder a troca e explicar quando bloqueada)
- Create: `app/Mail/BancaRevisaoTrocaOrientadorMail.php`, `resources/views/emails/banca-revisao-troca-orientador.blade.php`
- Test: `tests/Feature/TrocaOrientadorQualificacaoTest.php`

**Interfaces:**
- Consumes: Tarefa 1 e 2.
- Produces:
  - `OrientacaoService::trocaBloqueada(TrabalhoAcademico $t): ?string` — devolve o motivo (texto exato: "A troca de orientador só é possível antes da qualificação.") quando há banca `tipo = 'qualificacao'` com `status` em `['em_avaliacao','finalizada']`, ou banca `tipo = 'defesa'` com `status != 'cancelada'`; senão `null`. `trocarOrientador` lança ValidationException em `orientador_vinculo_id` com esse texto.
  - Na mesma transação da troca: cada banca do trabalho com `tipo = 'qualificacao'`, `status = 'cadastrada'` e `homologacao_status` em `['proposta','aguardando','devolvida','homologada']` passa a `homologacao_status = 'devolvida'` com `homologacao_observacoes` = "Orientador trocado em {d/m/Y} (SEI {processo}) — revisar a composição e reenviar." (prefixado ao texto existente, se houver). Atualizar só esses dois campos via query builder do model `Banca` — **não editar `app/Models/Banca.php`** (trabalho paralelo).
  - Se a banca estava `homologada`, enviar `BancaRevisaoTrocaOrientadorMail` a cada avaliador da banca (`$banca->avaliadores()`), depois do commit (`DB::afterCommit` ou `Mail::...->afterCommit()`). Assunto: "Banca voltou para revisão — troca de orientador".
  - Mensagem do `BancaController::validarComposicao` quando não há orientador: "Procure a Secretaria para registrar seu orientador antes de propor a banca."

- [ ] **Step 1: Testes que falham** em `tests/Feature/TrocaOrientadorQualificacaoTest.php` (`Mail::fake()`):
  - `test_troca_recusada_com_qualificacao_em_avaliacao` e `..._finalizada` (mensagem exata; nada muda).
  - `test_troca_recusada_com_banca_de_defesa`.
  - `test_troca_permitida_sem_banca`.
  - `test_troca_devolve_qualificacao_homologada_e_avisa_avaliadores`: `homologacao_status == 'devolvida'`, observação contém "Orientador trocado em" e o número SEI, `Mail::assertSent(BancaRevisaoTrocaOrientadorMail::class, n_avaliadores)`.
  - `test_troca_devolve_qualificacao_proposta_sem_email`.
  - `test_banca_cancelada_nao_bloqueia_nem_e_alterada`.
  - `test_orientador_anterior_perde_acesso`: depois da troca, o docente antigo não vê o trabalho na lista de `AvaliacaoController@index` (bancas do orientador) e `trabalho->orientacoes` não o contém; o novo vê.
  - `test_tela_esconde_troca_depois_da_qualificacao` (vê o texto do motivo, não vê o formulário).
  - `test_discente_sem_orientador_recebe_mensagem_ao_propor_banca` (Review Focus 5; usar a rota de proposta do discente coberta em `tests/Feature/BancaPropostaDiscenteTest.php`).
- [ ] **Step 2: Rodar e ver falhar** — `php artisan test tests/Feature/TrocaOrientadorQualificacaoTest.php`.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Rodar** o arquivo → PASS; `php artisan test` → sem regressão.
- [ ] **Step 5: Commit** — `feat: troca de orientador só antes da qualificação e devolução da banca aberta`.

---

### Task 5: Seeders, documentação e verificação manual

**Files:**
- Modify: seeders que criam orientações (procurar `Orientacao::create` / `orientacoes()->create` em `database/seeders`), `CLAUDE.md` (módulo "Orientação": tela na edição do discente; troca com SEI/justificativa/data automática só antes da qualificação; histórico em `orientacoes` com `inicio_em`/`fim_em`; relação `orientacoes()` = só vigentes, `orientacoesHistorico()` = todas; tabela de e-mails com `BancaRevisaoTrocaOrientadorMail`), `docs/superpowers/operacao-fluxo-secretaria.md` (passo a passo da Secretaria).

- [ ] **Step 1:** Seeders: todo discente de teste com orientador vigente, exceto um "sem orientador" (para a Secretaria ver o selo); um caso com troca registrada (SEI e justificativa fictícios) para a linha do tempo.
- [ ] **Step 2:** `php artisan migrate:fresh --seed` no MySQL local → sem erro.
- [ ] **Step 3:** `php artisan test` → sem regressão.
- [ ] **Step 4:** Verificação manual com `php artisan serve` e `secretaria@ppgmsb.test` / `uneb@123`: menu Bancas sem "Orientações"; cadastrar discente sem orientador → erro; com orientador → ok; editar discente → trocar orientador com SEI e justificativa → linha do tempo; incluir/retirar coorientador; aba "sem orientador". Anotar o resultado no relatório.
- [ ] **Step 5: Commit** — `docs: orientação do discente com histórico e troca auditável; seeders`.
