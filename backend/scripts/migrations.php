<?php

declare(strict_types=1);

use App\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/env.php';

$command = $argv[1] ?? 'status';
$migrationsPath = dirname(__DIR__, 2) . '/database/migrations';

if (!is_dir($migrationsPath)) {
    fwrite(STDERR, "Migrations directory not found: {$migrationsPath}\n");
    exit(2);
}

try {
    $dbConfig = require dirname(__DIR__) . '/config/database.php';
    $default = $dbConfig['default'] ?? 'mysql';
    Database::boot($dbConfig['connections'][$default]);
    ensureMigrationsTable();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(2);
}

match ($command) {
    'status' => statusCommand($migrationsPath),
    'up' => upCommand($migrationsPath),
    default => invalidCommand($command),
};

function ensureMigrationsTable(): void
{
    Database::execute(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(255) NOT NULL PRIMARY KEY,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function orderedMigrationFiles(string $migrationsPath): array
{
    $files = glob($migrationsPath . '/*.sql') ?: [];
    sort($files, SORT_NATURAL);
    return $files;
}

function appliedMigrationNames(): array
{
    $rows = Database::fetchAll('SELECT migration FROM schema_migrations');
    return array_column($rows, 'migration');
}

function pendingMigrationFiles(string $migrationsPath): array
{
    $applied = array_flip(appliedMigrationNames());
    $pending = [];

    foreach (orderedMigrationFiles($migrationsPath) as $file) {
        $name = basename($file);
        if (!isset($applied[$name])) {
            $pending[] = $file;
        }
    }

    return $pending;
}

function statusCommand(string $migrationsPath): never
{
    $all = orderedMigrationFiles($migrationsPath);
    $pending = pendingMigrationFiles($migrationsPath);
    $appliedCount = count($all) - count($pending);

    fwrite(STDOUT, "Migration status\n");
    fwrite(STDOUT, "----------------\n");
    fwrite(STDOUT, 'Total files: ' . count($all) . "\n");
    fwrite(STDOUT, "Applied: {$appliedCount}\n");
    fwrite(STDOUT, 'Pending: ' . count($pending) . "\n\n");

    if ($pending === []) {
        fwrite(STDOUT, "Database is up to date.\n");
        exit(0);
    }

    fwrite(STDOUT, "Pending migrations:\n");
    foreach ($pending as $file) {
        fwrite(STDOUT, '- ' . basename($file) . "\n");
    }
    exit(1);
}

function upCommand(string $migrationsPath): never
{
    $pending = pendingMigrationFiles($migrationsPath);
    if ($pending === []) {
        fwrite(STDOUT, "No pending migrations. Database is up to date.\n");
        exit(0);
    }

    foreach ($pending as $file) {
        $name = basename($file);
        $sql = trim((string) file_get_contents($file));

        if ($sql === '') {
            fwrite(STDOUT, "Skipping empty migration: {$name}\n");
            Database::execute('INSERT INTO schema_migrations (migration) VALUES (?)', [$name]);
            continue;
        }

        fwrite(STDOUT, "Applying {$name}...\n");

        try {
            Database::connection()->unprepared($sql);
            Database::connection()->insert('INSERT INTO schema_migrations (migration) VALUES (?)', [$name]);
            fwrite(STDOUT, "Applied {$name}\n");
        } catch (Throwable $e) {
            $message = $e->getMessage();
            fwrite(STDERR, "Failed on {$name}: {$message}\n");

            if (str_contains($message, 'SQLSTATE[42S02]')) {
                fwrite(STDERR, "\n");
                fwrite(STDERR, "Your database looks older than the baseline schema.\n");
                fwrite(STDERR, "Import the latest baseline first, then re-run migrations:\n");
                fwrite(STDERR, "  mysql -u <user> -p <db_name> < ../database/schema.sql\n");
                fwrite(STDERR, "  composer run-script migrations:up\n");
            }
            exit(1);
        }
    }

    fwrite(STDOUT, "All pending migrations applied successfully.\n");
    exit(0);
}

function invalidCommand(string $command): never
{
    fwrite(STDERR, "Unknown command: {$command}\n");
    fwrite(STDERR, "Usage: php scripts/migrations.php [status|up]\n");
    exit(2);
}
