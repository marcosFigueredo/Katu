# RAG de conformidade do manuscrito com as normas do programa

## Objetivo

Hoje a verificação de conformidade do manuscrito é 100% determinística (busca de termos configurável em `DocumentoChecklist`) e a análise por IA (`OllamaResumoService`) só avalia o **resumo**, sem nenhuma referência ao manual de normas do programa. Esta etapa acrescenta uma camada de análise semântica: um RAG (Retrieval-Augmented Generation) que usa o manual de normas do próprio programa como base de conhecimento para avaliar pontos do manuscrito que exigem interpretação (não apenas presença de uma palavra), citando a norma correspondente.

Esta é uma camada **consultiva**: nunca bloqueia o envio do trabalho aos avaliadores. É um complemento ao checklist existente, que continua funcionando como está.

## Fora do escopo

- Verificação de formatação tipográfica (fonte, margem, espaçamento) — o `smalot/pdfparser` extrai texto, não metadados de layout confiáveis para isso.
- Tornar as verificações configuráveis pela Secretaria (lista de checagens é fixa no código nesta primeira versão; evolui para configurável se houver necessidade real).
- Qualquer alteração no gate de homologação de banca ou no botão "Enviar à banca" — este recurso não interfere nesses fluxos.
- Reindexação automática agendada — reindexar é uma ação explícita da Secretaria (reenviar o documento).

## 1. Documentos de referência por programa

Novo módulo da Secretaria, `/programa/documentos-referencia` (mesmo padrão de `/programa/cursos`), com duas vagas de upload:

- **Normas e Procedimentos** (obrigatório para a análise rodar)
- **Template** (opcional; enriquece a base de conhecimento quando presente, mas sua ausência não impede a análise)

Cada vaga aceita um PDF (mesmas regras de `meu-trabalho.store`: `mimes:pdf`, até 30 MB), com botão de substituir. A tela mostra, por vaga: não enviado / indexando / indexado em DD/MM/AAAA / falhou (com o motivo).

### Modelo de dados (documentos e chunks)

**`programa_documentos_referencia`**

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `programa_id` | FK `programas` | |
| `tipo` | enum(`normas`,`template`) | unique junto com `programa_id` |
| `arquivo_path`, `arquivo_nome_original`, `tamanho`, `hash_sha256` | — | mesmo padrão de `trabalho_documentos` |
| `enviado_por` | FK `users` | |
| `enviado_em` | timestamp | |
| `status_indexacao` | enum(`pendente`,`processando`,`concluido`,`falhou`) | |
| `indexado_em` | timestamp nullable | |
| `erro_indexacao` | text nullable | motivo quando `falhou` |

Reenviar um documento substitui o arquivo anterior (apaga o físico) e reprocessa a indexação do zero.

**`base_conhecimento_chunks`**

| Coluna | Tipo |
| --- | --- |
| `programa_documento_referencia_id` | FK, `cascade` on delete |
| `programa_id` | denormalizado, para filtrar rápido |
| `tipo` | `normas`\|`template` |
| `pagina` | int |
| `ordem` | int (ordem do trecho dentro do documento) |
| `conteudo` | text |
| `embedding` | json (vetor de floats) |

### Indexação (assíncrona)

Ao enviar/substituir um documento, `ProgramaDocumentoReferenciaController@store` grava o arquivo, marca `status_indexacao = pendente` e despacha `IndexarBaseConhecimentoJob` (fila `database`, mesmo padrão de `SincronizarOrcidBancaJob`). O job:

1. Marca `processando`.
2. Extrai texto por página com `smalot/pdfparser` (já usado no projeto).
3. Quebra o texto em trechos de ~700 caracteres com leve sobreposição, respeitando parágrafos quando possível, mantendo o número da página de origem.
4. Para cada trecho, chama `POST {OLLAMA_URL}/api/embeddings` (modelo `nomic-embed-text`, novo em `config/ollama.php` como `embedding_model`) e grava o vetor retornado.
5. Substitui (delete + insert) os chunks anteriores daquele `programa_documento_referencia_id`.
6. Marca `concluido` com `indexado_em = now()`, ou `falhou` com `erro_indexacao` preenchido (um chunk falhar não aborta os demais; só falha o documento inteiro se a extração do PDF falhar ou se **nenhum** chunk for indexado).

Corpus pequeno (dezenas a poucas centenas de trechos por programa) — a busca por similaridade no momento da análise é feita em PHP (cosseno entre vetores), sem banco vetorial dedicado.

## 2. Análise de conformidade do manuscrito (RAG)

Dispara automaticamente quando o discente envia/reenvia o manuscrito (`TrabalhoDocumentoController@store`), **somente se** o programa do discente tiver `programa_documentos_referencia` do tipo `normas` com `status_indexacao = concluido`. Caso contrário, essa camada simplesmente não roda — sem erro, sem aviso.

### Verificações (lista fixa nesta versão)

Extraídas do sumário de `docs/NormasPPGMSB.pdf`:

1. **Seção "Aderência"** — presente e com o texto-base/conteúdo exigido pela norma.
2. **ODS do trabalho** — declarado conforme exigido.
3. **Padrão de citação (APA)** — referências seguem o padrão normativo adotado.
4. **Uso de IA** — declaração de boas práticas/limites, quando aplicável.

### Fluxo por verificação

`ConformidadeNormasService` (mesmo espírito de `OllamaResumoService`: nunca lança exceção, cada verificação grava seu próprio status):

1. Gera (ou reaproveita, cacheado em memória durante o job) o embedding da pergunta associada à verificação.
2. Busca no `base_conhecimento_chunks` do programa (`tipo = normas`, e `template` se indexado) os 5 trechos mais similares por cosseno.
3. Localiza o trecho correspondente no manuscrito, por busca de cabeçalho/palavras-chave no texto já extraído (reaproveita a extração do `DocumentoAnaliseService`); se não encontrar, marca `trecho_localizado = false` e segue mesmo assim (a ausência já é um dado relevante para o veredito).
4. Monta prompt para o Ollama (`qwen2.5:7b`, `format: json`) com os trechos da norma + o trecho do manuscrito (ou a informação de que não foi localizado), pedindo veredito estruturado: `veredito` (`conforme`\|`parcial`\|`nao_conforme`), `justificativa`, `trecho_norma_citado`.
5. Grava em `documento_conformidade_rag`.

Roda em `AnalisarConformidadeRagJob` (fila), despachado diretamente por `TrabalhoDocumentoController@store` logo após gravar o `TrabalhoDocumento` — diferente da avaliação do resumo (que hoje é disparada por AJAX do navegador após o redirect, sem depender de fila). Aqui já usamos fila porque são 4 chamadas ao Ollama por envio, cada uma independente (uma falhar não impede as outras), e o projeto já roda `queue:listen` em desenvolvimento (`composer dev`).

### Modelo de dados (resultado da análise)

**`documento_conformidade_rag`**

| Coluna | Tipo |
| --- | --- |
| `documento_analise_id` | FK `documento_analises` |
| `verificacao` | string (`aderencia`\|`ods`\|`citacao_apa`\|`uso_ia`) |
| `status` | `processando`\|`concluida`\|`falhou` |
| `veredito` | `conforme`\|`parcial`\|`nao_conforme`, nullable até concluir |
| `justificativa` | text nullable |
| `trecho_norma_citado` | text nullable |
| `erro` | text nullable |

## 3. UI

- **Secretaria**: nova tela `/programa/documentos-referencia`, com link ao lado de "Cursos" no menu de configurações do programa.
- **Discente**: em `meu-trabalho/analise.blade.php`, nova seção "Conformidade com as Normas (IA)" abaixo da avaliação do resumo — um card por verificação com badge de veredito + justificativa + trecho da norma citado. Enquanto `processando`, mostra aviso para recarregar a página (mesmo padrão já usado para o checklist estrutural). Se o programa não tem normas indexadas, a seção não aparece.

## 4. Tratamento de erros

- Upload de documento de referência: mesmas validações de PDF já usadas em `meu-trabalho.store`.
- `IndexarBaseConhecimentoJob`: chunk individual falhando não aborta os demais; falha total (extração do PDF ou 100% dos embeddings falhando) marca o documento como `falhou` com mensagem, e a Secretaria pode reenviar.
- `AnalisarConformidadeRagJob`/`ConformidadeNormasService`: cada verificação é independente; timeout do Ollama usa `config('ollama.timeout')` já existente.

## 5. Testes

- Upload/substituição de documentos de referência pela Secretaria: isolamento por programa, exige PDF, dispara o job.
- `IndexarBaseConhecimentoJob`/serviço de chunking: com `Http::fake()` simulando `/api/embeddings`, sem depender de Ollama real.
- Sem normas indexadas para o programa → a seção de conformidade não é exibida e o job de análise não é despachado.
- `ConformidadeNormasService`/`AnalisarConformidadeRagJob`: com `Http::fake()` simulando vereditos do Ollama, cobrindo sucesso, uma verificação falhando isoladamente, e o caso "trecho não localizado no manuscrito".
