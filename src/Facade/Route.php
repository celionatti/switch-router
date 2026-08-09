<?php

declare(strict_types=1);

namespace Switch\Router\Facade;

use Switch\Router\Route as RouteInstance;
use Switch\Router\RouteGroupBuilder;
use Switch\Router\Router;

/**
 * Static Laravel-style Route Facade for registering routes seamlessly.
 *
 * @method static RouteInstance get(string $path, mixed $handler)
 * @method static RouteInstance post(string $path, mixed $handler)
 * @method static RouteInstance put(string $path, mixed $handler)
 * @method static RouteInstance patch(string $path, mixed $handler)
 * @method static RouteInstance delete(string $path, mixed $handler)
 * @method static RouteInstance any(string $path, mixed $handler)
 * @method static RouteInstance redirect(string $from, string $to, int $status = 302)
 * @method static RouteInstance view(string $path, string $viewName, array $data = [])
 * @method static RouteInstance json(string $path, mixed $data)
 * @method static RouteInstance fallback(mixed $handler)
 * @method static void resource(string $name, string $controller)
 * @method static RouteGroupBuilder prefix(string $prefix)
 * @method static RouteGroupBuilder middleware(mixed ...$middleware)
 * @method static RouteGroupBuilder name(string $namePrefix)
 * @method static void group(array $attributes, callable $callback)
 * @method static string url(string $routeName, array $parameters = [])
 */
class Route
{
    private static ?Router $router = null;

    /**
     * Set the underlying Router instance.
     */
    public static function setRouter(Router $router): void
    {
        self::$router = $router;
    }

    /**
     * Get or lazily initialize the default Router singleton instance.
     */
    public static function getRouter(): Router
    {
        if (self::$router === null) {
            self::$router = new Router();
        }

        return self::$router;
    }

    /**
     * Forward static calls to the underlying Router singleton.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getRouter()->$method(...$arguments);
    }
}
