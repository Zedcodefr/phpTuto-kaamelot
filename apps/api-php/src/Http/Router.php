<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Routeur minimaliste : associe (méthode HTTP, motif d'URL) à un "handler" (callable).
 * Les paramètres {xxx} des motifs sont extraits par expression régulière.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(Request $request): JsonResponse
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if ($route['method'] !== $request->method) {
                continue;
            }

            // On ne garde que les groupes nommés : ['id' => '42']
            $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, ...$params);
        }

        throw $pathMatched
            ? new HttpException(405, "Method $request->method not allowed on $request->path")
            : new HttpException(404, "No route for $request->method $request->path");
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        // '/api/tasks/{id}' devient '#^/api/tasks/(?<id>\d+)$#'
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?<$1>\d+)', $pattern) . '$#';

        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }
}
