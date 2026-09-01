# Evolução operacional — registro de execução

## Preservação e linha de base

Em 27/08/2026, antes de alterar o sistema: 27 testes Laravel / 166 asserções e TypeScript passaram. Backup consistente SQLite com integrity_check=ok, documentos privados e código preservados em `../rd-backup-20260827-lpi9Qt`. Não executar seeders contra o banco existente. Não há repositório Git nesta pasta.

## Matriz de requisitos e divergências

| ID | Requisito | Fonte/data | Situação | Aceite verificável |
|---|---|---|---|---|
| R01 | Viabilidade e Gestão são contratações distintas no mesmo caso | Briefing 01, 21/08, consolidado 24/08; plano aprovado 27/08 | Confirmado para implementação | Entregar Viabilidade e encerrar sem marcar perda; Gestão mantém o mesmo ID |
| R02 | Express e Completo têm entregáveis diferentes; estratégico não definido | SOE e Briefing 01 | Declarado; estratégico pendente | Lista por modalidade, sem preço automático |
| R03 | Faixas de preço divergentes | SOE 3.500/6.000; Briefing 01 faixas e estratégico | Pendente | Nenhuma faixa se converte automaticamente em preço aprovado |
| R04 | Gestão 20% ou 15%; administração 15% e bases | SOE versus Briefing 01; Briefing 02 sem respostas | Pendente | Simulação rotulada, revisão final e envio real bloqueados |
| R05 | Autoridade comercial independente de administrador técnico | Plano aprovado 27/08 | Confirmado para implementação; pessoas pendentes | Teste servidor nega aprovação a administrador sem atribuição |
| R06 | Entrada conversacional, contexto estruturado e revisão humana | Briefing 01 e plano | Confirmado | Fatos editáveis, sugestões individuais com evidência, sem aprovação pela IA |
| R07 | Dados permitidos ao provedor | Briefing 02 sem respostas | Pendente | Sem autorização explícita, OpenAI não recebe contexto real |
| R08 | Fornecedor reconfirma medidas/quantidades após visita e mudanças | Incidente Meeting Greco–GPL, Briefing 01 | Declarado; gate confirmado no plano | Alteração invalida reconfirmação e bloqueia liberação |
| R09 | Tarefas internas não dependem de evento | SOE e plano | Confirmado | Criar tarefa sem oportunidade, atribuir e concluir na agenda |
| R10 | Orçamento/versionamento/documentos rastreáveis | Plano aprovado 27/08 | Confirmado | Snapshot imutável, versão esperada obrigatória, conflito não sobrescreve |
| R11 | 3–5 dias e 5–10 horas, 9–15 propostas/mês | Briefing 01 | Estimativa declarada | Mostrar como referência declarada, comparar apenas a registros reais |
| R12 | Piloto, responsáveis/suplentes, cotações e critérios | Briefing 02 sem respostas | Pendente | Registrar decisão e evidência antes de liberar operação real |

## Inventário inicial de ações

| Área | Estado inicial | Correção prioritária |
|---|---|---|
| Login e criação de oportunidade | Funcional, incompleto | Acesso ativo por requisição, cadastros relacionados |
| Clientes/equipe | Listas, sem edição | Formulários reais, validação e auditoria |
| Pipeline | Arraste funcional no código, regras parciais | Mesma regra servidor para arraste e seletor, justificativas/conflitos |
| Briefing | Mensagens persistidas, resposta JSON | Revisão individual, modo real da IA e anexos privados |
| Orçamento | Rascunho criado por GET; aprovação livre | GET puro, cotação/versão/permissão/regra confirmada |
| Proposta/contrato | Sobrescrevem versão | Snapshots e histórico; demonstração não equivale a envio |
| Produção | Tarefas básicas | Dependências e evidência técnica |
| Histórico | Mostra feedback do piloto | Separar auditoria operacional de feedback |
| Tutorial | Texto e estado local do navegador | Persistir progresso por usuário ligado a ações reais |

## Ordem e limites de entrega

1. Fundação: clientes/contatos, equipe/permissões, tarefas internas e agenda.
2. Casos: Comercial, Viabilidade e Gestão, com prontidão e evidências.
3. Contexto: briefing estruturado, revisão individual, arquivos privados e modos de IA.
4. Comercial: fornecedores/cotações, cálculo reproduzível, versões e propostas.
5. Operação: validação técnica, execução, encerramento e indicadores.
6. Aceite: comportamento em navegador e bancos, PDF/snapshots, pacote e recuperação.

Cada bloco exige testes de comportamento antes da próxima entrega. Os testes de MariaDB, hospedagem e provedores não serão declarados aprovados sem execução. Não há autorização implícita para publicar, enviar e-mail real, contratar fornecedor ou aprovar regra comercial.

## Incremento aplicado em 27/08

- Clientes/contatos com cadastro, edição e perfil; oportunidade com edição e vínculos normalizados nos novos registros.
- Equipe administrada no servidor, desativação de sessões, tarefas internas e agenda unificada. Autoridade comercial separada e desabilitada por padrão.
- Fornecedores e cotações imutáveis com referência textual da evidência. Ainda não há upload de documento de cotação nesta tela.
- Orçamento sem escrita por GET; edição/remoção/duplicação de itens, clone de versão, controle de revisão, bloqueio de cotação vencida e snapshot de revisão. Taxas explícitas são simulações, não regras aprovadas.
- Dinheiro calculado por inteiros, half-up por etapa para novos itens; fórmula legada mantém arredondamento final único. Entrada brasileira sem separador de milhar.
- Jornada própria no hub: configuração Express/Completo, contratação, entrega, aceite, encerramento sem Gestão ou contratação separada da Gestão. Não deduz aprovações das etapas antigas. Integração completa com o Kanban e gates técnicos permanece no próximo bloco.
- Histórico operacional separado dos feedbacks; ações administrativas não são listadas a produtores.
- IA manual por padrão; demo determinística; OpenAI depende de AI_MODE=openai, chave e AI_DATA_POLICY_APPROVED. Estrutura interna do diff validada. A revisão individual e a idempotência concorrente do job ainda precisam ser concluídas.

Verificação deste incremento: 46 testes / 276 asserções, TypeScript e build Vite passaram. Migrações 000007–000009 passaram numa cópia do SQLite antes de aplicar no banco local. Integridade ok; preservados 5 casos, 2 mensagens e 2 orçamentos. Nenhum seeder executado.

Limitações de aceite: política do navegador bloqueou abertura automatizada; não houve teste visual/arraste. MariaDB, produção, PDF real, anexos privados, revisão final de documentos e gates completos da produção não foram validados/concluídos. O plano integral não está concluído. O pacote Hostinger ainda precisa ser regenerado após os próximos blocos.
