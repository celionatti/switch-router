<?php

declare(strict_types=1);

namespace Switch\Router;

class RouteGroupBuilder
{
    private string $prefix = '';

    /** @var array<int, mixed> */
    private array $middleware = [];

    private string $namePrefix = '';

    public function __construct(
        private readonly Router $router
    ) {
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = '/' . trim($prefix, '/');
        return $this;
    }

    public function middleware(mixed ...$middleware): self
    {
        $middleware = is_array($middleware[0] ?? null) ? $middleware[0] : $middleware;
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function name(string $namePrefix): self
    {
        $this->namePrefix = $namePrefix;
        return $this;
    }

    public function group(callable $callback): void
    {
        $attributes = [
            'prefix' => $this->prefix,
            'middleware' => $this->middleware,
            'as' => $this->namePrefix,
        ];

        $this->router->group($attributes, $callback);
    }
}
