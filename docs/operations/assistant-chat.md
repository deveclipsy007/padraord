# Assistente de conversa e painéis — 10/09/2026

## Uso

Abra “Assistente” em qualquer página. Selecione um caso e/ou fornecedor em Contexto quando necessário. Escreva uma pergunta ou pedido; não é preciso escolher uma ação antes de conversar.

O histórico pertence ao usuário autenticado e fica no banco privado. A interface recupera as 30 interações mais recentes. Para cada resposta, o provedor recebe até oito interações anteriores do mesmo usuário, caso e modo, mais um resumo do contexto selecionado e até oito tarefas pendentes. Não recebe acesso genérico ao banco, navegação web ou execução de código.

## Ativação

Administração → Inteligência artificial (/settings/ai):

1. Escolher OpenAI e a origem da credencial.
2. Cadastrar a chave (armazenada criptografada, nunca retornada ao navegador).
3. Aprovar a política de dados, definir limites mensal e por processamento.
4. Confirmar a própria senha para salvar.
5. Enviar um pedido no chat para validar a conta e o modelo.

Nenhuma chamada real foi realizada nesta entrega. Manual e demonstração mostram orientação explícita sem tentar simular uma conversa real da OpenAI.

O chat usa gpt-5.6-luna, separado dos motores de briefing e transcrição. Tarifas padrão de referência verificadas em 10/09/2026: US$ 0,20 entrada e US$ 1,20 saída por milhão de tokens. Fonte: https://developers.openai.com/api/docs/models/gpt-5.6-luna

O valor mostrado é um cálculo conservador, sem desconto de cache, não a fatura oficial. O consumo conhecido e as reservas em aberto aparecem separados. A reserva é feita antes da chamada e compartilhada com os demais processamentos. Timeout ou resultado incerto não é repetido automaticamente.

## Ações

O chat pode preparar tarefas, fornecedores, consultas e cotações. Toda alteração requer uma prévia e confirmação explícita. Edição substitui a prévia anterior, preserva a versão na conversa e impede confirmar a versão substituída. A confirmação reaproveita os serviços e validações dos formulários.

Não pode aprovar preços, contratar, apagar registros, enviar documentos ou assinar. Selecionar fornecedor não equivale a contratar. Dados ausentes continuam pendentes; outras operações permanecem disponíveis pelos módulos manuais.

## Persistência e segurança

- Migração expansiva 2026_09_10_000008_create_assistant_chat_turns.
- Histórico privado por usuário; identificador de envio único por usuário.
- Repetição do mesmo envio não duplica chamada nem confirmação.
- Contexto alterado durante a resposta impede criar uma prévia aparentemente atual.
- GET/POST /assistant/chat e POST /assistant/chat/{turn}/preview exigem sessão.
- Chamadas pagas possuem limite de frequência, reserva de consumo e auditoria sem chave ou conteúdo da conversa.
- Backup local pré-migração: storage/backups/before-assistant-chat-20260910.sqlite.

## Interface

Painéis têm cantos arredondados, margem visível, altura limitada e rolagem interna. Assistente deixa de disputar espaço com menu/modal aberto. Confirmações nativas de Agenda, Orçamento e Briefing foram substituídas por diálogo próprio, com foco, Escape e botão Voltar.

A estrela reutiliza o símbolo ✦ já presente nas apresentações da Padrão RD. Não substitui a pendência de receber um arquivo-mestre oficial do logo.

## Validação e limites

Testes PHP cobrem persistência, privacidade, limites, ações não permitidas, confirmação idempotente, edição e conflito de contexto. Playwright cobre conversa, recarga, confirmação e geometria nos três tamanhos de tela.

Qualidade do modelo real, credenciais, cobrança da conta e disponibilidade do modelo precisam da validação autorizada após cadastro da chave. MariaDB e produção não foram validados nesta alteração.
