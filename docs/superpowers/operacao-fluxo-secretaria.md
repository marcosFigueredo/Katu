# Operação do fluxo da Secretaria

## Primeira configuração

1. Configure `.env` com MySQL, URL pública e e-mail.
2. Gere a chave da aplicação: `php artisan key:generate`.
3. Crie o banco `defesa_ppgmsb` no MySQL.
4. Execute `php artisan migrate --force`.
5. Limpe caches: `php artisan optimize:clear`.
6. Gere os assets: `npm install` e `npm run build`.

Os diplomas e documentos ficam no disco local privado (`storage/app/private`) e não precisam de `storage:link`. O link simbólico é necessário apenas se algum recurso público passar a usar `storage/app/public`.

## Desenvolvimento local

Em terminais separados:

```powershell
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Para e-mail sem envio externo, mantenha `MAIL_MAILER=log` e consulte `storage/logs/laravel.log`. Para produção, configure SMTP e use Redis ou outro worker persistente para a fila.

## Rotina da Secretaria

Cada usuário Secretaria administra **um único programa**, ligado a uma instituição. Todas as telas da Secretaria mostram apenas os dados desse programa.

1. No primeiro acesso, cadastre a **instituição** (nome, sigla e logotipo obrigatórios) e depois o **programa** (instituição, conceito CAPES, ano de início, logotipo opcional).
2. Em **Configurações → Cursos**, cadastre os cursos do programa (nível × modalidade: mestrado/doutorado, acadêmico/profissional) e ajuste prazos e regras de composição de banca (mín./máx. avaliadores, mín. internos, mín. externos à instituição) para qualificação e defesa.
3. Cadastre áreas de concentração, linhas de pesquisa e docentes; depois defina coordenador(a) e vice na tela do programa.
4. Cadastre discentes individualmente (curso, Lattes e **orientador** obrigatórios) ou em lote em **Usuários → Importar discentes** (CSV com prévia; e-mails de primeiro acesso enviados pela fila). Os importados chegam sem orientador e aparecem na aba **Sem orientador** de Usuários.
5. Em **Usuários → editar discente**, bloco **Orientação**: registre o orientador de quem ainda não tem; inclua ou retire coorientadores (até o máximo do programa); para **trocar o orientador**, informe o novo orientador, o número do processo SEI e a justificativa — a data é a de hoje, automática. A troca só é aceita antes da qualificação; se houver qualificação ainda não iniciada, ela volta como devolvida para o discente revisar a composição (avaliadores de banca já homologada recebem aviso). Todo período fica na linha do tempo do bloco.
6. Acesse **Membros externos**, envie o convite e acompanhe o cadastro; abra o diploma, devolva com observação ou aprove.
7. Acesse **Critérios da CPA** e mantenha uma versão ativa para qualificação e defesa.
   Em **Configurações → Áreas CNPq e CNAE**, confira as listas usadas pelos estudantes nos dados para o Lattes (já carregadas das tabelas oficiais; inclua, edite ou desative itens — vale para todos os programas). No cadastro do **programa**, informe a **Homepage dos trabalhos**; no da **instituição**, País, Estado e Município.
   Os baremas são concebidos pela **CPA** e lançados pela Secretaria: ao incluir cada pergunta, escolha a **dimensão da qualidade** que a CPA definiu (o barema só pode ser ativado com todas as perguntas ativas vinculadas a uma dimensão).
   Em **Baremas**, mantenha ativo um barema de cada tipo: Qualificação, Qualificação Confidencial, Defesa, Defesa Confidencial e **Autoavaliação do orientador** — sem eles a banca não é homologada.
8. Cadastre ou homologue bancas: o sistema confere a regra do curso e o **ORCID do discente**; sem eles a banca não é salva/homologada. O discente informa o ORCID em **Meu perfil**.
9. Somente bancas homologadas ficam liberadas para envio do trabalho e avaliação (o envio também exige ORCID).
10. Antes de o pedido chegar à fila, o discente já marcou as declarações do envio do projeto (originalidade, template, IA, plágio), preencheu os **Dados da pesquisa** (código, dados, CAAE/parecer, protocolo de ética animal) lançou as **atividades formativas** exigidas e, na defesa, respondeu o **espelho da avaliação**, o **impacto**, a **produção associada** (com o mínimo de publicações do curso) e as **Informações adicionais** — sem eles o pedido não é enviado. A Secretaria não vê as notas do espelho (privadas à CPA). Na tela de homologação, a Secretaria confere as atividades (e registra as que ela informa, como a proficiência) e, na defesa, marca as publicações; enquanto houver pendência, não homologa.
11. A lista de atividades formativas fica em Configurações → **Atividades formativas**; o mínimo de publicações para a defesa, em cada curso (Configurações → Cursos).

## Rotas de operação

- `membros-externos.index`: fila de convites e validações.
- `membros-externos.registro`: formulário público recebido pelo membro externo.
- `criterios-cpa.index`: configuração dos requisitos CPA.
- `bancas.homologacao`: fila de homologação e devolução.
- `instituicoes.create` / `instituicao.edit`: instituição do programa (com logotipo).
- `programas.create` / `programas.edit`: programa da Secretaria.
- `programa-cursos.index`: cursos, prazos e regras de banca.
- `usuarios.importar`: importação de discentes por CSV.

Com a fila em `database`, mantenha `php artisan queue:listen` (ou um worker) rodando para que os e-mails da importação sejam enviados.

## Usuários de teste

Somente para desenvolvimento e homologação — **não rode em produção**. O seeder usa o programa `PPGMSB` já cadastrado (sem alterar seus dados, áreas ou linhas), cria o curso Mestrado Acadêmico se ele não existir e um segundo programa (`PPGTI`, na UFBA) para testar o isolamento entre Secretarias. Pode ser executado várias vezes sem duplicar registros.

```powershell
php artisan db:seed --class=CenarioTesteSeeder
```

Senha de todos: **`uneb@123`** (sem troca de senha no primeiro acesso).

### Secretaria

| E-mail | Para testar |
| --- | --- |
| `secretaria@ppgmsb.test` | Secretaria principal do PPGMSB |
| `secretaria2@ppgmsb.test` | Segunda Secretaria do mesmo programa |
| `secretaria.outro@ppgmsb.test` | Secretaria de outro programa (PPGTI/UFBA) — não deve ver nada do PPGMSB |

### Docentes do PPGMSB

| E-mail | Perfil |
| --- | --- |
| `permanente1@ppgmsb.test` | Beatriz Carvalho Mendes — permanente, UNEB — orienta e avalia |
| `permanente2@ppgmsb.test` | Rafael Teixeira Nunes — permanente, UNEB — orienta e avalia |
| `colaborador@ppgmsb.test` | Luciana Prado Freitas — colaboradora, UNEB — orienta e avalia |
| `externo.ufba@ppgmsb.test` | Eduardo Moura Bastos — externo, UFBA — só avalia |
| `externo.usp@ppgmsb.test` | Externa, USP — só avalia |

### Discentes (Mestrado Acadêmico)

| E-mail | Situação |
| --- | --- |
| `discente.completo@ppgmsb.test` | Juliana Ribeiro Santos — com ORCID; orientadora Beatriz Carvalho Mendes (`permanente1`) e coorientador Rafael Teixeira Nunes (`permanente2`) — banca pode ser cadastrada. Projeto LaTeX fictício pronto em `dissertacao-teste.zip`; dados da pesquisa, impacto, atividades (publicação e extensão lançadas), produção (1 artigo, 1 extensão, 1 popularização) e Informações adicionais já preenchidos (o espelho da defesa fica para responder); vinculada ao projeto ativo da Beatriz com objetivo informado; bolsa CAPES |
| `discente.sem.orcid@ppgmsb.test` | Com orientador e **sem ORCID** — cadastro de banca deve ser recusado até ele informar o ORCID em Meu perfil |
| `discente.sem.orientador@ppgmsb.test` | Com ORCID e sem orientador — selo "sem orientador" e registro do orientador na edição |
| `discente.com.troca@ppgmsb.test` | Orientadora trocada há 2 meses (SEI fictício) — linha do tempo da orientação |
| `discente.outro@ppgmsb.test` | Aluno do programa PPGTI — não deve aparecer para a Secretaria do PPGMSB |

### Roteiro sugerido

- Projetos e bolsa: entre como `permanente1@ppgmsb.test` → **Meu projeto de pesquisa** (um ativo e um concluído) e cadastre um novo — os orientandos passam a ele. Como `discente.com.troca@ppgmsb.test`, em **Meu trabalho → Projeto de pesquisa**, diga se mantém o objetivo antigo; `discente.sem.orcid@ppgmsb.test` tem o objetivo pendente (selo "Pendente" em Meu trabalho; sem ele o pedido à homologação é recusado). Como Secretaria, em Usuários → edição da Juliana, veja e registre **Bolsas**; na edição da Beatriz, o projeto aparece só leitura.

- Produção no OpenAlex: com a fila rodando (`php artisan queue:listen`), cada publicação adicionada em Meu trabalho → Produção recebe o selo "Encontrada no OpenAlex" ou "Não encontrada — não invalida a produção". Para conferir as pendentes (ex.: as do cenário de teste): `php artisan producao:verificar-openalex`.
- Índice de qualidade (depois de finalizar a defesa da Juliana, item 0): o quadriênio "2025–2028" já vem cadastrado (Secretaria: Configurações → Quadriênios). Como `colaborador@ppgmsb.test` (Luciana, membro da CPA) abra **CPA → Faixas e pesos**, defina pelo SWARA os pesos das dimensões e das fontes e veja em **CPA → Qualidade** o painel anonimizado: panorama, evolução, recortes, pontos fortes e fracos, confiabilidade e projetos × objetivos; baixe a planilha. Como Secretaria, em **Membros da CPA → Acessos** veja o registro de auditoria. Como `permanente1@` (orientadora), a banca finalizada mostra as avaliações confidenciais no relatório consolidado. Como `permanente1@` (orientadora), a tela da banca finalizada mostra "Como a banca interpretou o trabalho". Como Secretaria, os membros ficam em Configurações → Membros da CPA.

0. Defesa da Juliana (`discente.completo`): como ela, em **Meu trabalho** responda as **Recomendações da qualificação** (duas) e o **Espelho da avaliação** (notas de 0 a 10), envie o `.zip` marcando as 4 declarações e envie o pedido à homologação; como Secretaria, na homologação confira as atividades (registre a proficiência), marque o artigo como "conta para o mínimo" e homologue a banca de defesa; entre como `permanente1@ppgmsb.test` (orientadora) — em **Verificação das referências** só aparecem as dela; o bloco **Autoavaliação** fica antes de "Status dos avaliadores"; responda as 15 afirmações e a **Avaliação do orientando** (5 elementos); depois que `colaborador@` e `externo.ufba@` enviarem as avaliações, finalize — ata e folha vão por e-mail aos avaliadores e o **certificado de participação** a avaliadores e presidente (`storage/logs/laravel.log`). Para testar o plágio, marque "Identifiquei plágio" no formulário sigiloso de um só avaliador: a finalização fica bloqueada até o alinhamento no quadro "Plágio" da tela da banca.

1. Como Secretaria, defina coordenador e vice no Programa (as permanentes aparecem na lista).
2. Cadastre banca para o discente sem ORCID e confira a recusa; entre como ele, informe o ORCID em **Meu perfil** e tente de novo.
3. Entre como `secretaria.outro@ppgmsb.test` e confirme que usuários, bancas e relatórios do PPGMSB não aparecem.
4. Importe discentes por CSV (**Usuários → Importar discentes**) e confira os e-mails de primeiro acesso em `storage/logs/laravel.log` (com `MAIL_MAILER=log` e a fila rodando).
5. Em **Usuários**, abra a aba **Sem orientador**, edite o discente e registre o orientador; depois troque-o com SEI e justificativa e confira a linha do tempo; inclua e retire um coorientador.

## Verificação antes de disponibilizar

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan route:list --path=membros-externos
php artisan route:list --path=criterios-cpa
php artisan route:list --path=bancas
php artisan test --compact
npm run build
git diff --check
```

Em uma base de homologação descartável, valide todas as migrações e seeders com:

```powershell
php artisan migrate:fresh --seed --force
```

Não execute `migrate:fresh` na base de desenvolvimento ou produção: ele apaga todas as tabelas.
