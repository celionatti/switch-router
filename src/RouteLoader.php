<?php

declare(strict_types=1);

namespace Switch\Router;

/**
 * RouteLoader — Discovers and loads route definition files from a directory.
 *
 * Default files loaded automatically:
 *   - web.php   (no prefix, no extra middleware)
 *   - api.php   (prefix: /api, middleware: configurable)
 *
 * Users can register additional custom route files with group attributes.
 */
class RouteLoader
{
    private string $routesPath;
    private Router $router;

    /**
     * @var array<string, array{file: string, attributes: array}>
     */
    private array $files = [];

    /**
     * Default route file configurations.
     *
     * @var array<string, array>
     */
    private array $defaults = [
        'web' => [
            'prefix' => '',
            'middleware' => [],
            'as' => '',
        ],
        'api' => [
            'prefix' => '/api',
            'middleware' => [],
            'as' => 'api.',
        ],
    ];

    public function __construct(Router $router, string $routesPath)
    {
        $this->router = $router;
        $this->routesPath = rtrim($routesPath, '/\\');
    }

    /**
     * Register a route file to be loaded.
     *
     * @param string $filename  File name without extension (e.g., 'admin', 'console')
     * @param array  $attributes  Group attributes: prefix, middleware, as
     */
    public function register(string $filename, array $attributes = []): self
    {
        $this->files[$filename] = [
            'file' => $this->routesPath . DIRECTORY_SEPARATOR . $filename . '.php',
            'attributes' => $attributes,
        ];

        return $this;
    }

    /**
     * Set group attributes for a default route file (web or api).
     */
    public function configure(string $name, array $attributes): self
    {
        if (isset($this->defaults[$name])) {
            $this->defaults[$name] = array_merge($this->defaults[$name], $attributes);
        }

        return $this;
    }

    /**
     * Set middleware for the api route file.
     *
     * @param array<int, mixed> $middleware
     */
    public function apiMiddleware(array $middleware): self
    {
        $this->defaults['api']['middleware'] = $middleware;
        return $this;
    }

    /**
     * Set middleware for the web route file.
     *
     * @param array<int, mixed> $middleware
     */
    public function webMiddleware(array $middleware): self
    {
        $this->defaults['web']['middleware'] = $middleware;
        return $this;
    }

    /**
     * Load all registered and default route files.
     */
    public function load(): void
    {
        // Load default route files (web.php, api.php) if they exist
        foreach ($this->defaults as $name => $attributes) {
            $file = $this->routesPath . DIRECTORY_SEPARATOR . $name . '.php';
            if (file_exists($file)) {
                $this->loadFile($file, $attributes);
            }
        }

        // Load custom registered route files
        foreach ($this->files as $name => $entry) {
            // Skip if already loaded as a default
            if (isset($this->defaults[$name])) {
                continue;
            }

            if (file_exists($entry['file'])) {
                $this->loadFile($entry['file'], $entry['attributes']);
            }
        }
    }

    /**
     * Load a single route file within a group context.
     */
    private function loadFile(string $file, array $attributes): void
    {
        $hasAttributes = !empty($attributes['prefix'])
            || !empty($attributes['middleware'])
            || !empty($attributes['as']);

        $router = $this->router;

        if ($hasAttributes) {
            $router->group($attributes, function () use ($file, $router) {
                $this->requireFile($file, $router);
            });
        } else {
            $this->requireFile($file, $router);
        }
    }

    /**
     * Require a route file, passing $router into its scope.
     */
    private function requireFile(string $file, Router $router): void
    {
        // Set the Facade router so Route:: calls inside the file work
        if (class_exists(\Switch\Router\Facade\Route::class)) {
            \Switch\Router\Facade\Route::setRouter($router);
        }

        // $router is available inside the required file
        (static function (string $_file, Router $router): void {
            require $_file;
        })($file, $router);
    }

    /**
     * Get the routes directory path.
     */
    public function getRoutesPath(): string
    {
        return $this->routesPath;
    }

    /**
     * Get the underlying Router.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
}
