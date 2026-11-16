# Chatbot de agendamento pelo WhatsApp — proposta do próximo módulo

Status: especificação; integração ainda não implementada ou ativada. A versão atual continua operando pelo site e por e-mail.

## Objetivo

Permitir que o cliente converse com o Lume Estúdio no WhatsApp e envie uma solicitação para a mesma agenda usada pelo site. A primeira etapa preserva a confirmação administrativa: o bot não promete que uma solicitação pendente já reservou o horário.

## Fluxo proposto

1. Cliente inicia a conversa e escolhe solicitar ensaio, acompanhar solicitação ou falar com o estúdio.
2. Se necessário, vincula a conversa à sua conta do site por fluxo autenticado e com validade curta. Informar um nome, telefone ou e-mail não autoriza acesso a uma conta existente.
3. Escolhe serviço, fotógrafo e cenário compatível, quando exigido.
4. Consulta opções de datas e horários, considerando duração, antecedência, horário de atendimento e reservas confirmadas.
5. Confere um resumo e confirma expressamente o envio.
6. O servidor revalida os dados e grava uma única solicitação PENDENTE. O bot informa o protocolo e que aguarda confirmação.
7. A solicitação aparece no painel e na área do cliente. A aprovação administrativa poderá gerar um aviso no WhatsApp, respeitando a janela de atendimento e os modelos aplicáveis.

## Arquitetura incremental

PHP atende ao módulo; preserva a infraestrutura e reduz duplicação de regras. Antes de ligar o canal, extrair o caso de uso de solicitação para um BookingService compartilhado por site e bot, reutilizando os bloqueios e validações já testados no AgendamentoHelper.

| Componente | Papel |
|---|---|
| BookingService | Validação e gravação transacional da solicitação, independente do canal |
| AvailabilityService | Consultar opções; disponibilidade exibida não constitui reserva |
| WhatsAppGateway | Contrato de envio, adapter Meta Cloud API e transporte falso |
| ConversationState | Etapas do diálogo, expiração, reinício e transferência para atendimento |
| InboundWebhook | Validar origem/assinatura, registrar evento e responder rapidamente |
| Inbox/Outbox | Deduplicar eventos e processar respostas com tentativas controladas |
| AccountLinkService | Vínculo explícito entre identidade externa e conta autenticada |

Padrões previstos: State para o diálogo, Adapter para o provedor, Repository para persistência e Inbox/Outbox para entrega. O fluxo guiado resolve o agendamento inicial sem depender de um modelo generativo. Uma IA futura poderá interpretar linguagem livre, usando os mesmos serviços de negócio para executar ações.

## Segurança e integridade

- Validar assinatura do corpo original do webhook com segredo do aplicativo. O endpoint externo tem autenticação própria; não criar exceção genérica ao CSRF do site.
- Identificar o remetente pelo identificador externo estável disponibilizado pela plataforma. A documentação atual prevê BSUID e ausência do número de telefone em alguns eventos; telefone não deve ser a única chave.
- Deduplicar pelo identificador da mensagem e usar uma chave única no comando de agendamento. Uma repetição de webhook não cria nova solicitação.
- Processar mensagens de uma conversa em ordem, com transação/bloqueio para confirmação concorrente.
- Revalidar disponibilidade e regras no servidor antes de gravar e, novamente, na aprovação.
- Registrar a concordância para os avisos e disponibilizar atendimento humano.
- Credenciais ficam no ambiente do servidor; logs não devem registrar tokens nem conversas completas sem necessidade.
- O envio de link de álbum continua exigindo login e propriedade. Nenhum link do WhatsApp libera as fotos por si só.

## Ativação externa e custos

Será necessário configurar WhatsApp Business Platform/Cloud API, aplicativo e permissões Meta, número habilitado, credenciais e endpoint público HTTPS. A configuração Docker atual é local e não recebe webhooks da internet.

Mensagens proativas fora da janela aplicável dependem de modelos e políticas da plataforma. Custos dependem das regras vigentes, categoria, país e eventual provedor intermediário; não foi assumido custo zero nem contratado serviço. Conferir os preços no momento da ativação. A validação inicial pode usar simulador e transporte falso.

## Critérios de aceite

- O pedido feito no WhatsApp aparece uma única vez no mesmo painel e na conta correta.
- Dois canais não confirmam reservas conflitantes para o mesmo fotógrafo.
- Webhook repetido, inválido, fora de ordem ou remetente sem vínculo não altera outra conta.
- Nenhuma mensagem afirma confirmação quando o status é PENDENTE.
- Cancelar/reiniciar o diálogo não gera pedido acidental.
- Falha no provedor preserva o pedido e permite repetir o aviso sem criar nova reserva.
- Testes com transporte falso precedem testes com número oficial; aprovação de mensagens fora da janela é validada antes da ativação.

## Referências oficiais consultadas

- https://developers.facebook.com/documentation/business-messaging/whatsapp/overview
- https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/overview
- https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/create-webhook-endpoint
- https://whatsappbusiness.com/products/platform-pricing/
