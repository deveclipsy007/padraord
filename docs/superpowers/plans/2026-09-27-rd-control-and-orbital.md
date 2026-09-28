# Controle operacional e Agente Orbital RD

Implementar nesta tarefa, sem agentes paralelos.

- Central de pendências por projeto: quem precisa responder, responsável, prazo, próximo contato e histórico.
- Portal do cliente: prévia, publicação explícita, seleção de entregas e propostas liberadas, retorno e arquivos privados. Links expiram e podem ser revogados.
- Impacto de mudanças: prévia de itens que precisam ser conferidos, ligada à edição de contexto.
- Prontidão: verificações objetivas de briefing, documentos, equipe, fornecedores, validação técnica e financeiro; ausência de dados permanece pendente.
- Capacidade: carga da semana, tarefas sem horário e conflitos reais de agenda.
- Versões: comparar revisões registradas de briefing, orçamento e documento.
- Automações: duas regras determinísticas, prévia antes de ativar, execução idempotente e desfazer somente registros ainda intactos.
- Experiência por perfil: foco escolhido pelo usuário, administração restrita ao administrador; foco não concede permissões.
- Orbital: página premium bloqueada, exemplos demonstrativos por perfil e Telegram. Sem integração ou ativação de IA nesta entrega.

Verificar regras de acesso, isolamento do portal, expiração, versão obsoleta, repetição de execução, comparação, carga e telas desktop/mobile. Preservar os dados atuais. Não publicar links de clientes durante a verificação.

## Entrega

As áreas previstas foram implementadas. O Orbital é uma apresentação bloqueada; não há bot nem integração Telegram ativos.

O portal exige publicação explícita pelo responsável do projeto ou administrador. Seus arquivos ficam privados; propostas expõem somente as seções públicas e o valor da versão liberada. Links não são criados durante a homologação no banco local.

As automações começam pausadas. O administrador confere a prévia e ativa cada regra. A agenda padrão do Laravel executa `rd:automations` a cada cinco minutos quando o agendador do servidor estiver em execução; há execução manual pela interface. Desfazer só cancela tarefas automáticas que continuam intactas.

A capacidade usa as horas configuradas pelo administrador e os horários registrados. Tarefas sem horário permanecem destacadas e não recebem estimativa inventada.

Verificação realizada em banco SQLite isolado e navegador desktop/celular emulado. A publicação em servidor e a homologação em aparelhos físicos não fazem parte desta alteração local.

Resultado final da verificação: 298 testes PHP (2.118 asserções), 27 testes de interface, TypeScript e build aprovados. Estilo conferido nos arquivos PHP alterados. Navegação e prévias verificadas no navegador; não foram publicados portais nem ativadas regras com dados reais.
