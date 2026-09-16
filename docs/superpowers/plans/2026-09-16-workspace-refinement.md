# Revisão visual e navegação — 16 de setembro de 2026

## Problemas confirmados

- A tela inicial ainda combinava indicadores circulares com brilho e superfícies operacionais sóbrias. Os cabeçalhos e cartões não tinham uma hierarquia consistente.
- Projetos priorizava o laboratório fictício, deixando os casos reais abaixo da demonstração.
- O Kanban ocupava pouco espaço útil, sem navegação direta para as etapas distantes. O detalhe não recebia foco e não fechava com Escape.
- Os erros locais de Fornecedores e de abertura de oportunidade eram causados por migrações pendentes: `supplier_quote_comparisons` e `event_briefs` não existiam no SQLite utilizado pelo servidor da porta 8000.

## Entregas

- Linguagem visual grafite consistente: tipografia de sistema, indicadores lineares, menos ornamentação, cartões e tabelas com hierarquia e contraste revisados.
- Navegação com nomes visíveis em telas largas e identificação da página atual para tecnologia assistiva.
- Projetos prioriza casos reais, permite busca por título, cliente, responsável e etapa; mantém o laboratório em uma seção expansível.
- Kanban com modo ampliado, atalhos para cada etapa e quantidade de oportunidades, filtros avançados recolhíveis e cartões legíveis.
- Detalhes e justificativas de mudança de etapa recebem foco, limitam a navegação de teclado à janela e devolvem o foco ao controle de origem. Escape fecha o detalhe; justificativas não são descartadas por uma resposta de validação.
- Banco local atualizado com as 11 migrações pendentes, após backup consistente em `work/rd-before-visual-20260916.sqlite` no workspace do Operon Pulse. Dados existentes preservados; nenhuma migração destrutiva de reset foi usada no banco local.

## Verificação

- Testes de regressão adicionados em `tests/e2e/workspace-refinement.spec.ts`. Falhas iniciais observadas antes da implementação: foco, ampliação ausente e organização anterior de Projetos.
- `npm run quality`: formatação, 23 testes de interface, TypeScript e build aprovados.
- Navegação, layout, acessibilidade, operações do Pipeline e novos cenários aprovados em desktop, tablet e celular.
- Fornecedores e a oportunidade 1 abertos manualmente na porta 8000 após a migração.
- Capturas desktop/mobile revisadas; contraste de colunas vazias e sobreposição do modo ampliado corrigidos durante a verificação.

Esta revisão não aprova novos critérios de escopo nem atribui pontos ao Pulse. A medição de progresso depende de nova auditoria do código atual.
