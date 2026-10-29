# Dados para o Lattes das bancas — desenho

Data: 2026-10-29 · Branch: `feat/submissao-latex`

## Intenção

Quem participa de uma banca do programa precisa lançar essa participação no Currículo Lattes ("Participação em bancas de trabalhos de conclusão"). O Katu passa a mostrar, para cada banca **finalizada** de que o docente participou, os dados já organizados nos campos do formulário do Lattes, prontos para conferir e copiar. Parte dos dados vem do sistema; parte o estudante informa no cadastro do trabalho, escolhendo áreas do CNPq e setor CNAE em listas que a Secretaria mantém.

## Decisões do usuário

1. É um apoio ao preenchimento do **Lattes** — "exatamente".
2. **Todos os membros** da banca têm essa opção na listagem das bancas de que participaram. O avaliador externo não tem "Bancas que você orienta"; a lista dele é "Bancas do {sigla do programa} de que participou".
3. Os dados aparecem **assim que a banca é finalizada**.
4. Campos (rótulos do Lattes), com a origem dita pelo usuário:
   - **Natureza:** Mestrado · Doutorado · Exame de qualificação de doutorado · Exame de qualificação de mestrado · Curso de aperfeiçoamento/especialização · Graduação.
   - **Tipo:** Acadêmico · Profissional.
   - **Título**, **Ano**, **País**, **Idioma**.
   - **Homepage do trabalho:** campo novo no **cadastro do programa**; vem de lá.
   - **Nome do candidato**, **Instituição**, **Curso**.
   - **Participantes da banca:** todos, inclusive orientador e estudante.
   - **Palavras-chave.**
   - **Áreas:** o estudante indica, no cadastro do trabalho, uma ou mais **áreas do CNPq**.
   - **Setor de aplicação (Tabela CNAE):** o estudante cadastra, se houver.
   - **Outras informações:** texto livre do estudante, se houver.
   - **Título em inglês:** vem do template LaTeX ou o estudante cadastra.
   - **Other information:** igual a "Outras informações", em inglês.
   - **Visibilidade:** pública por padrão; o estudante pode marcar **privada**.
5. **Listas mantidas pela Secretaria:** uma tela já com **todas as áreas do CNPq e todos os setores CNAE** carregados, e o estudante seleciona dessas listas. A Secretaria pode **incluir, editar e desativar** itens das duas listas ("podem haver interações"). As listas são **únicas para o sistema inteiro**: o que uma Secretaria muda vale para todos os programas.
6. **Níveis:** "conforme o Lattes" — áreas do CNPq até a subárea; setor CNAE no mesmo nível da lista de setores de atividade do Lattes.

## Comportamento

### Listas de áreas CNPq e setores CNAE (Secretaria)
- Tabelas próprias, carregadas por seeder a partir de arquivos de dados versionados no repositório (`database/data/`):
  - **Áreas do CNPq:** Tabela de Áreas do Conhecimento até o nível de **subárea** (grande área › área › subárea), com o código oficial. *(Chamada minha: é o nível que o Lattes pede.)*
  - **Setores CNAE:** CNAE 2.0 no nível de **divisão** (código de 2 dígitos + descrição, ~87 itens), agrupados pela seção — conferido com a lista de setores de atividade do formulário do Lattes antes de carregar; se o Lattes usar outro nível, segue o Lattes.
  - Fontes oficiais, não digitadas de memória: Árvore do Conhecimento do CNPq (lattes.cnpq.br/web/dgp/arvore-do-conhecimento) e o serviço de dados do IBGE (servicodados.ibge.gov.br/api/v2/cnae/divisoes).
- Tela da Secretaria (menu Configurações): listar com busca e filtro por nível; **incluir**, **editar** (código, nome, item pai) e **ativar/desativar**. Item usado por algum trabalho não é apagado, só desativado.
- O estudante só vê itens **ativos**.

### Cadastro do trabalho (estudante, em Meu trabalho)
- Bloco novo **"Dados para o Lattes"**: áreas do CNPq (seleção múltipla, com busca), setor CNAE (seleção opcional), título em inglês, outras informações (PT), other information (EN), visibilidade (pública/privada, padrão pública).
- **Título em inglês:** se a submissão LaTeX extraiu o título em inglês, o campo vem preenchido com ele; o estudante pode corrigir.
- Nenhum desses campos bloqueia proposta, envio ou homologação. *(Chamada minha.)*

### Programa (Secretaria)
- Campo novo **Homepage dos trabalhos** (URL) no cadastro do programa.

### Lista de bancas de que o docente participou
- Na área do docente, nova seção **"Bancas do {sigla} de que participou"**, com as bancas **finalizadas** em que ele foi avaliador ou orientador (presidente). Para quem não orienta nenhum trabalho, "Bancas que você orienta" não aparece.
- Cada banca tem um botão **"Dados para o Lattes"** que abre o quadro com os campos abaixo, cada um com botão de copiar.

| Campo | Origem |
|---|---|
| Natureza | Defesa + nível mestrado → "Mestrado"; defesa + doutorado → "Doutorado"; qualificação + mestrado → "Exame de qualificação de mestrado"; qualificação + doutorado → "Exame de qualificação de doutorado". |
| Tipo | Modalidade do curso do discente: acadêmico → "Acadêmico", profissional → "Profissional". |
| Título | Título do trabalho. |
| Ano | Ano da data da banca. |
| País | País da instituição do programa — campo novo **País** no cadastro da instituição, junto de Estado e Município (padrão "Brasil"). |
| Idioma | Idioma da submissão LaTeX vigente: `pt` → "Português", `en` → "Inglês" (padrão "Português"). |
| Homepage do trabalho | Homepage dos trabalhos do programa. |
| Nome do candidato | Nome do discente. |
| Instituição | Nome da instituição do programa. |
| Curso | Nome do programa + curso (ex.: "Mestrado Acadêmico em …"). |
| Participantes da banca | Orientador (presidente), coorientadores, avaliadores e o discente. |
| Palavras-chave | Palavras-chave do trabalho. |
| Áreas | Áreas do CNPq escolhidas pelo estudante (caminho grande área › área › subárea). |
| Setor de aplicação | Setor CNAE escolhido, se houver. |
| Outras informações / Other information | Textos do estudante, se houver. |
| Título em inglês | Campo do trabalho. |
| Visibilidade | Pública ou privada, como o estudante marcou. |

- Campo sem valor aparece como "—" (o membro vê o que falta conferir).

## Fora do escopo
- Enviar dados ao Lattes automaticamente (não há API pública de escrita).
- Naturezas "Curso de aperfeiçoamento/especialização" e "Graduação": entram na lista, mas nenhum fluxo do Katu as gera hoje.
- Exportar em lote.

## Anti-objetivos
- Mostrar os dados de uma banca ainda não finalizada.
- Mostrar a um docente uma banca de que ele não participou.
- Listas de áreas/CNAE digitadas de memória (erro de código ou nome no Lattes).

## Chamadas minhas (o usuário pode trocar)
- Os campos do estudante não bloqueiam nenhuma etapa.
- O quadro mostra "—" para campo vazio, com botão de copiar em cada campo.
- O estudante não vê o quadro (só os membros da banca, como pedido).

## Deixado para quem implementar
- Layout do bloco do estudante e do quadro do Lattes; componente de seleção múltipla com busca.
- Ordenação e paginação da tela da Secretaria.
