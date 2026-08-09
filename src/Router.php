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

    private string $groupNamePrefix = '';

    private ?Route $currentRoute = null;

    private mixed $fallbackHandler = null;

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
     * Helper to create a redirect route.
     */
    public function redirect(string $from, string $to, int $status = 302): Route
    {
        return $this->get($from, function () use ($to, $status) {
            if (class_exists(\Switch\Http\Response::class)) {
                return new \Switch\Http\Response($status, ['Location' => $to]);
            }
            header("Location: {$to}", true, $status);
            exit;
        });
    }

    /**
     * Helper to render a view directly.
     */
    public function view(string $path, string $viewName, array $data = []): Route
    {
        return $this->get($path, function () use ($viewName, $data) {
            if (class_exists(\Switch\View\View::class)) {
                return \Switch\View\View::render($viewName, $data);
            }
            return "View: {$viewName}";
        });
    }

    /**
     * Helper to return JSON directly.
     */
    public function json(string $path, mixed $data): Route
    {
        return $this->get($path, function () use ($data) {
            $content = is_callable($data) ? $data() : $data;
            if (class_exists(\Switch\Http\Response::class) && class_exists(\Switch\Http\Stream::class)) {
                return new \Switch\Http\Response(
                    200,
                    ['Content-Type' => 'application/json; charset=UTF-8'],
                    \Switch\Http\Stream::create(json_encode($content))
                );
            }
            header('Content-Type: application/json; charset=UTF-8');
            return json_encode($content);
        });
    }

    /**
     * Register a fallback 404 handler route.
     */
    public function fallback(mixed $handler): Route
    {
        $route = $this->any('{any:.*}', $handler);
        $this->fallbackHandler = $handler;
        return $route;
    }

    /**
     * Register RESTful resource routes for a controller.
     */
    public function resource(string $name, string $controller): void
    {
        $baseName = trim($name, '/');
        $singular = rtrim($baseName, 's');

        $this->get("{$baseName}", [$controller, 'index'])->name("{$baseName}.index");
        $this->get("{$baseName}/create", [$controller, 'create'])->name("{$baseName}.create");
        $this->post("{$baseName}", [$controller, 'store'])->name("{$baseName}.store");
        $this->get("{$baseName}/{{$singular}}", [$controller, 'show'])->name("{$baseName}.show");
        $this->get("{$baseName}/{{$singular}}/edit", [$controller, 'edit'])->name("{$baseName}.edit");
        $this->put("{$baseName}/{{$singular}}", [$controller, 'update'])->name("{$baseName}.update");
        $this->patch("{$baseName}/{{$singular}}", [$controller, 'update']);
        $this->delete("{$baseName}/{{$singular}}", [$controller, 'destroy'])->name("{$baseName}.destroy");
    }

    /**
     * Fluent Group entrypoint: Route::prefix('/api')
     */
    public function prefix(string $prefix): RouteGroupBuilder
    {
        return (new RouteGroupBuilder($this))->prefix($prefix);
    }

    /**
     * Fluent Group entrypoint: Route::middleware('auth')
     */
    public function middleware(mixed ...$middleware): RouteGroupBuilder
    {
        return (new RouteGroupBuilder($this))->middleware(...$middleware);
    }

    /**
     * Fluent Group entrypoint: Route::name('api.')
     */
    public function name(string $namePrefix): RouteGroupBuilder
    {
        return (new RouteGroupBuilder($this))->name($namePrefix);
    }

    /**
     * Add a route to the collection.
     *
     * @param string|array<int, string> $methods
     */
    public function addRoute(string|array $methods, string $path, mixed $handler): Route
    {
        $fullPath = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        $route = new Route($methods, $fullPath, $handler);

        if (!empty($this->groupMiddleware)) {
            $route->middleware(...$this->groupMiddleware);
        }

        if ($this->groupNamePrefix !== '') {
            $route->setNamePrefix($this->groupNamePrefix);
        }

        $this->routes[] = $route;
        return $route;
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousNamePrefix = $this->groupNamePrefix;

        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        }

        if (isset($attributes['as'])) {
            $this->groupNamePrefix .= $attributes['as'];
        }

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupNamePrefix = $previousNamePrefix;
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

                    $this->currentRoute = $route;
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
     * Generate a URL for a named route.
     */
    public function url(string $routeName, array $parameters = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $routeName) {
                $path = $route->getPath();
                foreach ($parameters as $param => $val) {
                    $path = preg_replace('/\{' . preg_quote($param, '/') . '(?::[^}]+)?\}/', (string)$val, $path);
                }
                return $path;
            }
        }

        throw new \InvalidArgumentException("Route with name '{$routeName}' not found.");
    }

    public function current(): ?Route
    {
        return $this->currentRoute;
    }

    public function currentRouteName(): ?string
    {
        return $this->currentRoute?->getName();
    }

    /**
     * @return array<int, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
