<?php

declare(strict_types=1);

use Danny\SchemaFixer;

/** CLI-only coordinator; MySQL's client handles DELIMITER without a custom SQL parser. */
final class MigrationRunner
{
    public function __construct(private readonly PDO $pdo, private readonly string $root)
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('Migrações só podem ser executadas pela linha de comando.');
        }
    }

    public function migrate(): void
    {
        $base = $this->pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins'");
        if ((int) $base->fetchColumn() === 0) {
            throw new RuntimeException('Schema inicial ausente. Execute scripts/install.php em um banco vazio.');
        }
        $stmt = $this->pdo->prepare("SELECT GET_LOCK(CONCAT('lume:migrate:', LEFT(SHA2(DATABASE(), 256), 40)), 10)");
        $stmt->execute();
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Outra migração está em execução. Tente novamente depois.');
        }

        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(191) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $versions = ['001_legacy' => $this->root . '/src/SchemaFixer.php'];
            foreach (glob($this->root . '/database/migrations/*.sql') ?: [] as $path) {
                $version = pathinfo($path, PATHINFO_FILENAME);
                if (!preg_match('/^\d{3}_[a-z0-9_]+$/D', $version) || isset($versions[$version])) {
                    throw new RuntimeException('Nome de migração inválido ou duplicado: ' . basename($path));
                }
                $versions[$version] = $path;
            }
            ksort($versions, SORT_STRING);

            foreach ($versions as $version => $path) {
                $checksum = hash_file('sha256', $path);
                if ($checksum === false) {
                    throw new RuntimeException('Não foi possível ler a migração ' . $version);
                }
                $stmt = $this->pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = :version');
                $stmt->execute(['version' => $version]);
                $applied = $stmt->fetchColumn();
                if ($applied !== false) {
                    if (!hash_equals((string) $applied, $checksum)) {
                        throw new RuntimeException('A migração já aplicada foi modificada: ' . $version . '. Crie uma nova versão.');
                    }
                    fwrite(STDOUT, "Já aplicada: $version\n");
                    continue;
                }

                fwrite(STDOUT, "Aplicando: $version\n");
                if ($version === '001_legacy') {
                    SchemaFixer::run($this->pdo);
                } else {
                    $this->executeSqlFile($path);
                }
                $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (:version, :checksum)');
                $stmt->execute(['version' => $version, 'checksum' => $checksum]);
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK(CONCAT('lume:migrate:', LEFT(SHA2(DATABASE(), 256), 40)))");
        }
    }

    public function install(): void
    {
        $lock = $this->pdo->query("SELECT GET_LOCK(CONCAT('lume:migrate:', LEFT(SHA2(DATABASE(), 256), 40)), 10)");
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Outra instalação ou migração está em execução.');
        }
        try {
            $this->installEmptyDatabase();
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK(CONCAT('lume:migrate:', LEFT(SHA2(DATABASE(), 256), 40)))");
        }
    }

    private function installEmptyDatabase(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
        if ($count !== 0) {
            throw new RuntimeException('Instalação recusada: o banco contém tabelas. Para preservar os dados, use scripts/migrate.php.');
        }
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
            throw new RuntimeException('DB_DATABASE deve conter apenas letras, números e sublinhado.');
        }
        $sql = file_get_contents($this->root . '/database.sql');
        if ($sql === false) {
            throw new RuntimeException('database.sql não encontrado.');
        }
        $sql = str_replace(['CREATE DATABASE IF NOT EXISTS `danny`', 'USE `danny`;'], ['CREATE DATABASE IF NOT EXISTS `' . $database . '`', 'USE `' . $database . '`;'], $sql);
        $temporary = tempnam(sys_get_temp_dir(), 'lume_install_');
        if ($temporary === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário de instalação.');
        }
        try {
            if (file_put_contents($temporary, $sql) === false) {
                throw new RuntimeException('Falha ao preparar o schema inicial.');
            }
            $this->executeSqlFile($temporary);
        } finally {
            unlink($temporary);
        }
        $this->migrate();
    }

    private function executeSqlFile(string $path): void
    {
        $command = [
            (string) env('MYSQL_CLIENT', 'mysql'),
            '--protocol=TCP',
            '--default-character-set=utf8mb4',
            '--host=' . (string) env('DB_HOST', 'db'),
            '--port=' . (string) env('DB_PORT', '3306'),
            '--user=' . (string) env('DB_USERNAME', 'lume'),
            '--database=' . (string) env('DB_DATABASE', 'lume'),
        ];
        // Argument array avoids shell interpolation; the password never appears in argv.
        $environment = getenv();
        $environment['MYSQL_PWD'] = (string) env('DB_PASSWORD', '');
        $process = proc_open($command, [0 => ['file', $path, 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $this->root, $environment);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Falha ao aplicar ' . basename($path) . '. Verifique o cliente MySQL e o erro acima. DDL não possui rollback automático; corrija a causa antes de repetir.');
        }
    }
}
