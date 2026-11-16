# Lume Estúdio — experiência dos álbuns privados

**Natureza:** especificação para implementação e validação. Este documento não comprova que as funcionalidades já existem.

## Objetivo e perfis

Permitir que o estúdio entregue fotografias por tempo limitado, com prazo compreensível e acesso exclusivo ao cliente vinculado ao álbum.

- **Administrador:** cria, organiza, publica, acompanha falhas de comunicação e prorroga o prazo enquanto as fotos ainda existem.
- **Cliente autenticado:** consulta apenas seus álbuns publicados, visualiza fotografias disponíveis e baixa cada foto.
- O vínculo usa o identificador da conta do cliente; nome e e-mail exibidos ajudam o administrador a confirmar a pessoa correta. Não localizar ou vincular clientes apenas pelo nome.

## Estados e regras de prazo

| Estado | Visibilidade para o cliente | Ações principais |
| --- | --- | --- |
| Rascunho | Não aparece | Administrador edita, envia fotos e publica. Não existe prazo de acesso nem lembrete ativo. |
| Disponível | Cliente vinculado vê álbum, fotos e downloads | Prazo começa no instante da publicação e termina 14 dias corridos depois. |
| Expirado, em recuperação | Cliente vê somente identificação do álbum, vencimento e orientação | Fotos, miniaturas e downloads ficam bloqueados. Administrador pode prorrogar durante os 7 dias seguintes ao vencimento. |
| Excluído | Se houver histórico, mostrar somente aviso, sem fotografias | Fotografias e arquivos derivados são removidos. Prorrogação não recupera arquivos já excluídos. |

Todos os horários apresentados usam **America/Sao_Paulo**, com data completa e hora. Armazenar os instantes de forma inequívoca e converter para apresentação. A expiração corresponde à publicação mais 14 dias corridos, preservando a hora local; não ocorre automaticamente à meia-noite.

- O acesso vale enquanto `agora < expiração`. No instante exato da expiração, já está bloqueado.
- A janela administrativa de recuperação vale enquanto `expiração <= agora < expiração + 7 dias corridos`.
- A partir de `expiração + 7 dias corridos`, o álbum está destinado à exclusão e não pode ser reaberto pela interface. Se a rotina de exclusão estiver atrasada, isso não devolve acesso nem amplia a recuperação.
- Após exclusão efetiva, manter apenas os registros mínimos necessários ao histórico definido pelo projeto, sem imagens ou URLs reutilizáveis.

**Exemplo:** publicação em 16/11/2026 às 10h00; acesso até 30/11/2026 às 10h00; lembretes em 23/11/2026 e 28/11/2026 às 10h00; exclusão prevista para 07/12/2026 às 10h00. Datas no horário de São Paulo.

## Fluxo administrativo

1. **Criar rascunho:** escolher cliente, informar título e confirmar nome/e-mail do destinatário. Mostrar “O prazo de 14 dias começa quando você publicar”.
2. **Enviar várias fotos:** selecionar múltiplos arquivos. Antes de iniciar, informar os formatos, tamanhos e limites realmente aceitos pelo servidor; não anunciar limites sem validação correspondente.
3. **Acompanhar envio:** listar arquivo, estado e resultado: “Na fila”, “Enviando”, “Concluído” ou “Falhou”. Mostrar contagem, como “8 de 12 fotos enviadas”. Percentual somente quando houver medição real; permitir repetir apenas as falhas sem perder as fotos concluídas nem duplicá-las.
4. **Revisar:** exibir miniaturas autorizadas ao administrador, quantidade de fotos, cliente e título. Arquivos rejeitados devem ter motivo acionável. Não publicar automaticamente ao terminar o upload.
5. **Publicar:** exigir ao menos uma foto válida e confirmação explícita. Se houver falhas, o administrador precisa resolvê-las ou removê-las da seleção antes de publicar. A confirmação informa o destinatário, a quantidade final e a regra dos 14 dias; após concluir, mostrar data/hora reais de publicação e expiração.
6. **Acompanhar comunicação:** separar “Álbum publicado” de “E-mail enviado”, “Envio pendente” e “Falha no envio”. Não tratar entrega ou leitura como comprovadas só porque o serviço aceitou o envio.
7. **Prorrogar:** em álbum disponível ou dentro da recuperação, escolher nova expiração futura, confirmar e registrar autor, instante, prazo anterior e novo prazo. Exibir a nova data ao cliente e recalcular lembretes/exclusão. Preservar a publicação original no histórico; não reiniciar silenciosamente um período de 14 dias.

Publicação e envio de e-mail são operações independentes: uma falha de comunicação não remove fotos, não desfaz a publicação e não altera o prazo. Repetir o envio usa o mesmo álbum.

## Fluxo do cliente

1. Entrar na conta e acessar **Meus álbuns**. Não exibir álbuns de outras contas nem rascunhos.
2. Em cada álbum disponível, mostrar título, quantidade de fotos e **“Disponível até DD/MM/AAAA às HHhMM — horário de São Paulo”**. Contagem de dias pode complementar, sem substituir a data exata.
3. Ao abrir, apresentar fotos em grade, visualização ampliada e botão **“Baixar foto”** com nome acessível. Mostrar estados de carregamento, falha e opção de tentar novamente. Oferecer download do álbum completo somente se houver implementação correspondente.
4. Perto do vencimento, destacar o prazo e **“Baixe suas fotos antes desse horário”**. Não prometer renovação automática.
5. Se vencer enquanto a página estiver aberta, interromper novas requisições de imagens/downloads e atualizar o aviso. A autorização do servidor continua obrigatória mesmo que a tela ainda não tenha sido atualizada.
6. No álbum vencido, substituir a grade por **“O prazo deste álbum terminou em DD/MM/AAAA às HHhMM. Entre em contato com o estúdio para verificar a possibilidade de prorrogação.”** Dentro da recuperação, explicar que os arquivos podem ser excluídos na data/hora indicada; não apresentar essa janela como acesso adicional do cliente.
7. Após exclusão, informar **“As fotos deste álbum foram excluídas após o prazo de disponibilidade”**, sem botão de baixar ou promessa de recuperação.

## Lembretes e falhas de e-mail

**“7 dias antes” significa exatamente expiração menos 7 dias corridos; “2 dias antes”, expiração menos 2 dias corridos**, no mesmo horário local. Não são prazos contados após o primeiro acesso ou o envio do e-mail.

- Agendar um lembrete de cada tipo por álbum e versão do prazo. Se a rotina executar depois do instante previsto, enviar somente se o álbum continuar disponível; não enviar mensagens vencidas ou os dois lembretes juntos como recuperação automática do atraso.
- Cada mensagem informa título do álbum, expiração exata e link para entrar na conta e abrir o álbum. Não anexar fotos nem conceder acesso anônimo pelo e-mail.
- Prorrogação invalida lembretes pendentes e a exclusão associados ao prazo anterior. Recalcular para a nova expiração e registrar a versão para evitar duplicação.
- Se um novo marco já passou quando o prazo for prorrogado, não disparar esse lembrete retroativamente; comunicar o novo prazo e programar apenas os marcos futuros.
- Registrar evento, destinatário, tentativa, instante, resultado e erro técnico sem expor dados desnecessários ao cliente. Fazer novas tentativas controladas e permitir reenvio administrativo com feedback.
- A mensagem de erro ao administrador deve orientar: **“O álbum está disponível, mas o e-mail não foi enviado. Confira o endereço e tente novamente.”**

## Acesso restrito e URLs

- Exigir autenticação, vínculo com a conta e estado disponível em toda requisição de álbum, fotografia, miniatura ou download. Conferir apenas a página HTML não é suficiente.
- Conhecer ou trocar o ID na URL não pode revelar dados de outro cliente; usar uma resposta genérica de indisponibilidade, sem confirmar título, destinatário ou existência do álbum.
- Arquivos e derivados não ficam em caminhos públicos que dispensem autorização. Evitar cache compartilhado; URLs ou tokens temporários, se utilizados, nunca prolongam acesso além da expiração e precisam respeitar revogação/estado atual.
- Expiração bloqueia novos acessos e downloads, inclusive usando um link copiado anteriormente. Uma foto já baixada para o dispositivo do cliente não pode ser retirada remotamente; a interface não deve sugerir essa capacidade.
- Revogar acesso pela troca de vínculo deve valer também para URLs previamente conhecidas. Revalidar identidade e álbum no servidor.

## Até cinco melhorias prioritárias de UX

1. **Prazo consistente:** reutilizar o mesmo formato de data/hora na lista, detalhe, publicação, prorrogação e e-mails, mantendo estado separado do status de comunicação.
2. **Retomada sem retrabalho:** preservar dados do rascunho, aceitar reenvio somente dos arquivos que falharam e anunciar contagem final verificável.
3. **Ações compreensíveis:** usar “Publicar álbum”, “Baixar foto” e “Prorrogar prazo”; explicar efeitos antes de ações que publiquem ou alterem disponibilidade.
4. **Navegação simples:** incluir “Meus álbuns” no painel do cliente e diferenciar visualmente disponível, expirado e excluído sem depender apenas de cores.
5. **Acessibilidade de fotos e feedback:** controles com nomes acessíveis, rótulos persistentes, mensagens junto ao contexto e atualizações anunciadas de forma moderada às tecnologias assistivas.

## Critérios de aceite

### Fluxo e prazos

- Rascunho salvo não é exibido ao cliente nem tem expiração ativa. Publicar com zero fotos válidas é impedido com explicação.
- Lote com sucessos e falhas conserva os sucessos, identifica falhas individualmente e pode ser retomado sem duplicação ou publicação involuntária.
- Publicação define a expiração em 14 dias corridos; o exemplo deste documento é reproduzido com relógio controlado. Validar um instante antes, o instante exato e um instante após expirar.
- Lembretes ocorrem em D−7 e D−2 com referência à expiração. Tentativas/reenvios não geram eventos duplicados nem mudam o prazo.
- E-mail com falha preserva álbum e fotos, mostra estado correto ao administrador e permite nova tentativa.
- Conta A não acessa álbum, foto, miniatura ou download da conta B, mesmo conhecendo os IDs e URLs. Pedido sem login não entrega arquivos.
- Após vencer, URLs anteriores não entregam conteúdo. Na recuperação, só o administrador pode prorrogar. No limite de 7 dias após vencer, a prorrogação é recusada e a exclusão segue agendada.
- Prorrogação bem-sucedida atualiza tela, autorização, lembretes e exclusão para o novo prazo; tarefas com a versão antiga não podem excluir o álbum ou enviar lembretes desatualizados.

### Layout em 360, 390, 768 e 1440 px

| Largura | Verificação obrigatória |
| --- | --- |
| 360 px | Lista, grade, prazo completo, erros e ações cabem sem rolagem horizontal da página. Botões têm área de toque confortável de pelo menos 44 × 44 px. Modal de foto e confirmação permanece utilizável. |
| 390 px | Repetir o fluxo completo de envio/revisão/publicação e visualização/download; nomes de arquivo longos quebram ou truncam com acesso ao nome completo, sem cobrir ações. |
| 768 px | Grade aproveita espaço sem forçar colunas mínimas maiores que o contêiner. Navegação e formulários mantêm ordem de leitura, rótulos e mensagens associados. |
| 1440 px | Conteúdo tem largura legível, fotos não distorcem e ações principais ficam próximas ao contexto. A indicação de prazo permanece visível na página do álbum. |

Em todas as larguras: verificar estados vazio, carregando, erro, envio parcial, disponível, expirado e excluído. Testar ampliação de 200% sem perda de ações ou informação e sem sobreposição de elementos fixos.

### Teclado e tecnologia assistiva

- Percorrer criação, seleção de arquivos, publicação, lista de álbuns, ampliação e download somente com Tab, Shift+Tab, Enter, Espaço e Escape quando aplicável; não exigir arrastar arquivos.
- Foco sempre visível e ordem previsível. Modais recebem foco ao abrir, contêm sua navegação enquanto abertos, fecham com Escape e devolvem foco ao controle de origem.
- Ações e fotos acionáveis têm nome acessível; campos usam rótulos associados. Estados não dependem só de cor e erros indicam como corrigir.
- Atualizações de envio e confirmação são anunciadas com uma região de status, sem anunciar cada byte. Erro importante é identificável e não desaparece antes da leitura.
- Nova aba, download e indisponibilidade são informados pelo texto do controle ou contexto. O aviso de expiração não bloqueia a navegação nem prende o foco.

## Limites desta revisão

Recomendações derivadas da revisão estática anterior e das regras autorizadas para o módulo. Não houve teste visual, teste com usuários ou verificação da implementação dos álbuns. A validação final exige executar os critérios acima no sistema real.
