# Lume Estúdio

Sistema demonstrativo de fotografia: portfólio, agenda, área do cliente, administração e entrega de álbuns privados. Marca, símbolo, fotografias e dados iniciais são fictícios.

## Funcionalidades

- Home responsiva com fotografias WebP e identidade Lume.
- Cadastro, login, perfil, recuperação de senha e gestão de ensaios.
- Agenda com identidade por e-mail/ID, duração contratada e proteção contra confirmações concorrentes.
- Álbuns privados: rascunho, upload múltiplo com progresso, publicação e prorrogação.
- Fotos/miniaturas protegidas por sessão e proprietário; download individual e ZIP até 500 MB.
- Acesso por 14 dias após publicar, com mais 7 dias de recuperação administrativa.
- Aviso de publicação e lembretes 7 e 2 dias antes do vencimento, processados em rotina CLI.
- Sessões, CSRF e migrações explícitas, sem alterações de schema durante navegação.

Stack: PHP 8.3+, MySQL 8.4, JavaScript, CSS, Composer, PHPMailer e Docker Compose. O módulo de álbuns aplica SOLID, Repository, Adapter, Strategy, Factory simples e Transactional Outbox. [Arquitetura](docs/ARQUITETURA.md).

## Executar com Docker

Instale Docker Desktop com Compose. Java não é necessário.

```bash
git clone https://github.com/Leonardo360-arch/lume-estudio.git
cd lume-estudio/app
```

Copie `.env.example` para `.env`: PowerShell `Copy-Item .env.example .env`; Linux/macOS `cp .env.example .env`. Defina senhas próprias em DB_PASSWORD e DB_ROOT_PASSWORD.

```bash
docker compose up -d --build
docker compose exec app php scripts/install.php
docker compose exec app php scripts/create-admin.php
```

O último comando solicita nome, e-mail, senha de pelo menos 12 caracteres e confirmação. Não há contas/senhas administrativas predefinidas. O primeiro administrador explicitamente aprovado gerencia solicitações de outros administradores, independente do ID numérico.

| Acesso | Endereço |
|---|---|
| Site | http://localhost:8080 |
| Administração | http://localhost:8080/login.php |
| Cliente | http://localhost:8080/login-cliente.php |
| Entregas | http://localhost:8080/album-admin.php |
| E-mails de teste | http://localhost:8025 |

O Compose publica serviços somente em localhost e é destinado ao desenvolvimento. Os volumes db_data e album_storage preservam banco e fotos: faça backup dos dois. `docker compose down -v` apaga os volumes.

Para atualização: faça backup, reconstrua a imagem e rode `docker compose exec app php scripts/migrate.php` com o sistema em manutenção. Não use install.php para atualizar: ele recusa banco com tabelas. Migrações têm checksum e não devem ser editadas depois de aplicadas.

## Testar uma entrega

1. Crie uma conta de cliente com dados fictícios e senha própria.
2. Como administrador, abra Entregas de fotos e crie um rascunho para esse cliente.
3. Envie as fotos, confira falhas e revise o resultado.
4. Publique: o prazo de 14 dias começa nesse momento.
5. Entre como cliente e teste Meus álbuns, download individual e ZIP.
6. Confira que outra conta não acessa as fotos, mesmo conhecendo os endereços.

## Avisos e limpeza

Simular sem enviar e-mails nem excluir arquivos:

```bash
docker compose exec app php scripts/album-jobs.php
```

Executar fila e limpeza dos álbuns fora da retenção:

```bash
docker compose exec app php scripts/album-jobs.php --execute
```

Configure o agendador do servidor para executar a cada 10 minutos. Exemplo de cron no host, com o caminho real:

```cron
*/10 * * * * cd /caminho/lume-estudio/app && docker compose exec -T app php scripts/album-jobs.php --execute
```

Sem o agendador, a expiração de acesso funciona, mas e-mails e remoção física não são processados automaticamente. Localmente as mensagens ficam no Mailpit. SMTP real e APP_URL canônica HTTPS devem ser configurados em produção.

Armazenamento atual: local privado. R2/S3, favoritas e ZIP assíncrono estão no backlog. Nenhum serviço de nuvem contratado. Limites e características de repetição dos e-mails: [Arquitetura](docs/ARQUITETURA.md).

## Desenvolvimento e testes

Sem Docker: PHP 8.3+, Composer, cliente MySQL e extensões do composer.json, incluindo ZIP. Em app/, execute composer install, configure .env, crie banco vazio e rode php scripts/install.php. Use Apache com AllowOverride All e mod_rewrite. O servidor embutido PHP ignora .htaccess e não deve expor a pasta na internet.

```bash
cd app
composer validate --strict
php tests/security.php
```

Integrações exigem banco isolado e migrado com nome lume_test_*. Defina TEST_DB_DSN, TEST_DB_USER e TEST_DB_PASSWORD para backend_integration.php e notifications_integration.php. albums_http.php cria/remove exclusivamente lume_test_albums, usando ALBUM_TEST_DB_HOST, ALBUM_TEST_DB_PORT, ALBUM_TEST_DB_USER e ALBUM_TEST_DB_PASSWORD. Use somente dados descartáveis. A configuração de CI contém os comandos completos.

[Resultados e limites](docs/VALIDACAO.md) · [Sugestões dos especialistas](docs/REVISAO_ESPECIALISTAS.md) · [UX dos álbuns](docs/UX_ALBUNS.md).

## Origem e créditos

Evolução de [Portf-lio-PI-2026](https://github.com/Leonardo360-arch/Portf-lio-PI-2026), referência a106e9eace4f62d87db8f6df9757c1e5f1959c4b. O original permanece intacto e conserva o histórico. Este repositório recebe o código revisado, sem importar todo o histórico Git nem os arquivos antigos. [Origem](docs/ORIGEM.md).

Equipe acadêmica original: Michael Pierre Nintz de Freitas (backend/PO), Leonardo Marson Coral (banco de dados), Denis Diego Brandão da Cunha (backend/frontend), Eduardo Ferreira Lopes e Gabriel Neves (documentação/casos de uso). A evolução foi assistida por IA, sob as decisões do responsável pelo projeto.
