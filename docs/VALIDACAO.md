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

## CI com Docker e Apache

[Execução aprovada no GitHub Actions](https://github.com/Leonardo360-arch/lume-estudio/actions/runs/43854845690), revisão `c237a13c3607d3a81315830ca9adf8a1e616e874`:

- Job principal aprovado com PHP 8.3 e MySQL 8.4.8: ambiente, segurança, agenda, notificações e álbuns.
- Build real do Dockerfile PHP 8.3/Apache, incluindo GD, mbstring, Composer e os arquivos da aplicação.
- Stack real do Docker Compose com MySQL 8.4.8 e Mailpit v1.27.8; banco saudável antes da aplicação e portas publicadas apenas em localhost.
- Instalação do banco e repetição das migrações; criação de administrador sintético por stdin, sem senha em argumentos.
- Home e telas de login acessíveis; o teste exige HTTP 200 para as páginas. WebP servido com tipo `image/webp` e assinatura RIFF/WEBP válida.
- HTTP 403 confirmado no Apache para `.env.example`, `database.sql`, `src/helpers.php`, `scripts/install.php`, `composer.json`, `storage/smoke.txt`, `vendor/autoload.php`, `database/migrations/002_albums.sql` e `docker/php.ini`. A fixture em storage foi criada dentro do contêiner antes da consulta.
- Contêineres e volumes descartáveis removidos ao fim do job, inclusive quando há falha.

A configuração e as credenciais do CI são descartáveis. O job não publica o site nem utiliza dados de clientes.

## Limites

Não há Docker local; o build e a execução do Apache foram verificados no runner do GitHub Actions. Um servidor de destino com configuração diferente ainda exige sua própria verificação de instalação e acesso.

Revisão visual completa de todos os painéis, contraste, zoom e dispositivos reais permanece pendente. Critérios em UX_ALBUNS.md; não se declara aprovação visual sem execução.
