<?php

declare(strict_types=1);

namespace App\Core;

final class MiddlewareRunner
{
    /**
     * @param list<class-string|\App\Core\Middleware> $middleware
     */
    public static function run(array $middleware, Request $request): Request
    {
        foreach ($middleware as $m) {
            if (is_string($m)) {
                if (!class_exists($m, true)) {
                    Logger::error('Invalid middleware configuration', [
                        'middleware_type' => 'missing_class',
                        'middleware' => $m,
                        'path' => $request->path(),
                        'method' => $request->method(),
                    ]);
                    Response::error('Invalid middleware configuration.', 500, 'server_error');
                }

                $reflection = new \ReflectionClass($m);
                if (!$reflection->isInstantiable()) {
                    Logger::error('Invalid middleware configuration', [
                        'middleware_type' => 'non_instantiable_class',
                        'middleware' => $m,
                        'path' => $request->path(),
                        'method' => $request->method(),
                    ]);
                    Response::error('Invalid middleware configuration.', 500, 'server_error');
                }

                $instance = $reflection->newInstance();
            } else {
                $instance = $m;
            }

            if (!$instance instanceof Middleware) {
                Logger::error('Invalid middleware configuration', [
                    'middleware_type' => is_object($instance) ? get_class($instance) : gettype($instance),
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);
                Response::error('Invalid middleware configuration.', 500, 'server_error');
            }

            Logger::debug('Running middleware', [
                'middleware' => get_class($instance),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            $request = $instance->handle($request);
        }

        return $request;
    }
}
