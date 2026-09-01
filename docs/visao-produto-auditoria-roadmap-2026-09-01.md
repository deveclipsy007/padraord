# Padrão RD OS — visão, auditoria e roteiro definitivo

> Documento-mestre do produto, da arquitetura e da liberação operacional.
>
> Data da auditoria: 01/09/2026
>
> Escopo de produção: operação interna completa, OpenAI real, SMTP, exportação de custos para Odoo e assinatura externa registrada manualmente.

## 1. Visão do sistema-alvo

O Padrão RD OS será um sistema operacional interno para empresas de eventos. Seu objetivo é permitir que qualquer pessoa autorizada compreenda um caso, assuma a próxima tarefa e prepare uma entrega para revisão sem depender de Rômulo para reconstruir todo o contexto.

Rômulo continuará participando das decisões que exigem sua autoridade. O sistema reduzirá sua presença obrigatória na coleta de informações, organização do briefing, busca de fornecedores, preparação do orçamento e cobrança de pendências.

Um único caso acompanhará toda a jornada:

```text
Entrada → Qualificação → Briefing → Viabilidade → Orçamento → Proposta
→ Contratação → Produção → Evento → Pós-evento → Encerramento
```

O caso não será apenas um registro de CRM. Ele terá contexto, responsável, prazo, próxima ação, bloqueios, evidências, documentos, versões e histórico.

### Princípios do produto

- Informação é registrada uma vez e reaproveitada com origem visível.
- A tela destaca a próxima decisão, não uma coleção de formulários.
- A IA prepara rascunhos; pessoas aprovam escopo, preço, fornecedor e documento.
- Desconhecido, hipótese e conflito nunca aparecem como fato confirmado.
- Toda mudança relevante mostra o impacto em orçamento, documentos, tarefas e validações.
- O modo manual continua completo quando a IA, SMTP ou Odoo estiverem indisponíveis.
- Viabilidade, aceite da entrega e contratação da Gestão são decisões distintas.
- Uma Viabilidade encerrada sem Gestão é um resultado válido, não uma oportunidade perdida.

### Escopo incluído

- Operação interna completa em um único workspace.
- OpenAI como provedor real atrás de `AiProvider`.
- SMTP para envio autorizado de documentos.
- Exportação de custo de IA para Odoo por outbox idempotente.
- Assinatura realizada externamente, registrada com data e evidência.
- Deploy completo em Hostinger Web Business.
- SQLite local e MariaDB em produção.

### Fora do escopo inicial

- WhatsApp automático, Google Drive e portal externo do cliente.
- Assinatura eletrônica integrada.
- Estoque, folha, ERP contábil e conciliação bancária.
- Editor 3D.
- Reconhecimento biométrico persistente de voz.

## 2. Auditoria do estado atual

Os estados abaixo substituem percentuais subjetivos de progresso:

- **Validado localmente:** comportamento comprovado no ambiente local.
- **Funcional incompleto:** há fluxo executável, mas faltam regras, cobertura ou acabamento para operação.
- **Demonstração:** interação serve para validar conceito e não deve ser tratada como operação real.
- **Não validado em produção:** depende de MariaDB, Hostinger, credenciais ou serviço externo.
- **Bloqueado por acesso ou decisão:** não deve ser concluído por suposição.

| Área | Estado | Evidência atual | Trabalho restante |
|---|---|---|---|
| Arquitetura Laravel/Inertia | Validado localmente | Aplicação monolítica same-origin, React com build Vite e separação de release | Consolidar contratos, health check e remoção de legados |
| Dashboard e navegação | Funcional incompleto | Rotas e shell navegáveis | Transformar métricas em fila operacional real |
| Pipeline e oportunidades | Funcional incompleto | Kanban, cards e arraste existentes | Completar prontidão, filtros, justificativas e conflitos |
| Clientes e contatos | Funcional incompleto | Listas, cadastro e vínculos iniciais | Completar CRUD, relacionamento e histórico |
| Briefing | Funcional incompleto | Mensagens persistidas e revisão inicial | Consolidar evidências, lacunas e aprovação |
| Áudio e IA | Funcional incompleto | Contexto transversal, diarização e ledger presentes | Unificar fluxo legado, áudio longo, avaliações e produção |
| Viabilidade | Funcional incompleto | Página e jornada iniciais | Entregáveis, contratação, entrega, aceite e encerramento |
| Fornecedores e cotações | Funcional incompleto | Cadastro, cotações e comparação iniciais | Revisões, evidências e ligação completa ao orçamento |
| Orçamento | Funcional incompleto | Itens, versões e cálculos básicos | Regras comerciais, snapshots, aprovações e impactos |
| Documentos | Funcional incompleto | Propostas/contratos versionados e PDF local | SMTP, autoridade comercial e aceite externo |
| Produção e pós-evento | Demonstração operacional | Telas e tarefas básicas | Operação real, validação técnica e encerramento |
| SMTP, Odoo e OpenAI | Não validado em produção | Adaptadores/outbox e testes simulados | Credenciais, chamadas reais, monitoramento e recuperação |
| Hostinger | Bloqueado parcialmente por acesso | Scripts e layout `app_core`/`public_html` locais | Confirmar capacidades, publicar, monitorar e restaurar |

### Evidências técnicas coletadas

- 96 rotas Laravel após as rotas protegidas de health e autoridade comercial.
- 26 controllers, 31 models e 20 migrations após as adições deste ciclo.
- 25 páginas React.
- 120 testes PHP e 918 assertions passando no SQLite.
- 12 testes de interface passando.
- TypeScript e build Vite passando.
- Pint passou sem arquivos pendentes após a correção dos 19 arquivos do baseline.
- Testes PHP locais usam SQLite; MariaDB agora está coberto por configuração e script para CI.
- Suíte Playwright versionada criada com 21 testes e três breakpoints.
- Workflow GitHub Actions criado, aguardando repositório privado e execução remota.
- Pacote Hostinger atual foi reconstruído e auditado pelo verificador de release.
- O PHP local observado é 8.5.4; a produção deverá ser fixada e verificada em 8.4.
- Telas principais foram observadas em desktop, tablet e celular sem rolagem horizontal global, mas o briefing móvel tem tabs contextuais com affordance fraca e os botões flutuantes de Assistente/Feedback disputam espaço com o dock inferior.
- Há dois fluxos de áudio que precisam convergir: o legado do Briefing e o motor transversal de contexto.
- O cenário demonstrativo possui duplicidades e precisa de restauração idempotente.

### Classificação de funcionalidades existentes

| Funcionalidade | Classificação para o próximo ciclo |
|---|---|
| Login e sessão | Funcional, cobertura de permissões a ampliar |
| Navegação e workspace do caso | Funcional incompleto |
| Drag-and-drop do Pipeline | Funcional incompleto; precisa usar a mesma regra do seletor |
| Clientes e fornecedores | Funcional incompleto |
| Assistente contextual | Funcional incompleto; prévia e confirmação existem, cobertura limitada |
| Preparação de briefing | Funcional incompleto |
| Orçamento e versões | Funcional incompleto |
| Propostas e PDF | Funcional incompleto |
| Produção e pós-evento | Demonstração operacional |
| OpenAI real | Não validado em produção |
| SMTP real | Não validado em produção |
| Exportação Odoo | Não validado em produção |
| Deploy Hostinger | Não validado em produção |

## 3. Arquitetura definitiva

### Aplicação e hospedagem

Preservar Laravel 13, React 19, TypeScript, Inertia 3, Vite e a estrutura Hostinger já definida.

```text
/home/u12345678/domains/app.padraord.com.br/
├── app_core/
│   ├── app/ bootstrap/ config/ database/ resources/views/ routes/
│   ├── storage/ vendor/ artisan composer.json composer.lock .env
│
└── public_html/
    ├── index.php .htaccess
    └── build/manifest.json build/assets/*
```

Regras de publicação:

- O único ponto de entrada público será `public_html/index.php`.
- Não haverá `public_html/index.html`.
- `.env`, `vendor`, migrations, testes, logs, uploads privados e SQLite ficarão fora da área pública.
- Node.js, npm e Vite rodarão somente localmente ou na CI.
- A aplicação pública e os endpoints internos usarão a mesma origem.
- MariaDB será exercitado antes de qualquer migração de produção.
- O pacote de release será gerado novamente a cada ciclo que alterar o código.

### Domínio central

Todos os módulos compartilharão:

- `case_id` e identificador estável da oportunidade.
- Cliente, contatos, responsável e colaboradores.
- Ciclo (`commercial`, `viability`, `management`) e fase.
- Próxima ação, prazo, prioridade e bloqueio.
- Estado de prontidão e revisão esperada.
- Fontes, evidências e documentos relacionados.
- Histórico de alterações e autor da decisão.

Serviços de domínio serão a única fonte para transições, cálculos, aprovações, versionamento, aplicação de sugestões, auditoria e exportação de custos. Controllers, formulários e Assistente chamarão os mesmos serviços.

### Contratos compartilhados

Consolidar no frontend e backend os contratos:

- `CaseWorkspaceSummary`
- `CaseModuleStatus`
- `CaseReadiness`
- `ContextEntry`
- `ContextSegment`
- `EvidenceReference`
- `ContextChangeSet`
- `AssistantPreview`
- `AssistantResult`
- `AiUsageEntry`
- `OdooCostExport`
- `OperationalEvent`

Adicionar o endpoint operacional protegido:

```text
GET /api/v1/health
```

Ele retornará apenas aplicação, versão, banco, fila, storage, e-mail, IA, Odoo e horário do servidor. Nenhum segredo será exposto.

### IA e áudio

O fluxo oficial será:

```text
Upload → preparação local → armazenamento privado → transcrição
→ diarização → extração → prévia consolidada → confirmação → rascunhos
```

Para reuniões de até uma hora, o navegador carregará sob demanda um worker de mídia. Ele converterá arquivos compatíveis para formato comprimido e dividirá o resultado em segmentos menores que 20 MB, preferencialmente em pausas e com pequena sobreposição. O backend manterá upload retomável, original privado e segmentos vinculados à mesma entrada.

A API de transcrição aceita arquivos de até 25 MB e recomenda compressão ou divisão para arquivos maiores, evitando cortar uma frase no meio. [Guia oficial de transcrição](https://developers.openai.com/api/docs/guides/speech-to-text)

Usar `gpt-4o-transcribe-diarize`, com saída diarizada e chunking automático quando aplicável. [Modelo oficial de diarização](https://developers.openai.com/api/docs/models/gpt-4o-transcribe-diarize)

O modelo inicial de organização textual será `gpt-5.6-luna`, com Structured Outputs e schema estrito, sujeito a avaliação com dez briefings autorizados. [Documentação oficial do GPT-5.6 Luna](https://developers.openai.com/api/docs/models/gpt-5.6-luna)

Cada sugestão deverá distinguir:

- fato extraído;
- hipótese;
- conflito;
- informação ausente.

Cada item terá referência à mensagem, documento ou trecho do áudio, falante, horário e módulos afetados. A IA nunca aprovará escopo, fornecedor, preço, documento ou contratação.

### Custos e Odoo

Registrar por processamento:

- caso e organização;
- operação e modelo;
- tokens ou duração de áudio;
- estimativa e custo confirmado;
- moeda e tabela de preço;
- tentativas, timeout, erro e resultado;
- chave idempotente;
- estado de exportação.

O outbox seguirá:

```text
pendente → enviado → confirmado
             └→ falhou → nova tentativa controlada
```

Uma repetição de fila nunca criará um custo duplicado.

## 4. Roteiro em seis ciclos

Cada ciclo terá uma saída verificável. O ciclo seguinte somente começa após a saída anterior passar pelos testes definidos.

### Ciclo 1 — fundação, segurança e prontidão da infraestrutura

Implementar:

- Corrigir os 19 arquivos apontados pelo Pint.
- Criar matriz de papéis, permissões e autoridade comercial.
- Proteger no servidor arquivos, aprovações, exportações e configurações.
- Consolidar tokens, contratos e componentes compartilhados.
- Criar Playwright versionado e CI.
- Adicionar `/api/v1/health` e logs estruturados sem dados sensíveis.
- Tornar o cenário demonstrativo canônico e idempotente.
- Testar migrations novas e de atualização em SQLite e MariaDB.
- Confirmar na Hostinger domínio, diretório, PHP 8.4, extensões, MariaDB, SSH/SFTP, cron, SSL, SMTP e backup.

Saída:

- Pint, TypeScript, Vitest, build e testes PHP limpos.
- Banco vazio e banco existente migrados nos dois drivers.
- Nenhum segredo, source map ou arquivo privado no pacote público.
- Matriz visual e funcional de todas as rotas.

### Ciclo 2 — comercial, clientes e fila de trabalho

Implementar:

- Central “Hoje” com minhas tarefas, revisões, atrasos e próxima ação.
- CRUD de clientes, contatos e oportunidades.
- Kanban e lista com filtros compartilhados.
- Qualificação, responsável, prazo, prioridade e motivo de perda/cancelamento/retorno.
- Tarefas internas independentes de evento.
- Agenda unificada de atividades e tarefas.
- Busca global, comando rápido e histórico humano.
- Formulários progressivos, foco, teclado e comportamento móvel.

Saída: uma pessoa cria cliente, contato e oportunidade, qualifica o lead, agenda retorno, delega a tarefa e recupera tudo após novo login.

### Ciclo 3 — contexto inteligente, áudio, Briefing e Viabilidade

Implementar:

- Unificar o fluxo de áudio legado com o motor de contexto.
- Worker de compressão/segmentação carregado sob demanda.
- Upload privado retomável e transcrição de até uma hora em segmentos.
- Diarização, identificação corrigível dos participantes e reprodução dos trechos.
- Extração de fatos, hipóteses, conflitos, lacunas e evidências.
- Prévia consolidada por módulo, confirmação transacional e idempotente.
- Briefing editável e aprovação humana.
- Viabilidade Express e Completa, entregáveis, estimativa, contratação, entrega e aceite.
- Encerramento válido da Viabilidade sem Gestão.
- Avaliação com dez briefings fictícios ou autorizados.

Saída: uma reunião gera transcrição e rascunhos de Briefing/Viabilidade sem perder o conteúdo original e sem transformar estimativa em preço aprovado.

### Ciclo 4 — fornecedores, cotações, orçamento e documentos

Implementar:

- Fornecedores, serviços, contatos, duplicidades sugeridas e histórico.
- Consultas e cotações com evidência, validade, condições e tipo de preço.
- Comparação de alternativas equivalentes.
- Transferência da cotação escolhida para o orçamento sem redigitação.
- Orçamento por categorias, itens, versões e snapshots.
- Cálculo determinístico em centavos, com custos, Gestão, administração, contingência e preço separados.
- Bloqueios por custo ausente, cotação vencida ou regra pendente.
- Propostas distintas de Viabilidade e Gestão.
- Contrato derivado de snapshots aprovados.
- PDF real e registro de assinatura externa.
- SMTP somente para versão liberada.

Saída: um produtor prepara o pacote comercial completo para revisão, e Rômulo vê dados, fontes, alterações e decisões sem reconstruir o caso.

### Ciclo 5 — produção, evento, pós-evento e memória

Implementar:

- Marcos, tarefas, responsáveis, prioridades e dependências.
- Visita técnica, medidas, desenhos, evidências e reconfirmações.
- Bloqueio de itens técnicos sem evidência atual.
- Responsabilidades e aprovações do cliente.
- Agenda de montagem, execução e desmontagem.
- Visão móvel orientada a “agora”, “a seguir” e “problemas”.
- Ocorrências, decisões, extras e impactos.
- Previsto versus realizado informado.
- Avaliação de fornecedores e retorno do cliente.
- Debriefing, aprendizados aprovados e checklist de encerramento.
- Indicadores de tempo, espera, retrabalho e distribuição.
- Assistente contextual com prévia em módulos permitidos.

Saída: o caso percorre contratação, produção, evento e encerramento com evidências, responsáveis e histórico.

### Ciclo 6 — integrações reais, piloto e produção Hostinger

Implementar e validar:

- OpenAI real, limites, timeouts, custos e indisponibilidade.
- SMTP real e preservação da cópia enviada.
- Odoo outbox com confirmação e retry controlado.
- PHP 8.4 web/CLI e MariaDB real.
- Cron e fila de banco na Hostinger.
- Upload privado, downloads autorizados e limpeza controlada.
- Novo release Hostinger com o código corrente.
- Deploy SFTP aditivo, preservando `.env`, `storage` e documentos.
- Smoke test, backup pré-migration e rollback de aplicação/banco.
- HTTPS, headers de segurança e logs operacionais.
- Piloto interno com caso autorizado.
- Runbook de operação e suporte.

O ciclo só será concluído após os acessos reais à Hostinger, SMTP e Odoo serem fornecidos e os testes passarem. Sem esses acessos, o estado correto é `release-ready`, nunca “100% ativo”.

## 5. Testes e critérios de aceite

### Testes automatizados

- PHPUnit para autenticação, permissões, CRUDs, transições, cálculos, snapshots, arquivos e auditoria.
- Testes de concorrência, idempotência, filas e conflitos de revisão.
- Vitest para componentes, contratos e estados de erro.
- Playwright versionado em 1440×900, 1024×768 e 390×844.
- SQLite e MariaDB em CI.
- Migrations em banco vazio e banco com dados existentes.
- Build Vite, manifest e pacote Hostinger.
- Smoke test após deploy e ensaio documentado de restauração.

### Jornadas obrigatórias

1. Lead → Viabilidade → entrega → encerramento sem Gestão.
2. Lead → Viabilidade → orçamento → Gestão → produção → pós-evento.
3. Áudio de uma hora → preparação → diarização → revisão → múltiplos módulos.
4. Rejeição parcial de sugestões sem perder as restantes.
5. Cotação vencida → bloqueio explicado → correção → nova revisão.
6. Mudança técnica → impacto → reconfirmação.
7. OpenAI indisponível → operação manual completa.
8. SMTP indisponível → documento e evidência preservados.
9. Falha no Odoo → custo mantido na outbox.
10. Upload interrompido → retomada sem duplicação.
11. Alteração concorrente → conflito recuperável.
12. Deploy interrompido → manutenção e rollback.
13. Usuário sem autoridade → operação permitida, aprovação bloqueada.

### Definição de pronto

O sistema estará concluído quando:

- todos os módulos operacionais estiverem ligados ao mesmo caso;
- a próxima ação, responsável, prazo e bloqueio forem claros;
- uma pessoa da equipe completar o piloto sem orientação constante;
- Rômulo participar apenas das decisões do seu papel;
- propostas forem rastreáveis a briefing, cotações e orçamento aprovados;
- mudanças mostrarem impactos e invalidarem revisões afetadas;
- a operação manual funcionar sem IA;
- OpenAI, SMTP, Odoo, MariaDB, fila e cron estiverem validados;
- os testes automatizados e de navegador passarem;
- a aplicação estiver publicada em Hostinger com HTTPS;
- backup e rollback tiverem sido ensaiados;
- `public_html` contiver somente entrada e assets públicos;
- o runbook estiver atualizado.

## 6. Premissas e decisões preservadas

- O workspace é de uma única empresa; não haverá multiempresa nesta etapa.
- Administrador técnico não recebe automaticamente autoridade comercial.
- Assinatura eletrônica continua manualmente registrada.
- A IA não inventa preços, fornecedores, quantidades, escopo ou aprovações.
- Áudio original, transcrições e documentos são privados.
- O processamento longo é resolvido por preparação e segmentação local antes da OpenAI; não haverá FFmpeg permanente na Hostinger.
- A política de envio de dados reais à OpenAI precisa estar aprovada antes do piloto.
- Taxas, preços, modalidades e autoridades que ainda não foram confirmados continuarão como regras pendentes.
- A publicação em Hostinger depende de acesso parcial ser convertido em acesso verificável.

## 7. Estado de liberação

### Registro de evidências — Ciclo 01

Executado localmente em 01/09/2026, sem apagar dados existentes:

| Verificação | Resultado |
|---|---|
| Backup SQLite | `cycle-01-backup-20260901/database.sqlite`; integridade `ok` |
| PHPUnit SQLite | 119 testes, 909 assertions — passou |
| Pint | passou sem arquivos pendentes |
| Composer validate/audit | passou; nenhum advisory conhecido |
| Vitest | 12 testes — passou |
| TypeScript | passou |
| Vite build | passou; manifest e assets compilados |
| Playwright | 21 testes em desktop/tablet/mobile — passou |
| Health protegido | token ausente/inválido retorna 401; token válido retorna contrato 200 |
| Headers | request ID, nosniff, frame deny, referrer policy e permissions policy cobertos |
| Release Hostinger | pacote atual gerado e verificado; `public_html/index.php`, sem `index.html`, secrets ou source |
| MariaDB | teste e script preparados para CI; não executado localmente (sem servidor/cliente disponível) |
| Git | branch `main` e baseline local commitada; nenhum remote GitHub configurado |
| Hostinger | nenhum upload; inventário marcado como pendente por falta de acesso real |

Arquivos de evidência do ciclo: `phpunit.mariadb.xml`, `scripts/ci/test-mariadb.sh`, `playwright.config.ts`, `tests/e2e/`, `.github/workflows/ci.yml`, `scripts/deploy/verify-hostinger-release.sh` e [inventário Hostinger](operations/hostinger-capabilities.md).

O teste MariaDB será a validação primária no GitHub Actions quando o repositório privado estiver disponível. O pacote local não é prova de produção; PHP 8.4, MariaDB, cron, SMTP, Odoo e OpenAI continuam sem validação externa.

**Estado atual:** fundação local validada; produção ainda não validada e o acesso remoto permanece bloqueado.

**Próximo bloqueio objetivo:** ativar o repositório privado/CI e coletar o inventário real do hPanel/FTP. O Ciclo 01 não inclui upload nem migration em produção.

**Critério para declarar produção:** somente após concluir o Ciclo 6, executar o piloto e comprovar deploy, smoke test, backup e rollback reais.
