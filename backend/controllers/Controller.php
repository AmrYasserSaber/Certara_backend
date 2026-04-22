<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

abstract class Controller
{
    protected function ok(mixed $data = null, ?array $meta = null): never
    {
        Response::json($data, 200, $meta);
    }

    protected function created(mixed $data = null): never
    {
        Response::json($data, 201);
    }

    protected function fail(string $message, int $status = 400, string $code = 'bad_request', ?array $details = null): never
    {
        Response::error($message, $status, $code, $details);
    }

    protected function validate(Request $request, array $rules): array
    {
        $data   = $request->all();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $required = str_contains($rule, 'required');
            $value    = $data[$field] ?? null;

            if ($required && ($value === null || $value === '')) {
                $errors[$field] = "{$field} is required";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (str_contains($rule, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "{$field} must be a valid email";
            }

            if (preg_match('/min:(\d+)/', $rule, $m) === 1 && strlen((string) $value) < (int) $m[1]) {
                $errors[$field] = "{$field} must be at least {$m[1]} characters";
            }
        }

        if ($errors !== []) {
            $this->fail('Validation failed.', 422, 'validation_error', $errors);
        }

        return $data;
    }
}
