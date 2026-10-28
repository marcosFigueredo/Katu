# CLAUDE.md — defesa-ppgmsb

## O que é este projeto

Sistema web Laravel para gerenciar o ciclo completo de qualificações e defesas de dissertações/teses do **PPGMSB** (Programa de Pós-Graduação em Modelagem em Saúde da Bahia — UNEB).

## Stack

- **Backend**: PHP 8.2+ · Laravel 12
- **Banco**: MySQL 8+ (conexão `mysql`, banco `defesa_ppgmsb`)
- **Frontend**: Tailwind CSS 3 · Alpine.js 3 · Bootstrap 5.3.3 · Bootstrap Icons 1.11.3
- **Build**: Vite 7 + Laravel Vite Plugin
- **IA**: Ollama local (modelo `qwen2.5:7b`) para análise de PDF
- **Fila**: `QUEUE_CONNECTION=database` (dev); Redis recomendado em produção
- **Auth**: Laravel Breeze

## Como rodar em desenvolvimento

```bash
composer dev        # serve + queue:listen + vite em paralelo
```

Ou separado:
```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Papéis de usuário (`users.categoria`)

| Valor | Papel |
|-------|-------|
| `sec` | Secretaria — gestão completa |
| `docente` | Avaliador / orientador |
| `discente` | Envio de documentos e acompanhamento |
| `admin` | Reservado para uso futuro — sem lógica implementada |

O campo `categoria` é a única fonte de verdade para autorização — não há gates/policies do Laravel, a lógica de acesso está nos controllers.

## Módulos principais

- **Bancas** (`BancaController`): CRUD + reabrir. Status: `cadastrada → em_avaliacao → finalizada`.
- **Avaliações** (`AvaliacaoController`): formulário de notas por barema, finalização pelo orientador, ata imprimível.
- **Baremas** (`BaremaController` + `BaremaSecaoController` + `BaremaItemController`): instrumentos de avaliação com seções e itens. Nota final = `Σ(nota × peso) / Σ(nota_max × peso) × 10`.
- **Documentos** (`TrabalhoDocumentoController`): upload de PDF, extração com `smalot/pdfparser`, análise via Ollama, envio para avaliadores.
- **Usuários** (`UsuarioController`): cadastro com envio automático de e-mail de primeiro acesso. Discente cadastrado à mão exige orientador; o importado por CSV nasce sem orientador (aba/selo "sem orientador").
- **Orientação** (`OrientacaoDiscenteController` + `OrientacaoService`): bloco na edição do discente em Usuários (não há mais tela "Orientações"). Troca de orientador com processo SEI e justificativa obrigatórios e data automática, só antes da qualificação (bloqueada com qualificação `em_avaliacao`/`finalizada` ou defesa não cancelada); a troca devolve a qualificação ainda `cadastrada`. Coorientadores entram e saem sem SEI, até `programas.max_coorientadores` vigentes.

## E-mails automáticos

| Gatilho | Destinatário | Classe |
|---------|-------------|--------|
| Novo usuário cadastrado | Usuário | `PrimeiroAcessoUsuarioMail` |
| Banca cadastrada | Avaliadores | `BancaCadastradaMail` |
| Discente envia trabalho para banca | Avaliadores | `TrabalhoEnviadoBancaMail` |
| Todos avaliadores enviaram | Orientador | `BancaProntaParaFinalizarMail` |
| Banca finalizada | Discente | `BancaFinalizadaDiscenteMail` |
| Recuperação de senha | Usuário solicitante | `RecuperacaoSenhaTemporariaMail` |
| Troca de orientador devolve qualificação homologada | Avaliadores da banca | `BancaRevisaoTrocaOrientadorMail` |

## Convenções do projeto

- Nomes em português (controllers, models, rotas, variáveis).
- Rotas nomeadas no padrão `recurso.acao` (ex.: `bancas.reabrir`, `avaliacoes.form`).
- Todas as rotas protegidas com `['auth', 'verified']` — não deixar rotas fora de middleware.
- PDFs armazenados em `storage/app/private/` — nunca expor publicamente.
- Ata da banca gerada em HTML imprimível (`avaliacoes.ata`), logos em `public/images/`.

## Comandos úteis

```bash
php artisan optimize:clear   # limpar caches (config, rotas, views)
php artisan migrate --force  # migrations em produção
php artisan tinker           # inspeção interativa
```

## Pontos de atenção

- O papel `admin` existe na tabela mas **não tem nenhuma lógica implementada** nos controllers.
- Baremas inativados não afetam avaliações existentes.
- A banca de defesa só pode ser criada após a qualificação ser finalizada com aprovação.
- O orientador é presidente da banca via `Orientacao` — não entra em `banca_membros` como avaliador.
- `orientacoes` guarda períodos (`inicio_em`/`fim_em`, `processo_sei`, `justificativa`, `registrado_por`) e nunca apaga linhas; vigente ⇔ `ativo = true`. `TrabalhoAcademico::orientacoes()` e `DocenteProgramaVinculo::orientacoes()` trazem **só as vigentes** (só elas dão acesso); o histórico completo é `orientacoesHistorico()`.
- Cada Secretaria administra um único programa (`ContextoSecretaria`, helpers `programaDaSecretaria()`/`garantirProgramaDaSecretaria()` no `Controller` base); todas as telas da Secretaria filtram por ele.
- Regras de composição de banca e prazos vêm do curso do discente (`programa_cursos`, `curso_regras_banca`, via `VerificacaoBancaService`) — não há mais regra fixa por nível nem sigla UNEB no código.
- ORCID do discente é obrigatório para cadastrar/homologar banca e enviar o trabalho; o discente informa em Meu perfil.
