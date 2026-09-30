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
4. Cadastre discentes individualmente (curso e Lattes obrigatórios) ou em lote em **Usuários → Importar discentes** (CSV com prévia; e-mails de primeiro acesso enviados pela fila).
5. Acesse **Orientações** e atribua o orientador/coorientadores ao vínculo acadêmico do discente.
6. Acesse **Membros externos**, envie o convite e acompanhe o cadastro; abra o diploma, devolva com observação ou aprove.
7. Acesse **Critérios da CPA** e mantenha uma versão ativa para qualificação e defesa.
8. Cadastre ou homologue bancas: o sistema confere a regra do curso e o **ORCID do discente**; sem eles a banca não é salva/homologada. O discente informa o ORCID em **Meu perfil**.
9. Somente bancas homologadas ficam liberadas para envio do trabalho e avaliação (o envio também exige ORCID).

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
