<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
    ];

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $minLevel = strtolower((string) env('LOG_LEVEL', 'debug'));
        $threshold = self::LEVELS[$minLevel] ?? self::LEVELS['debug'];
        $current = self::LEVELS[$level] ?? self::LEVELS['debug'];
        if ($current < $threshold) {
            return;
        }

        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('c'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($dir . '/app.log', $line, FILE_APPEND);

        $toStderr = filter_var(env('LOG_TO_STDERR', true), FILTER_VALIDATE_BOOL);
        if ($toStderr) {
            error_log(rtrim($line));
        }
    }
}
