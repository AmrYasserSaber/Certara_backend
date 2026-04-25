<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(mixed $data = null, int $status = 200, ?array $meta = null): never
    {
        self::send([
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => $meta,
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        string $code = 'bad_request',
        ?array $details = null
    ): never {
        Logger::warning('API error response', [
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        ]);

        self::send([
            'success' => false,
            'data'    => null,
            'error'   => [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta'    => null,
        ], $status);
    }

    public static function send(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        exit;
    }
}
