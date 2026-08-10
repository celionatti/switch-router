<?php

declare(strict_types=1);

namespace Switch\Router\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Router\Router;
use Switch\Router\RouteLoader;

class RouteLoaderTest extends TestCase
{
    private string $routesDir;

    protected function setUp(): void
    {
        $this->routesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'switch_routes_test_' . uniqid();
        mkdir($this->routesDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp route files
        $files = glob($this->routesDir . '/*.php');
        if ($files) {
            foreach ($files as $f) {
                unlink($f);
            }
        }
        if (is_dir($this->routesDir)) {
            rmdir($this->routesDir);
        }
    }

    public function testLoadWebRouteFile(): void
    {
        file_put_contents($this->routesDir . '/web.php', '<?php
            $router->get("/", fn() => "Home");
            $router->get("/about", fn() => "About");
        ');

        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->load();

        $match = $router->match('GET', '/');
        $this->assertEquals('Home', ($match->getHandler())());

        $match = $router->match('GET', '/about');
        $this->assertEquals('About', ($match->getHandler())());
    }

    public function testLoadApiRouteFileWithPrefix(): void
    {
        file_put_contents($this->routesDir . '/api.php', '<?php
            $router->get("/users", fn() => "API Users");
            $router->get("/posts", fn() => "API Posts");
        ');

        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->load();

        $match = $router->match('GET', '/api/users');
        $this->assertEquals('API Users', ($match->getHandler())());

        $match = $router->match('GET', '/api/posts');
        $this->assertEquals('API Posts', ($match->getHandler())());
    }

    public function testApiRouteNamePrefix(): void
    {
        file_put_contents($this->routesDir . '/api.php', '<?php
            $router->get("/status", fn() => "ok")->name("status");
        ');

        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->load();

        $match = $router->match('GET', '/api/status');
        $this->assertEquals('api.status', $match->getRoute()->getName());
    }

    public function testCustomRouteFileRegistration(): void
    {
        file_put_contents($this->routesDir . '/admin.php', '<?php
            $router->get("/dashboard", fn() => "Admin Dashboard")->name("dashboard");
        ');

        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->register('admin', [
            'prefix' => '/admin',
            'middleware' => ['auth'],
            'as' => 'admin.',
        ]);
        $loader->load();

        $match = $router->match('GET', '/admin/dashboard');
        $this->assertEquals('Admin Dashboard', ($match->getHandler())());
        $this->assertEquals('admin.dashboard', $match->getRoute()->getName());
        $this->assertEquals(['auth'], $match->getMiddleware());
    }

    public function testConfigureApiMiddleware(): void
    {
        file_put_contents($this->routesDir . '/api.php', '<?php
            $router->get("/data", fn() => "data");
        ');

        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->apiMiddleware(['throttle', 'auth:api']);
        $loader->load();

        $match = $router->match('GET', '/api/data');
        $this->assertEquals(['throttle', 'auth:api'], $match->getMiddleware());
    }

    public function testWebAndApiTogetherWithFacade(): void
    {
        file_put_contents($this->routesDir . '/web.php', '<?php
            use Switch\Router\Facade\Route;
            Route::get("/home", fn() => "Web Home")->name("home");
        ');
        file_put_contents($this->routesDir . '/api.php', '<?php
            use Switch\Router\Facade\Route;
            Route::get("/info", fn() => "API Info")->name("info");
        ');

        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->load();

        $match = $router->match('GET', '/home');
        $this->assertEquals('Web Home', ($match->getHandler())());
        $this->assertEquals('home', $match->getRoute()->getName());

        $match = $router->match('GET', '/api/info');
        $this->assertEquals('API Info', ($match->getHandler())());
        $this->assertEquals('api.info', $match->getRoute()->getName());
    }

    public function testMissingRouteFileIsSkipped(): void
    {
        // No files created — should not throw
        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);
        $loader->load();

        $this->assertCount(0, $router->getRoutes());
    }

    public function testGetRoutesPathAndRouter(): void
    {
        $router = new Router();
        $loader = new RouteLoader($router, $this->routesDir);

        $this->assertEquals($this->routesDir, $loader->getRoutesPath());
        $this->assertSame($router, $loader->getRouter());
    }
}
