<?php

declare(strict_types=1);

use Dotenv\Dotenv;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

return (static function (): void {
    $root = dirname(__DIR__);
    $envFile = $root . '/.env';

    if (!is_file($envFile)) {
        return;
    }

    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
})();
