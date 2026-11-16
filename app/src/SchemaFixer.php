<?php

declare(strict_types=1);

namespace Danny;

use LogicException;
use PDO;

/** Upgrade bridge for the historical schema. Called only by migration 001. */
final class SchemaFixer
{
    public static function run(PDO $pdo): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('Alterações de schema exigem scripts/migrate.php pela linha de comando.');
        }

        // Run upgrades with the app stopped: old triggers validate unrelated UPDATEs.
        $pdo->exec('DROP TRIGGER IF EXISTS trigger_validar_agendamento_insert');
        $pdo->exec('DROP TRIGGER IF EXISTS trigger_validar_agendamento_update');
        $pdo->exec('DROP TRIGGER IF EXISTS trigger_newborn');
        $pdo->exec('DROP TRIGGER IF EXISTS trigger_newborn_insert');
        $pdo->exec('DROP TRIGGER IF EXISTS trigger_newborn_update');
        self::ensureTables($pdo);
        self::ensureColumns($pdo);
        $pdo->exec("UPDATE agendamentos SET status = 'CANCELADO' WHERE status = 'PENDENTE_CANCELAMENTO'");
        $pdo->exec("ALTER TABLE agendamentos MODIFY status ENUM('PENDENTE','CONFIRMADO','RECUSADO','CANCELADO','CONCLUIDO') NOT NULL DEFAULT 'PENDENTE'");
        // Triggers and the completion procedure are installed by migration 003.
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function ensureTables(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_password_resets_token_hash (token_hash),
            KEY idx_password_resets_admin (admin_id),
            KEY idx_password_resets_expires (expires_at),
            CONSTRAINT fk_password_resets_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = [
            ['fotografos', 'telefone', 'VARCHAR(20) DEFAULT NULL'],
            ['fotografos', 'email', 'VARCHAR(150) DEFAULT NULL'],
            ['admins', 'telefone', "VARCHAR(20) NOT NULL DEFAULT 'Não informado'"],
            ['admins', 'status_admin', "VARCHAR(20) NOT NULL DEFAULT 'APROVADO' AFTER tipo"],
            ['agendamentos', 'valor', 'DECIMAL(10,2) DEFAULT NULL AFTER observacao'],
            ['agendamentos', 'duracao_minutos', 'INT NULL AFTER hora_inicio'],
        ];
        foreach ($columns as [$table, $column, $definition]) {
            if (self::columnExists($pdo, $table, $column)) {
                continue;
            }
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            if ($table === 'admins' && $column === 'status_admin') {
                $pdo->exec("UPDATE admins SET status_admin = 'PENDENTE' WHERE tipo = 'ADMIN' AND id <> 1");
            }
        }

        // Existing bookings retain their historical catalog duration as initial snapshot.
        $pdo->exec('UPDATE agendamentos a INNER JOIN tipos_servico ts ON ts.id = a.tipo_servico_id SET a.duracao_minutos = ts.duracao_minutos WHERE a.duracao_minutos IS NULL');
        $pdo->exec('ALTER TABLE agendamentos MODIFY duracao_minutos INT NOT NULL');
        $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'agendamentos' AND CONSTRAINT_NAME = 'chk_agendamentos_duracao'");
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE agendamentos ADD CONSTRAINT chk_agendamentos_duracao CHECK (duracao_minutos > 0)');
        }
    }
}
