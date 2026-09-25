# Fluxo da Secretaria

## Objetivo

Implementar a primeira etapa do ciclo acadêmico do PPGMSB: a Secretaria administra a base acadêmica, convida e valida membros externos, configura os critérios da CPA e homologa ou devolve bancas submetidas.

## Limites desta etapa

- A Secretaria continua sendo a única categoria autorizada a administrar este fluxo.
- A proposta da banca pelo discente será implementada na etapa seguinte; esta etapa prepara os estados, evidências e ações de homologação.
- O barema continua sendo o instrumento de julgamento da banca. Os critérios da CPA são pré-requisitos/evidências, não notas.
- Alterações locais já existentes no repositório devem ser preservadas.

## Regras de negócio

### Base acadêmica

- Cada discente vinculado a um programa pode ter um orientador e zero ou mais coorientadores.
- O vínculo da orientação deve referenciar diretamente o vínculo do discente no programa, além de continuar referenciando o trabalho existente para preservar compatibilidade com o código atual.
- Apenas docentes ativos, vinculados ao mesmo programa e com `pode_orientar = true` podem ser orientadores/coorientadores.

### Membro externo

- A Secretaria cria um convite com nome, e-mail, programa e prazo de validade.
- O link contém apenas um token não reversível armazenado no banco como hash.
- O membro externo completa os dados e envia uma imagem/PDF do diploma de doutorado.
- O cadastro fica `aguardando_validacao` até a decisão da Secretaria.
- Ao aprovar, o sistema cria/ativa o vínculo externo ao programa e envia as credenciais de primeiro acesso.
- Ao devolver, a Secretaria deve registrar uma observação; o convite/cadastro permanece editável pelo mesmo link enquanto válido.

### Critérios da CPA

- Cada critério pertence a um programa e a uma etapa (`qualificacao` ou `defesa`).
- O critério possui código, título, descrição, tipo de evidência, obrigatoriedade, nível opcional, ordem, versão e vigência (`ativo`).
- Tipos de evidência suportados nesta etapa: `arquivo`, `texto`, `link`, `doi`, `numero` e `declaracao`.
- Critérios inativados não desaparecem de históricos futuros; apenas deixam de ser aplicados a novas submissões.

### Homologação

- A banca mantém seu status de avaliação atual para não quebrar o fluxo existente.
- A homologação é registrada separadamente com os estados `homologada`, `aguardando`, `devolvida` e `rejeitada`.
- Homologar registra responsável/data, libera o envio do trabalho e permite o início da avaliação.
- Devolver exige justificativa, registra responsável/data e bloqueia o envio do trabalho.
- A tela da Secretaria deve exibir uma fila de bancas aguardando homologação ou devolvidas.
- A banca de defesa só pode avançar quando a qualificação estiver finalizada com resultado aprovado ou aprovado com modificações.

## Auditoria mínima

Toda decisão de homologação/devolução registra usuário, data e observação. Convites registram emissor, expiração, uso e estado. O diploma é armazenado em `storage/app/private`.

## Critérios de aceite

1. Uma Secretaria consegue enviar e reenviar convite de membro externo.
2. Um membro externo consegue abrir o link, preencher os dados e anexar o diploma sem estar autenticado.
3. A Secretaria consegue aprovar ou devolver o cadastro externo e o sistema cria o vínculo externo somente na aprovação.
4. A Secretaria consegue cadastrar, editar, ativar/inativar e ordenar critérios da CPA por programa e etapa.
5. O vínculo de orientação salvo contém o vínculo do discente no programa.
6. Uma banca aguardando homologação pode ser homologada ou devolvida com justificativa, com bloqueio/liberação do envio refletindo a decisão.
7. Acesso de discente/docente às telas e ações exclusivas da Secretaria retorna 403.
8. Migrações, testes de feature e build frontend executam sem introduzir regressões nos testes atuais.
