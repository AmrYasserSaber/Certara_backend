<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;


final class Database
{
    private static ?Capsule $capsule = null;

    /**
     * Backwards-compatible accessor that returns a handle-like object.
     *
     * The current codebase mostly uses static calls, but some modules expect a
     * getInstance() entry point. Returning an instance keeps that contract
     * available without changing the existing public API surface.
     */
    public static function getInstance(): self
    {
        return new self();
    }

    public static function boot(array $config): void
    {
        if (self::$capsule !== null) {
            return;
        }

        Logger::info('Database booting', [
            'driver' => $config['driver'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
        ]);

        $capsule = new Capsule();
        $capsule->addConnection($config);
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;
    }

    public static function capsule(): Capsule
    {
        if (self::$capsule === null) {
            throw new \RuntimeException('Database not booted. Call Database::boot($config) first.');
        }
        return self::$capsule;
    }

    public static function connection(): \Illuminate\Database\Connection
    {
        return self::capsule()->getConnection();
    }

    public static function query(string $sql, array $bindings = []): array
    {
        return array_map(
            static fn ($row): array => (array) $row,
            self::connection()->select($sql, $bindings)
        );
    }

    public static function fetchOne(string $sql, array $bindings = []): ?array
    {
        $row = self::connection()->selectOne($sql, $bindings);
        return $row === null ? null : (array) $row;
    }

    public static function fetchAll(string $sql, array $bindings = []): array
    {
        return array_map(
            static fn ($row): array => (array) $row,
            self::connection()->select($sql, $bindings)
        );
    }

    public static function execute(string $sql, array $bindings = []): int
    {
        return self::connection()->affectingStatement($sql, $bindings);
    }

    public static function transaction(callable $callback): mixed
    {
        return self::connection()->transaction($callback);
    }
}
