<?php

declare(strict_types=1);

namespace App\Support;

class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): self
    {
        $this->routes[$method][$path] = $handler;

        return $this;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function dispatch(string $method, string $path): array
    {
        $handler = $this->routes[$method][$path] ?? null;
        $params = [];

        if ($handler === null) {
            foreach ($this->routes[$method] ?? [] as $routePath => $routeHandler) {
                if (!str_contains($routePath, '{')) {
                    continue;
                }
                $paramCount = 0;
                $skeleton = preg_replace_callback(
                    '#\{[a-zA-Z][a-zA-Z0-9]*\}#',
                    static function () use (&$paramCount) {
                        return '§§' . ($paramCount++) . '§§';
                    },
                    $routePath
                );
                $regexFragment = preg_quote($skeleton, '#');
                preg_match_all('#\\{([a-zA-Z][a-zA-Z0-9]*)\\}#', $routePath, $names);
                $paramNames = is_array($names[1] ?? null) ? $names[1] : [];
                for ($pi = 0; $pi < $paramCount; $pi++) {
                    $paramName = $paramNames[$pi] ?? '';
                    $segment = str_ends_with(strtolower($paramName), 'id') || $paramName === 'id'
                        ? '(\d+)'
                        : '([^/]+)';
                    $regexFragment = str_replace(preg_quote('§§' . $pi . '§§', '#'), $segment, $regexFragment);
                }
                $pattern = '#^' . $regexFragment . '$#';
                if (preg_match($pattern, $path, $m)) {
                    for ($i = 0; $i < count($paramNames); $i++) {
                        $raw = $m[$i + 1] ?? '';
                        $paramNames[$i] = $paramNames[$i] ?? '';
                        if (str_ends_with(strtolower($paramNames[$i]), 'id') || $paramNames[$i] === 'id') {
                            $params[$paramNames[$i]] = (int) $raw;
                        } else {
                            $params[$paramNames[$i]] = $raw;
                        }
                    }
                    $handler = $routeHandler;
                    break;
                }
            }
        }

        if ($handler === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        $result = $handler(...array_values($params));
        if (is_array($result) && isset($result['status'], $result['headers'], $result['body'])) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::html((string) $result);
    }
}
