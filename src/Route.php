<?php

declare(strict_types=1);

namespace Switch\Router;

class Route
{
    /**
     * @var array<int, string>
     */
    private array $methods;
    private string $path;
    private mixed $handler;
    private ?string $name = null;

    /**
     * @var array<int, mixed>
     */
    private array $middleware = [];

    /**
     * @var array<string, string>
     */
    private array $wheres = [];

    /**
     * @param string|array<int, string> $methods
     */
    public function __construct(string|array $methods, string $path, mixed $handler)
    {
        $this->methods = array_map('strtoupper', (array) $methods);
        $this->path = '/' . trim($path, '/');
        $this->handler = $handler;
    }

    /**
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function middleware(mixed ...$middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function where(string|array $nameOrArray, ?string $regex = null): self
    {
        if (is_array($nameOrArray)) {
            foreach ($nameOrArray as $param => $pattern) {
                $this->wheres[$param] = $pattern;
            }
        } elseif ($regex !== null) {
            $this->wheres[$nameOrArray] = $regex;
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * Compile route path into Regex for matching parameters.
     */
    public function compileRegex(): string
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function ($matches) {
                $param = $matches[1];
                $customRegex = $matches[2] ?? ($this->wheres[$param] ?? '[^/]+');
                return "(?<{$param}>{$customRegex})";
            },
            $this->path
        );

        return '#^' . $pattern . '$#u';
    }
}
