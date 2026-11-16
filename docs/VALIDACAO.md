# Validação da evolução Lume

## Executado

- Sintaxe dos arquivos PHP da aplicação e Composer estrito/requisitos.
- Duas verificações de precedência de ambiente: variáveis do processo prevalecem sobre .env com variables_order GPCS ou EGPCS.
- 27 verificações de segurança HTTP: cookies, CSRF, rotação e URL canônica.
- 106 verificações HTTP/banco de álbuns: uploads reais, SVG disfarçado, propriedade, visitantes, originais, miniaturas, ZIP, prazos, prorrogação, exclusão e aprovação do primeiro administrador.
- Seis cenários de agenda, incluindo confirmações simultâneas.
- Integração da fila com transporte falso: repetição, retries, expiração, locks, lote e simulação sem efeitos.
- Instalação, migração idempotente, recusa de banco não vazio/checksum alterado.
- Upgrade do schema original preservando agendamento histórico e duração.
- Conexão SELECT-only, sem DDL automático.

Ambiente: PHP 8.4.27 e MariaDB 11.8.6, com bases/arquivos descartáveis. Nenhum e-mail enviado a pessoas reais; SMTP testado com transporte falso.

## Limites

Não há Docker local. A automação GitHub Actions exercita PHP 8.3 e MySQL 8.4.8; o resultado de cada revisão está na aba Actions. O build completo da imagem PHP/Apache ainda precisa ser validado no Docker de destino. Os bloqueios .htaccess foram revisados, mas não executados em Apache. Após subir, verificar resposta403 para .env, SQL, storage, src, vendor e scripts.

Revisão visual completa de todos os painéis, contraste, zoom e dispositivos reais permanece pendente. Critérios em UX_ALBUNS.md; não se declara aprovação visual sem execução.
