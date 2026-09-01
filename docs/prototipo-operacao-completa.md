# Protótipo da operação completa — 27/08/2026

## Entrada e isolamento

Acesse `/projects` e selecione **Experimentar Conferência Horizonte**. Cada clique cria um novo cenário; nenhum teste anterior é apagado. O cenário tem identificador DEMO próprio e persiste em `prototype_cases`, sem criar oportunidades, fornecedores, cotações ou documentos nas tabelas operacionais existentes.

Toda decisão, documento e saída pertence ao modo demonstração. Não há envio SMTP, contratação, assinatura, processamento externo de IA ou validação jurídica neste laboratório. A mesma equipe autenticada pode colaborar nos cenários. Contas desativadas perdem acesso pelo middleware existente.

## O que pode ser experimentado

1. Hoje: fila real de tarefas, filtros de responsável/prioridade/atraso e acesso à agenda.
2. Comercial: cadastro operacional existente; no cenário, editar contexto e registrar etapa, perda/cancelamento e justificativa.
3. Projeto: workspace com dez abas e mesmo identificador; Viabilidade Express/Completo, contratação, entrega, aceite e encerramento independente.
4. Briefing: texto preservado, sugestão determinística ligada à mensagem, aceite/rejeição individual, campos editáveis e revisão humana.
5. Cotações: comparar fornecedor, custo, validade, condições e evidência textual fictícia.
6. Orçamento: selecionar cotação, calcular custos/taxas simuladas, revisar, congelar snapshot e criar nova versão.
7. Documentos: proposta/estrutura demonstrativa de contrato, finalidade Viabilidade/Gestão, texto e snapshots, revisão, envio/aceite simulados e preview imprimível.
8. Produção: medidas/quantidades, reconfirmação, dependências de tarefas, responsáveis e fases.
9. Evento/pós: liberação após verificações, passagem ao pós, ocorrências, extras, realizado informado, aprendizado e checklist de encerramento.
10. Histórico: registro de ações, responsável, data, evidência e revisão. Busca global localiza cenários com selo DEMO.

## Roteiro de teste

### Caminho A — Viabilidade sem Gestão

Na aba Viabilidade, registre contratação fictícia; marque os entregáveis; registre entrega e aceite; encerre sem Gestão. O resultado será “Viabilidade concluída”, nunca perda.

### Caminho B — Gestão até o encerramento

1. Envie contexto e revise o briefing, preenchendo os seis campos essenciais.
2. Contrate e entregue a Viabilidade; registre o aceite.
3. Cadastre uma cotação válida e adicione itens ao orçamento.
4. Registre medidas/quantidades e reconfirmação na Produção.
5. Revise o orçamento e gere proposta de Gestão em Documentos.
6. Registre revisão, envio simulado e aceite da proposta, nessa ordem.
7. Registre contratação de Gestão na aba Viabilidade.
8. Crie e conclua tarefas respeitando dependências; libere montagem/evento.
9. Passe ao pós-evento, registre síntese, realizado e aprendizado; encerre.

### Mudança e recuperação

Alterar medidas ou briefing invalida a revisão vigente e marca documentos como desatualizados, preservando seus conteúdos. Reconfirme o fornecedor, revise o novo orçamento, gere e revise nova proposta antes de liberar execução. Uma cotação vencida bloqueia a revisão. Atualizações concorrentes retornam mensagem recuperável; não sobrescrevem o estado silenciosamente.

## Limites desta entrega

Esta é a visualização navegável da operação, não a conclusão do sistema de produção inteiro. Anexos privados reais, PDF automático final, contratos jurídicos, indicadores medidos, envio externo, integração completa do Kanban com ciclos e demais regras comerciais ainda exigem entregas posteriores. No laboratório, o PDF é obtido pela impressão do navegador e sempre contém aviso de demonstração. Documentos não são enviados pelo sistema.

Cadastro operacional completo de fornecedores e orçamento com contingência/edição permanece nas páginas existentes; o laboratório representa uma fatia conectada dessas funções. O roteiro registra progresso por ações do cenário compartilhado, não uma conclusão individual de treinamento por usuário.

Validação: testes de comportamento HTTP cobrem as duas jornadas, conflitos, cotação vencida, isolamento, mudança técnica e documentos desatualizados. TypeScript e build verificados. Sem aceite visual em navegador: a política do navegador integrado bloqueou o localhost anteriormente e não foi contornada. MariaDB e publicação Hostinger não executados nesta entrega.
