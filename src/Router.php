<?php

declare(strict_types=1);

namespace Switch\Router;

use Switch\Router\Exception\MethodNotAllowedException;
use Switch\Router\Exception\RouteNotFoundException;

class Router
{
    /**
     * @var array<int, Route>
     */
    private array $routes = [];

    /**
     * @var array<string, Route>
     */
    private array $namedRoutes = [];

    private string $groupPrefix = '';
    
    /**
     * @var array<int, mixed>
     */
    private array $groupMiddleware = [];

    public function get(string $path, mixed $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function any(string $path, mixed $handler): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], $path, $handler);
    }

    /**
     * @param string|array<int, string> $methods
     */
    public function addRoute(string|array $methods, string $path, mixed $handler): Route
    {
        $fullPath = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        $route = new Route($methods, $fullPath, $handler);

        if (!empty($this->groupMiddleware)) {
            $route->middleware(...$this->groupMiddleware);
        }

        $this->routes[] = $route;
        return $route;
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        }

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Match an HTTP method and URI path to a RouteMatch.
     *
     * @throws RouteNotFoundException
     * @throws MethodNotAllowedException
     */
    public function match(string $method, string $path): RouteMatch
    {
        $method = strtoupper($method);
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        $allowedMethods = [];
        $matchedPath = false;

        foreach ($this->routes as $route) {
            $regex = $route->compileRegex();

            if (preg_match($regex, $path, $matches)) {
                $matchedPath = true;

                if (in_array($method, $route->getMethods(), true)) {
                    $parameters = array_filter(
                        $matches,
                        fn($key) => is_string($key),
                        ARRAY_FILTER_USE_KEY
                    );

                    return new RouteMatch($route, $parameters);
                }

                foreach ($route->getMethods() as $m) {
                    $allowedMethods[$m] = true;
                }
            }
        }

        if ($matchedPath && !empty($allowedMethods)) {
            throw new MethodNotAllowedException(array_keys($allowedMethods));
        }

        throw new RouteNotFoundException("No route found for path '{$path}'");
    }

    /**
     * @return array<int, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
