# Central de Produção, equipe e home

Produção agora tem entrada principal em /production. Eventos possuem contexto próprio em /production/events/{id}, com pós-evento em /production/events/{id}/post-event. Os cards e links de produção/pós-evento foram retirados do conjunto de módulos comerciais, substituídos por passagem explícita para a execução. Rotas antigas permanecem compatíveis; gravações preservam regras, validações e vínculos existentes. A central oferece busca, filtros e contagens reais de tarefas, sem transformar tarefas em percentual de conclusão de projeto.

Equipe usa fichas, cargos sugeridos com ícones e cargo livre persistido em users.job_title. Cargo não concede permissões: acesso administrativo e autoridade comercial continuam separados e protegidos no servidor. Cadastro e edição ocorrem em drawers. Migração aditiva aplicada com backup SQLite em work/rd-before-team-roles-20260916.sqlite no workspace Codex.

Home organiza planejar/executar/acompanhar, substitui cartão escuro por Agente RD claro, evita mensagem de tudo sob controle com pendências, prioriza etapas ocupadas e permite títulos completos. Corrigido deslocamento da capa dos projetos e alcance do botão de sair em tablet. Animações respeitam movimento reduzido e não esmaecem texto.

Validação: 280 testes PHP / 1969 assertions; quality aprovado (23 testes UI, tipagem, formatação e build). Rodada completa de navegador: 113/117; corrigidos seletor ambíguo do novo teste e overflow vertical da barra lateral. Rodada direcionada: 30/33; três falhas de contraste durante fade levaram à retirada da transparência da animação. Revalidação final dos seis cenários de produção/equipe: 6/6, incluindo auditoria de contraste em equipe, produção e home, nas três resoluções. Build final aprovado após ajuste do ícone decorativo do cartão do Agente RD. Inspeção visual em localhost e screenshots desktop/mobile.

Sem alteração de escopo, pesos ou evidências do Pulse; nenhuma afirmação de percentual atualizado.
