<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<string, list<array{
     *     pattern:string,
     *     regex:string,
     *     params:list<string>,
     *     handler:mixed,
     *     middleware:list<class-string|\App\Core\Middleware>
     * }>>
     */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'PATCH'  => [],
        'DELETE' => [],
    ];

    /** @var list<class-string|\App\Core\Middleware> */
    private array $globalMiddleware = [];

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('DELETE', $path, $handler, $middleware);
    }

    /** Runs before every per-route middleware chain. */
    public function useGlobal(array $middleware): void
    {
        foreach ($middleware as $m) {
            $this->globalMiddleware[] = $m;
        }
    }

    public function register(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $method  = strtoupper($method);
        $pattern = '/' . trim($path, '/');

        [$regex, $params] = $this->compilePattern($pattern);

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];

        Logger::debug('Route registered', [
            'method' => $method,
            'pattern' => $pattern,
            'middleware_count' => count($middleware),
        ]);
    }

    public function dispatch(Request $request): never
    {
        $method = $request->method();
        $path   = $request->path();

        if (!isset($this->routes[$method])) {
            Response::error('الطريقة غير مسموح بها.', 405, 'method_not_allowed');
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['regex'], $path, $matches) === 1) {
                $params = [];
                foreach ($route['params'] as $name) {
                    $params[$name] = $matches[$name] ?? '';
                }
                $request->setRouteParams($params);

                Logger::info('Route matched', [
                    'method' => $method,
                    'path' => $path,
                    'pattern' => $route['pattern'],
                    'params' => $params,
                ]);

                $request = MiddlewareRunner::run($this->globalMiddleware, $request);
                $request = MiddlewareRunner::run($route['middleware'], $request);

                $this->invokeHandler($route['handler'], $request);
            }
        }

        Response::error('المسار غير موجود.', 404, 'not_found');
    }

    private function compilePattern(string $pattern): array
    {
        $params = [];
        $regex  = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $pattern
        );

        return ['#^' . $regex . '$#', $params];
    }

    private function invokeHandler(mixed $handler, Request $request): never
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = is_object($class) ? $class : new $class();
            $result = $controller->{$method}($request);
        } elseif (is_callable($handler)) {
            $result = $handler($request);
        } else {
            Logger::error('Invalid route handler', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            Response::error('معالج المسار غير صالح.', 500, 'server_error');
        }

        if ($result !== null) {
            Response::json($result);
        }

        Response::json(null);
    }
}
