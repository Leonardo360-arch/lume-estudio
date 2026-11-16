# Arquitetura e decisões do Lume Estúdio

O projeto mantém PHP 8.3+, MySQL e páginas renderizadas no servidor. O novo módulo é um monólito organizado por responsabilidades. A operação atual não exige outra linguagem, microsserviços ou API pública.

| Componente | Responsabilidade |
|---|---|
| Páginas PHP | Autenticação, entrada HTTP e apresentação |
| AlbumService | Criar, enviar, publicar, prorrogar e excluir após retenção |
| AlbumPolicy + Clock | Proprietário, prazo e horário testáveis |
| AlbumRepository / PdoAlbumRepository | Persistência e transações |
| PhotoStorage / LocalPhotoStorage | Arquivos privados, validação e miniaturas |
| AlbumFactory | Composição das dependências |
| AlbumNotificationWorker | Fila durável, concorrência e repetição |
| NotificationSender | Contrato de envio, adapter SMTP e transporte falso |

## SOLID e padrões

| Princípio | Aplicação |
|---|---|
| Responsabilidade única | Prazo separado de HTML, SMTP e diretório físico |
| Aberto/fechado | Outro armazenamento implementa PhotoStorage sem mudar o caso de uso |
| Substituição | Relógio e transporte falsos exercitam os mesmos contratos em testes |
| Segregação de interfaces | Contratos específicos para relógio, arquivos e envio |
| Inversão de dependências | Serviço recebe dependências por injeção |

Padrões usados: Repository, Adapter, Strategy, Factory simples de composição e Transactional Outbox. A factory simples não é apresentada como GoF Factory Method. A agenda legada continua sendo refatorada incrementalmente; não houve conversão completa de todas as páginas para SOLID.

## Entrega privada

Rascunhos não iniciam prazo. Publicar libera 14 dias corridos, com avisos no momento da publicação e 7 e 2 dias antes da expiração. Datas persistidas em UTC, apresentadas em America/Sao_Paulo. Depois de vencer, originais, miniaturas e ZIP ficam bloqueados ao cliente. Há 7 dias adicionais para recuperação administrativa. Depois disso, administração e prorrogação também são bloqueadas, mesmo se a limpeza estiver atrasada. A rotina explícita exclui arquivos e mantém registro encerrado. URLs exigem sessão e autorização a cada acesso.

## Operação e limites

O armazenamento implementado é local privado, no volume Docker album_storage. R2/S3 é extensão prevista, ainda não implementada ou contratada. Faça backup do volume e do banco.

Upload: JPEG/PNG/WebP, 25 MB e 24 megapixels por arquivo, 20 arquivos e 100 MB por lote no navegador, 500 fotos e 5.000 MB por álbum. Limites de quantidade/tamanho configuráveis. ZIP temporário até 500 MB; álbuns maiores usam downloads individuais. ZIP assíncrono é backlog.

SMTP e banco não possuem transação distribuída. A entrega é pelo menos uma vez: falha depois da aceitação SMTP e antes do commit pode repetir um aviso. Versões antigas são ignoradas. São cinco tentativas com intervalo de 15 minutos; esgotamentos permanecem para revisão no banco. Painel de reenvio é backlog.

Revogação global de sessões, limitação persistente de tentativas e auditoria detalhada permanecem no backlog de endurecimento antes de produção pública.

## Agenda

Somente confirmados ocupam horários. Confirmação e remarcação exigem sete dias de antecedência. Valor/observação podem ser corrigidos sem invalidar reservas. Duração contratada é preservada no agendamento. Operações concorrentes bloqueiam a agenda do fotógrafo em transação.

Cancelamento pelo cliente continua restrito a pendentes. A antiga anotação acadêmica de 48 horas não foi adotada sem decisão de produto.
