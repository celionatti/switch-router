<?php

declare(strict_types=1);

namespace Switch\Router;

class RouteMatch
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        private readonly Route $route,
        private readonly array $parameters = []
    ) {
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getHandler(): mixed
    {
        return $this->route->getHandler();
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $name, ?string $default = null): ?string
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * @return array<int, mixed>
     */
    public function getMiddleware(): array
    {
        return $this->route->getMiddleware();
    }
}
