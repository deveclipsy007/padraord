# Interface editorial e pastas de clientes

## Direção aprovada no pedido

As três referências fornecidas pelo usuário orientaram uma linguagem com tipografia editorial nos destaques, superfícies translúcidas, capas com volume, pastas e acento lavanda. Esta entrega substitui a direção estritamente monocromática anterior.

## Implementação

- Camada visual compartilhada em `resources/css/editorial-workspace.css`: tipografia serifada nos destaques e nomes, Manrope nos controles, papéis claros, acento lavanda, superfícies com profundidade, botões e navegação coerentes. Nenhuma fonte remota ou biblioteca de animação foi adicionada.
- Dashboard com composição editorial e fundo abstrato; projetos e cabeçalhos de contexto recebem a mesma linguagem.
- Clientes abre em pastas com abas, capas, identificação e ações independentes. A lista permanece disponível.
- Editor de capa com quatro temas (Íris, Névoa, Duna e Aurora), prévia e imagem própria. Tema e imagem são persistidos por cliente, com acesso autenticado à imagem. Aceita JPG, PNG e WebP até 5 MB e 6000 px por dimensão. Não aceita SVG.
- Kanban com cartões tipográficos, cliente, próxima ação, responsável e prioridade distinguíveis; acabamento de borda, hover e arraste, respeitando movimento reduzido.
- Corrigida a seta duplicada do seletor de etapa: o seletor nativo mantém sua interação e recebe apenas o indicador visual do componente.

## Dados e escopo

Migração aditiva `2026_09_16_000001_add_client_covers` aplicada ao banco local após backup consistente em `work/rd-before-covers-20260916.sqlite` no workspace do Pulse. Os dados e imagens existentes não foram apagados.

Nenhum peso ou percentual do Pulse foi editado. Novas alterações exigem a auditoria habitual para manter atualizada a medição de escopo verificado.

## Verificação

Os testes de capa falharam inicialmente por ausência da rota; os novos testes de interface falharam por ausência das pastas e pela seta nativa ainda ativa. Depois da implementação, verificam persistência de tema, upload, recarga, troca de imagem por tema, acesso autenticado, rejeição de entradas inválidas e interação independente dos controles do Kanban.

Build, tipagem, formatação e 23 testes de interface aprovados. Os 278 testes de backend passaram, com 1.931 asserções. A suíte completa de navegador passou com **105 cenários em 3,2 minutos**, cobrindo desktop, tablet e celular, incluindo acessibilidade, navegação, briefing, decisões, documentos, produção, financeiro e os novos fluxos de pastas. A inspeção visual incluiu capturas de pastas e Kanban nos três tamanhos; o contraste do seletor de visualização foi corrigido antes da execução final.
