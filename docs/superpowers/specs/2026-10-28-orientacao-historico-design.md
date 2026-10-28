# Orientação no cadastro do discente, troca auditável e histórico — desenho

Data: 2026-10-28 · Branch de origem: `feat/submissao-latex` · Autor do pedido: coordenação do PPGMSB

## 1. Intenção

A Secretaria é quem registra quem orienta cada discente. Hoje isso fica numa tela à parte ("Orientações", no menu Bancas), o orientador é opcional no cadastro, e salvar a tela **apaga** as orientações anteriores e recria tudo — não sobra histórico de quem orientou antes.

O que o programa precisa:

- o orientador nasce junto com o discente (cadastro manual);
- a troca de orientador é um ato formal, validado num processo SEI, e precisa deixar **rastro para auditoria futura**: número do processo SEI e justificativa;
- o período de cada orientador fica guardado (quem orientou, de quando a quando);
- coorientadores entram e saem ao longo do curso, como ato burocrático simples;
- o orientador que sai **perde o acesso** ao discente.

## 2. Objetivos

1. **Tirar "Orientações" do menu Bancas.** A gestão de orientação passa para a tela do discente, em Usuários (cadastro e edição do discente).
2. **Orientador obrigatório no cadastro manual do discente** pela Secretaria (`UsuarioController::store`, categoria discente). Sem orientador, o cadastro não salva.
3. **Importação por CSV continua sem orientador** (`ImportacaoDiscentesService`/`CadastroDiscenteService`): o discente entra sem orientador e a Secretaria inclui depois. A lista de Usuários mostra um aviso "sem orientador" para esses discentes, e um filtro para achá-los.
4. **Troca de orientador**: ação própria da Secretaria, com três campos obrigatórios — novo orientador, número do processo SEI, justificativa (texto). **A data da troca é automática** (o dia em que a Secretaria registra) e fica gravada no registro. A troca encerra o período do orientador atual (data de fim = data da troca) e abre o do novo (data de início = data da troca). Registra quem (usuário da Secretaria) fez a troca.
4a. **Troca só antes da qualificação** (regra do usuário): a troca é recusada se o trabalho tem banca de qualificação que já ocorreu ou está ocorrendo — `tipo = qualificacao` com `status` `em_avaliacao` ou `finalizada` (e, por consequência, qualquer banca de defesa). A tela não oferece o botão nesse caso e explica o motivo. Coorientadores continuam podendo entrar e sair a qualquer momento.
5. **Inclusão do orientador num discente sem orientador** (vindo do CSV): é a primeira atribuição, não uma troca — não exige processo SEI nem justificativa.
6. **Coorientadores**: opcionais, incluídos e retirados a qualquer momento pela Secretaria, **sem** processo SEI nem justificativa. Ainda assim cada período guarda data de entrada e de saída (sem custo para a Secretaria; mantém o histórico). Limite: o `max_coorientadores` do programa, como hoje (decisão do usuário: manter a configuração por programa).
7. **Histórico visível**: a tela do discente mostra a linha do tempo da orientação — orientadores e coorientadores, com início, fim e, nas trocas de orientador, processo SEI e justificativa.
8. **Acesso**: só a orientação **vigente** dá ao docente acesso ao discente (trabalho, documentos, submissões, bancas, notificações). O orientador anterior perde o acesso a partir da troca.

## 3. Não objetivos

- Não mudar prazos: a troca não altera prazo nenhum (prazos seguem contando da data de ingresso, regras do curso).
- Não criar fluxo de pedido de troca dentro do sistema (o pedido e a validação acontecem no SEI; o sistema só registra o resultado).
- Não exigir SEI/justificativa para coorientador.
- Não mudar a regra de quem pode orientar (`docente_programa_vinculos.pode_orientar`, ativo, do programa do discente) nem de quem pode coorientar (qualquer docente ativo do programa).
- Não mexer no papel `admin`.

## 4. Anti-objetivos (seria falha mesmo funcionando)

- Perder o registro de quem orientou num período (apagar linhas de `orientacoes`).
- Algum lugar do sistema continuar tratando o orientador antigo como orientador atual depois da troca (e-mail para a pessoa errada, presidente errado, acesso indevido).
- Trocar orientador sem processo SEI ou sem justificativa.
- Permitir troca de orientador depois da qualificação.

## 5. Estado atual (levantado no código)

- `orientacoes`: `trabalho_academico_id`, `discente_programa_vinculo_id`, `docente_programa_vinculo_id`, `tipo` (`orientador|coorientador`), `ordem`, `ativo`, timestamps. Índice único `orientacoes_trab_doc_unique (trabalho_academico_id, docente_programa_vinculo_id)` — **impede** que o mesmo docente volte a orientar o mesmo trabalho depois de sair, ou que um coorientador vire orientador mantendo o registro antigo.
- `OrientacaoController@update` faz `$trabalho->orientacoes()->delete()` e recria tudo.
- Várias consultas pegam "o orientador" com `firstWhere('tipo','orientador')` **sem** filtrar `ativo` (ex.: `BancaController` 128 e 401, `OrientacaoAvaliacaoController` 50 e 80, `IeoService` 27, `RelatorioController` 250, `FolhaAprovacaoLatexService` 69, `ConformidadeCadastroService` via `nomesOrientacao`, views `ata`, `bancas/form`, `homologacao*`, `meu-trabalho`, `dashboards/discente`, e-mail `banca-cadastrada`). Outras já filtram `ativo` (`AvaliacaoController` 293, `BibliometriaService` 29, `VerificacaoReferenciasService` 337). Hoje isso funciona por acaso: todas as linhas estão ativas.
- O `TrabalhoAcademico` é criado junto com o vínculo do discente, tanto no cadastro manual (`UsuarioController` 384-396) quanto na importação (`CadastroDiscenteService`).
- `programas.max_coorientadores` (0–5, padrão 1) já existe e é validado.

## 6. Como fica (decidido)

### 6.1 Dados

Em `orientacoes`, acrescentar:

- `inicio_em` (date, obrigatório);
- `fim_em` (date, nulo enquanto vigente);
- `processo_sei` (string, nulo) — obrigatório só na linha do orientador que **entra por troca**;
- `justificativa` (text, nulo) — idem;
- `registrado_por` (FK `users`, nulo) — quem da Secretaria registrou.

Vigente = `fim_em IS NULL` (e `ativo = true`, mantido coerente: ao encerrar, `ativo = false`). Trocar o índice único por um que só impeça **duas linhas vigentes** do mesmo docente no mesmo trabalho e **dois orientadores vigentes** no mesmo trabalho — no MySQL isso não sai de índice parcial; a regra fica no serviço (com transação e `lockForUpdate`), e o índice único antigo é removido (substituído por índice simples). Nomes de índice ≤ 64 caracteres.

### 6.2 Um ponto único para "orientação vigente"

No `TrabalhoAcademico`: relação/escopo `orientacoesVigentes()`, e atalhos `orientadorVigente()` e `coorientadoresVigentes()`. **Todos** os lugares listados na seção 5 que querem "o orientador de agora" passam a usar esse ponto. Os que tratam de um fato passado usam o orientador **vigente na data do fato** (ver 6.5).

### 6.3 Serviço

Um serviço (ex.: `OrientacaoService`) concentra as operações, cada uma em transação:

- `definirOrientadorInicial(trabalho, vinculoDocente, usuarioSecretaria)` — só quando não há orientador vigente;
- `trocarOrientador(trabalho, novoVinculo, processoSei, justificativa, usuarioSecretaria)` — data = hoje, automática; recusa depois da qualificação (2.4a); encerra o vigente, abre o novo; recusa se o novo for o mesmo que o atual; se o novo for coorientador vigente, encerra a coorientação dele na mesma data; devolve a banca de qualificação aberta (6.5);
- `incluirCoorientador(...)` / `retirarCoorientador(...)` — respeitando `max_coorientadores` contra os **vigentes**; o orientador vigente não pode ser coorientador.

### 6.4 Telas da Secretaria

- Menu: sai "Orientações" do grupo Bancas (e `orientacoes.*` sai de `$bancasAberto`). As rotas `orientacoes.*` antigas deixam de existir ou redirecionam para a tela do discente.
- Cadastro do discente (`usuarios.create`, categoria discente): campo **Orientador** obrigatório (docentes do programa com `pode_orientar`, ativos). Coorientadores não aparecem no cadastro.
- Edição/visualização do discente em Usuários: bloco **Orientação** com o orientador vigente, coorientadores vigentes, ações "Incluir orientador" (quando não há), "Trocar orientador" (formulário com novo orientador, processo SEI, justificativa), "Incluir coorientador", "Retirar coorientador", e a **linha do tempo** com todos os períodos.
- Lista de Usuários: selo "sem orientador" nos discentes sem orientador vigente; filtro para listá-los.
- Tudo filtrado pelo programa da Secretaria (`garantirProgramaDaSecretaria`).

### 6.5 Acesso e efeitos da troca

- Acesso do docente ao discente/trabalho (dashboards, meu-trabalho do orientador, documentos, submissões, notificações, finalização da banca) passa a depender da orientação **vigente**.
- O orientador anterior perde **todo** o acesso ao discente a partir da data da troca. Como a troca só ocorre antes da qualificação, ele nunca presidiu banca desse discente — não há ata nem avaliação dele a preservar.
- Consequência da regra 4a: toda banca (qualificação e defesa) acontece com o orientador vigente. Por isso relatórios e indicadores (produção do orientador nas referências, avaliação da orientação, IEO, relatórios de orientação) usam simplesmente o orientador **vigente**; não é preciso apurar "orientador na data do fato" nem guardar cópia do orientador na banca. O histórico de orientadores aparece na tela do discente (linha do tempo) e serve à auditoria.
- **Banca de qualificação aberta que ainda não ocorreu** (homologação `proposta`, `aguardando`, `devolvida` ou `homologada`, com `status` ainda `cadastrada`) no momento da troca — decisão do controlador: a troca é permitida e, na mesma transação, essa banca volta para `devolvida` com observação automática "Orientador trocado em <data> (SEI <número>) — revisar a composição e reenviar". O discente revê e reenvia; a Secretaria homologa de novo, já com o novo presidente. Os avaliadores convidados de uma banca que estava `homologada` são avisados por e-mail de que a banca voltou para revisão.
- Discente sem orientador vigente não consegue propor banca nem enviar trabalho à homologação (mensagem clara: "procure a Secretaria para registrar seu orientador").

## 7. Restrições

- **Dados atuais são de teste e descartáveis** (regra do usuário até o app ser finalizado): nada de backfill nem saneamento de orientações antigas; a mudança de esquema pode assumir `migrate:fresh --seed`. Seeders e factories acompanham o esquema novo (orientações com `inicio_em`).
- Limite de coorientadores (escolha do controlador): vale só para novas inclusões; nada é retirado automaticamente se o programa reduzir o limite.
- Ao trocar o orientador, o anterior **não** vira coorientador automaticamente; se for o caso, a Secretaria o inclui depois como coorientador (escolha do controlador).

- Convenções do projeto (CLAUDE.md): nomes em português, rotas `recurso.acao` em `['auth','verified']`, autorização por `users.categoria` no controller, telas da Secretaria filtradas pelo programa dela.
- Migrations rodam no MySQL local depois de prontas (pedido do usuário) e têm `down()` funcional.
- Testes de feature para: cadastro sem orientador recusado; importação sem orientador aceita e com selo; troca sem SEI/justificativa recusada; troca grava a data automática e preserva o período anterior; troca recusada com qualificação em avaliação ou finalizada; troca com qualificação ainda não ocorrida devolve a banca com a observação; orientador anterior perde acesso; limite de coorientadores contra vigentes; consultas de "orientador" retornam o vigente depois de uma troca.
- Há trabalho paralelo não commitado nos arquivos de Homologação (`HomologacaoBancaController`, `HomologacaoBancaService`, `homologacao*.blade.php`, `bancas/index.blade.php`, `routes/web.php`). Coordenar antes de editar esses arquivos.

## 8. Deixado ao construtor

- Nome exato do serviço, das rotas novas e dos componentes Blade.
- Se as rotas `orientacoes.*` são removidas ou redirecionam.
- Formato visual da linha do tempo.
- Ordem de exibição dos coorientadores (o campo `ordem` pode continuar existindo).
