<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Logger;
use App\Helpers\InputValidator;

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
        $data = $request->all();
        $result = InputValidator::validate($data, $rules);

        if ($result['errors'] !== []) {
            Logger::warning('Validation failed', [
                'path' => $request->path(),
                'method' => $request->method(),
                'errors' => $result['errors'],
            ]);
            $this->fail('فشل التحقق.', 422, 'validation_error', $result['errors']);
        }

        return $result['data'];
    }
}
