# Fluxo da Secretaria Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implementar o fluxo operacional da Secretaria para orientação, membros externos, critérios da CPA e homologação de bancas.

**Architecture:** Preservar os módulos existentes e acrescentar recursos focados por responsabilidade. A orientação passará a carregar o vínculo do discente; convites externos terão token hash e cadastro público; critérios CPA serão dados versionáveis por programa; homologação será uma máquina de estados separada do status de avaliação da banca para manter compatibilidade.

**Tech Stack:** Laravel 12, PHP 8.2+, MySQL 8+/SQLite de testes, Blade, Bootstrap 5, PHPUnit.

**Spec:** `docs/superpowers/specs/secretaria-fluxo.md`

## Global Constraints

- Nomes de código e interface seguem o português do projeto.
- Todas as rotas autenticadas usam `auth` e `verified`; rotas públicas ficam limitadas ao convite externo e usam throttle.
- PDFs e imagens de diploma ficam em `storage/app/private/`.
- `users.categoria` continua sendo a fonte de verdade para autorização.
- Não alterar nem descartar mudanças locais preexistentes.
- Não substituir o barema por critérios CPA; são camadas diferentes.

## Review Focus

- Token de convite inválido, expirado ou reutilizado não permite cadastro.
- Diploma ausente, formato inválido ou arquivo excessivo não cria membro externo.
- Membro externo pendente não aparece como avaliador habilitado até aprovação.
- Critério inativo não bloqueia uma nova submissão e critério obrigatório bloqueia homologação quando a evidência estiver ausente.
- Discente/docente não acessa ações administrativas nem consegue forjar o `programa_id` de outro programa.

### Task 1: Orientação acadêmica vinculada ao discente

**Files:**
- Create: `database/migrations/2026_09_25_100000_add_discente_vinculo_to_orientacoes_table.php`
- Modify: `app/Models/Orientacao.php`
- Modify: `app/Http/Controllers/OrientacaoController.php`
- Modify: `resources/views/orientacoes/index.blade.php`
- Modify: `resources/views/orientacoes/edit.blade.php`
- Test: `tests/Feature/SecretariaOrientacaoTest.php`

**Interfaces:**
- Consumes: `TrabalhoAcademico->discente_programa_vinculo_id` and existing orientation form.
- Produces: orientation rows with `discente_programa_vinculo_id` populated and unchanged legacy `trabalho_academico_id` behavior.

- [ ] Write failing tests for secretary-only assignment, same-program validation, and persisted discente vínculo.
- [ ] Run `php artisan test --compact tests/Feature/SecretariaOrientacaoTest.php` and confirm failure because the column/behavior is absent.
- [ ] Add nullable foreign key, backfill it from the work relation, and add the Eloquent relation/fillable field.
- [ ] Update the controller transaction to deactivate existing rows and create rows carrying both work and discente vínculo IDs.
- [ ] Update copy in the views to identify the academic discente vínculo rather than implying the orientation only belongs to a work.
- [ ] Re-run the focused test, then the full test suite.
- [ ] Commit only this task's files with `feat: vincular orientacao ao discente`.

### Task 2: Convite e validação de membro externo

**Files:**
- Create: `database/migrations/2026_09_25_110000_create_convites_membros_externos_table.php`
- Create: `database/migrations/2026_09_25_110100_add_diploma_to_docente_profiles_table.php`
- Create: `database/migrations/2026_09_25_110200_expand_cadastro_status_users.php`
- Create: `app/Models/ConviteMembroExterno.php`
- Create: `app/Mail/ConviteMembroExternoMail.php`
- Create: `resources/views/emails/convite-membro-externo.blade.php`
- Create: `app/Http/Controllers/MembroExternoController.php`
- Create: `resources/views/membros-externos/index.blade.php`
- Create: `resources/views/membros-externos/create.blade.php`
- Create: `resources/views/membros-externos/registro.blade.php`
- Create: `resources/views/membros-externos/pendente.blade.php`
- Modify: `app/Models/DocenteProfile.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/DocenteProgramaVinculo.php`
- Modify: `routes/web.php`
- Modify: `resources/views/dashboards/secretaria.blade.php`
- Test: `tests/Feature/MembroExternoTest.php`

**Interfaces:**
- Consumes: current `Programa`, `ProgramaUsuario`, `DocenteProfile`, `DocenteProgramaVinculo`, first-access mail flow.
- Produces: named routes `membros-externos.index`, `membros-externos.convites.store`, `membros-externos.registro`, `membros-externos.registro.store`, `membros-externos.aprovar` and `membros-externos.devolver`.

- [ ] Write failing tests for invitation authorization, public registration, invalid/expired/reused tokens, required diploma validation, approval, return, and cross-program protection.
- [ ] Run the focused test and verify the expected missing-route/model failures.
- [ ] Add invitation and diploma migrations using private storage-compatible path fields; keep MySQL enum changes guarded for SQLite tests.
- [ ] Implement the model token helpers with a hash in the database and a raw token only in the URL/mail.
- [ ] Implement secretary invite/list/approve/return actions inside transactions; only approval creates or activates `DocenteProgramaVinculo` with `tipo_vinculo=externo`.
- [ ] Implement public GET/POST registration with throttling, image/PDF validation, private disk storage and `cadastro_status=aguardando_validacao`.
- [ ] Add the invitation mail and Blade screens; expose the module from the secretary dashboard.
- [ ] Re-run focused and full tests.
- [ ] Commit only this task's files with `feat: implementar convite de membro externo`.

### Task 3: Critérios versionáveis da CPA

**Files:**
- Create: `database/migrations/2026_09_25_120000_create_criterios_cpa_table.php`
- Create: `database/migrations/2026_09_25_120100_create_banca_evidencias_table.php`
- Create: `app/Models/CriterioCpa.php`
- Create: `app/Models/BancaEvidencia.php`
- Create: `app/Http/Controllers/CriterioCpaController.php`
- Create: `resources/views/criterios-cpa/index.blade.php`
- Create: `resources/views/criterios-cpa/form.blade.php`
- Modify: `app/Models/Programa.php`
- Modify: `app/Models/Banca.php`
- Modify: `routes/web.php`
- Modify: `resources/views/dashboards/secretaria.blade.php`
- Test: `tests/Feature/CriterioCpaTest.php`

**Interfaces:**
- Consumes: active secretary program from `programaUsuarios`, existing program model, banca type/level.
- Produces: CRUD routes `criterios-cpa.*`, criteria relation on `Programa` and `Banca`, and evidence storage contract for the next student phase.

- [ ] Write failing tests for secretary CRUD, validation of stage/evidence type, ordering, activation, and cross-program isolation.
- [ ] Run the focused test and verify failure before production changes.
- [ ] Add criteria/evidence migrations with indexes and foreign keys; use strings for extensible domain values where existing MySQL enums would hinder SQLite tests.
- [ ] Implement model casts, relations and controller authorization/validation.
- [ ] Add the criteria list/form with required, stage, type, level, version and active controls.
- [ ] Add a reusable service method that returns active mandatory criteria for a banca and distinguishes absent/not applicable/informed evidence.
- [ ] Re-run focused and full tests.
- [ ] Commit only this task's files with `feat: configurar criterios da cpa`.

### Task 4: Homologação e devolução de bancas

**Files:**
- Create: `database/migrations/2026_09_25_130000_add_homologacao_to_bancas_table.php`
- Create: `app/Services/HomologacaoBancaService.php`
- Create: `app/Http/Controllers/HomologacaoBancaController.php`
- Create: `resources/views/bancas/homologacao.blade.php`
- Modify: `app/Models/Banca.php`
- Modify: `app/Http/Controllers/BancaController.php`
- Modify: `app/Http/Controllers/AvaliacaoController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/bancas/index.blade.php`
- Modify: `resources/views/dashboards/secretaria.blade.php`
- Test: `tests/Feature/HomologacaoBancaTest.php`

**Interfaces:**
- Consumes: `BancaEvidencia`, `CriterioCpa`, existing composition validation and evaluation transition.
- Produces: `bancas.homologacao`, `bancas.homologar`, `bancas.devolver`, explicit `homologacao_status`, audit fields and service transition methods.

- [ ] Write failing tests for pending queue, required evidence blocking, homologation release, return blocking, authorization and defense-after-failed-qualification rejection.
- [ ] Run the focused test and confirm the new state/service is missing.
- [ ] Add homologation fields with legacy-safe default `homologada` for existing records and `aguardando` for future submitted proposals.
- [ ] Implement `HomologacaoBancaService::homologar` and `::devolver` in transactions, recording actor/time/observation and updating `libera_envio_trabalho`.
- [ ] Remove direct secretary control of evaluation status from the homologation screen; keep existing edit route only for schedule/composition compatibility.
- [ ] Guard evaluation start against non-homologated bancas and guard defense creation against a failed qualification result.
- [ ] Add queue/actions to the banca index and dashboard.
- [ ] Re-run focused and full tests.
- [ ] Commit only this task's files with `feat: homologar bancas pela secretaria`.

### Task 5: Verificação integrada e operação local

**Files:**
- Modify: `README.md`
- Modify: `.env.example`
- Create: `docs/superpowers/operacao-fluxo-secretaria.md`
- Test: existing suite and build output.

- [ ] Run `php artisan migrate:fresh --seed` against the configured test database only after confirming the connection target.
- [ ] Run `php artisan test --compact` and record the final count.
- [ ] Run `npm run build`.
- [ ] Run `php artisan route:list --path=membros-externos`, `php artisan route:list --path=criterios-cpa` and `php artisan route:list --path=bancas`.
- [ ] Document the exact commands for MySQL, storage link (if needed for non-private assets), queue worker, mail log and Vite.
- [ ] Run `git diff --check` and inspect the final diff without claiming success until all outputs are read.
