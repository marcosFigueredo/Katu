# Programa completo, cursos e importação de discentes

## Objetivo

Completar as tarefas de base da Secretaria:

1. Cadastrar os dados completos do programa.
2. Organizar o programa em **cursos** (nível × modalidade), cada um com prazos e regras de composição de banca próprios.
3. Importar discentes em lote por CSV.
4. Garantir que nenhum ato de qualificação ou defesa avance sem o ORCID do discente, que o próprio discente informa no seu cadastro.

## Fora do escopo

- Vincular baremas e critérios CPA a um curso: depende do estudo sobre critérios numéricos/categóricos e análises quantitativas/qualitativas. Baremas e critérios continuam filtrando por `nivel`.
- Suplentes de banca (`banca_membros.papel` só aceita `avaliador`); tratados na etapa de proposta de banca pelo discente.
- Upload de regimento e resoluções.
- Importação em lote de docentes.

## 0. Logotipos da instituição e do programa

Hoje `instituicoes.logo_path` é sempre gravado como `images/logoUneb.png` e a ata usa imagens fixas de `public/images/`.

- **Instituição**: o formulário ganha upload de logotipo, **obrigatório** no primeiro cadastro e opcional nas edições (substitui o atual). Formatos PNG, JPG ou WebP (SVG não é aceito, por risco de script embutido); até 2 MB; o sistema pré-visualiza antes de salvar.
- **Programa**: nova coluna `programas.logo_path`, upload opcional com as mesmas regras.
- **Armazenamento**: `storage/app/private/logos/`, com nome gerado pelo sistema; o arquivo anterior é apagado ao substituir. Servido por rota autenticada (`instituicao.logo`, `programas.logo`), sem depender de `storage:link`.
- **Uso**: a ata (`avaliacoes.ata`) e a tela da instituição passam a exibir os logotipos cadastrados; sem logotipo do programa, a ata mostra só o da instituição. Os logos de marca do sistema (login, sidebar) não mudam.
- A instituição continua única no sistema (a instituição sede); `programas.instituicao_id` aponta para ela.

## 1. Dados completos do programa

Novas colunas em `programas`:

| Coluna | Tipo | Regra |
|---|---|---|
| `instituicao_id` | FK `instituicoes`, nullable | instituição sede do programa |
| `conceito_capes` | tinyint, nullable | 1 a 7 |
| `ano_inicio` | smallint, nullable | 4 dígitos, até o ano corrente |
| `coordenador_id` | FK `users`, nullable | docente ativo com vínculo permanente ativo no programa |
| `vice_coordenador_id` | FK `users`, nullable | mesma regra; diferente do coordenador |

`oferta_mestrado` e `oferta_doutorado` permanecem na tabela para compatibilidade, mas deixam de ser editados no formulário: são recalculados a partir dos cursos ativos sempre que um curso é criado, alterado ou inativado.

Todos os dados são cadastrados pela Secretaria (`categoria = sec`); demais papéis recebem 403.

## 2. Cursos do programa

### Tabela `programa_cursos`

- `programa_id` (FK, cascade)
- `nivel`: `mestrado` | `doutorado`
- `modalidade`: `academico` | `profissional`
- `prazo_qualificacao_meses` (padrão: 18 mestrado, 30 doutorado)
- `prazo_defesa_meses` (padrão: 24 mestrado, 48 doutorado)
- `ativo` (boolean)
- único: (`programa_id`, `nivel`, `modalidade`)

Nome exibido derivado: "Mestrado Acadêmico", "Doutorado Profissional" etc. Identificador textual usado no CSV: `{nivel}_{modalidade}` (ex.: `mestrado_academico`).

### Tabela `curso_regras_banca`

Uma linha por curso e etapa:

- `programa_curso_id` (FK, cascade)
- `etapa`: `qualificacao` | `defesa`
- `min_avaliadores` (≥ 1)
- `min_externos_programa` (≥ 0): membro cujo vínculo no programa tem `tipo_vinculo = externo`
- `min_externos_instituicao` (≥ 0): membro cuja `instituicao_sigla` (perfil docente) difere, sem distinção de maiúsculas/espaços, da sigla da instituição do programa
- único: (`programa_curso_id`, `etapa`)
- os mínimos de externos não podem exceder `min_avaliadores`

Ao criar um curso, as duas regras (qualificação e defesa) são criadas com `min_avaliadores = 2` e externos = 0, para a Secretaria ajustar.

### Vínculo do discente

`discente_programa_vinculos` ganha `programa_curso_id` (FK nullable). A migration:

1. cria, para cada programa, os cursos acadêmicos correspondentes aos níveis já usados pelos vínculos existentes e às flags `oferta_mestrado`/`oferta_doutorado`;
2. preenche `programa_curso_id` a partir de `nivel` + modalidade `academico`.

`nivel` continua na tabela e é sincronizado com o nível do curso sempre que o vínculo é salvo (o código de trabalhos acadêmicos, bancas e baremas o utiliza).

Cadastro individual de discente passa a pedir o **curso** em vez do nível.

### Indicadores A13/A14

`AutoavaliacaoIndicadorService` passa a comparar o tempo de cada discente com os prazos do **seu curso** (fallback 18/24 quando o vínculo não tem curso). Os rótulos deixam de citar "18 meses"/"24 meses" e passam a "dentro do prazo do curso".

## 3. Regras bloqueantes na homologação

`HomologacaoBancaService::homologar` recusa a homologação (a Secretaria só pode devolver) quando:

1. **ORCID ausente**: o discente do trabalho não tem `orcid_url` no perfil.
2. **Composição mínima**: a banca não atende a regra do curso do discente para a etapa (`banca.tipo`). Se o curso não tiver regra, não bloqueia por composição.

A tela de homologação exibe um quadro de verificação com cada regra (exigido × encontrado, ✔/✖) e a pendência de ORCID, e desabilita o botão "Homologar" enquanto houver falha. O serviço valida novamente no servidor (não confia na tela).

## 4. ORCID obrigatório para atos de banca

- **Perfil do discente**: a tela "Meu perfil" do discente passa a permitir editar `orcid_url` e `lattes_url`, com as mesmas regras de formato do cadastro da Secretaria.
- **Aviso**: enquanto o discente não tiver ORCID, o dashboard exibe um alerta com link para o perfil.
- **Bloqueio** — sem ORCID do discente, o sistema recusa:
  - cadastrar banca (qualificação ou defesa) para o trabalho;
  - homologar a banca (item 3);
  - enviar o trabalho para a banca.
  A mensagem indica que o discente deve informar o ORCID no seu cadastro.

## 5. Importação de discentes (CSV)

Tela **Usuários → Importar discentes**, exclusiva da Secretaria, no programa atual.

### Formato

A tela mostra, em texto, as colunas esperadas e um exemplo de linha (não há arquivo-modelo para baixar).

| Coluna | Obrigatória | Regra |
|---|---|---|
| `nome` | sim | até 255 caracteres |
| `email` | sim | e-mail válido |
| `matricula` | sim | até 255; única no programa |
| `curso` | sim | identificador de curso **ativo** do programa (ex.: `mestrado_academico`) |
| `data_ingresso` | sim | `dd/mm/aaaa`, não futura |
| `lattes` | sim | `http(s)://lattes.cnpq.br/<dígitos>` |
| `linha_pesquisa` | não | nome de linha de pesquisa ativa do programa (comparação sem acento/maiúsculas) |
| `telefone` | não | até 30 caracteres |

ORCID **não** é importado; é informado pelo discente.

Leitura: primeira linha é cabeçalho (nomes sem distinção de maiúsculas/acentos); separador `;` ou `,` detectado pelo cabeçalho; remove BOM UTF-8; aceita arquivos Latin-1 exportados pelo Excel (convertidos para UTF-8); ignora linhas vazias. Limites: 2 MB e 1.000 linhas de dados.

### Fluxo

1. **Envio** do arquivo → o sistema valida todas as linhas e mostra a **prévia** com contadores e uma tabela por linha:
   - **válida** — será importada;
   - **com erro** — motivo(s) exibido(s) na própria linha; não será importada;
   - **já existente** — e-mail já cadastrado ou e-mail repetido no próprio arquivo (a partir da 2ª ocorrência); ignorada, nada é alterado.
2. A prévia é guardada em cache por 30 minutos, identificada por token vinculado ao usuário da Secretaria.
3. **Confirmar importação** → em uma transação, para cada linha válida: cria `User` (`categoria = discente`, `primeiro_acesso = true`, senha temporária), `DiscenteProfile` (Lattes, telefone) e `DiscenteProgramaVinculo` (curso, nível sincronizado, matrícula, data de ingresso, linha). As linhas são revalidadas na confirmação (ex.: e-mail cadastrado nesse intervalo vira "já existente").
4. Após o commit, um e-mail `PrimeiroAcessoUsuarioMail` por discente é enfileirado.
5. Resultado: resumo (importados / com erro / já existentes) na tela, com a lista das linhas não importadas e seus motivos para a Secretaria corrigir o arquivo e reenviar.

Prévia expirada ou de outro usuário → mensagem pedindo novo envio.

## Autorização

Todas as rotas novas ficam sob `['auth', 'verified']` e verificam `categoria === 'sec'` no controller; a edição de ORCID/Lattes no perfil é do próprio discente.

## Critérios de aceite

0. A instituição não pode ser cadastrada sem logotipo; o logotipo (e o do programa, se houver) aparece na ata; arquivos inválidos (tipo/tamanho) são recusados.
1. A Secretaria edita instituição, conceito CAPES, ano de início, coordenador e vice; coordenador ≠ vice e ambos permanentes no programa.
2. A Secretaria cria, edita e inativa cursos; não é possível duplicar nível+modalidade no programa; `oferta_*` refletem os cursos ativos.
3. Cada curso tem regras de banca para qualificação e defesa editáveis, com externos ≤ avaliadores.
4. Vínculos existentes recebem curso na migration; novos discentes são cadastrados por curso.
5. A13/A14 usam os prazos do curso.
6. Banca que não atende a composição mínima ou cujo discente não tem ORCID não pode ser homologada; o quadro de verificação mostra o motivo.
7. Sem ORCID do discente, cadastrar banca e enviar trabalho para a banca são recusados.
8. O discente edita ORCID e Lattes no próprio perfil.
9. O CSV com `;` ou `,`, com BOM ou Latin-1, gera prévia correta; linhas inválidas e já existentes não são importadas; Lattes é obrigatório.
10. Confirmar a importação cria usuário, perfil e vínculo e enfileira um e-mail por discente importado.
11. Discente e docente recebem 403 nas telas de programa, cursos e importação.
12. Suíte de testes e `npm run build` passam sem regressões.
