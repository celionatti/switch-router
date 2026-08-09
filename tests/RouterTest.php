<?php

declare(strict_types=1);

namespace Switch\Router\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Router\Exception\MethodNotAllowedException;
use Switch\Router\Exception\RouteNotFoundException;
use Switch\Router\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testBasicRouteMatching(): void
    {
        $this->router->get('/users', fn() => 'users_list');
        $match = $this->router->match('GET', '/users');

        $this->assertEquals('/users', $match->getRoute()->getPath());
        $this->assertIsCallable($match->getHandler());
        $this->assertEquals('users_list', ($match->getHandler())());
    }

    public function testDynamicRouteParameters(): void
    {
        $this->router->get('/users/{id}', fn() => 'user_detail');
        $match = $this->router->match('GET', '/users/42');

        $this->assertEquals('42', $match->getParameter('id'));
    }

    public function testRouteRegexConstraints(): void
    {
        $this->router->get('/posts/{id}', fn() => 'post_detail')
            ->where('id', '[0-9]+');

        $match = $this->router->match('GET', '/posts/100');
        $this->assertEquals('100', $match->getParameter('id'));

        $this->expectException(RouteNotFoundException::class);
        $this->router->match('GET', '/posts/abc');
    }

    public function testRouteGroupsAndMiddleware(): void
    {
        $this->router->group([
            'prefix' => '/api/v1',
            'middleware' => ['auth', 'cors']
        ], function (Router $router) {
            $router->get('/status', fn() => 'ok')->middleware('rate_limit');
        });

        $match = $this->router->match('GET', '/api/v1/status');
        $this->assertEquals('/api/v1/status', $match->getRoute()->getPath());
        $this->assertEquals(['auth', 'cors', 'rate_limit'], $match->getMiddleware());
    }

    public function testMethodNotAllowedException(): void
    {
        $this->router->post('/submit', fn() => 'done');

        try {
            $this->router->match('GET', '/submit');
            $this->fail('Expected MethodNotAllowedException was not thrown');
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(['POST'], $e->getAllowedMethods());
        }
    }

    public function testRouteNotFoundException(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->router->match('GET', '/non-existent-path');
    }

    // --- New Route Feature Tests ---

    public function testNamedRoutesAndUrlGeneration(): void
    {
        $this->router->get('/users/{id}', fn() => 'user_detail')->name('users.show');
        $url = $this->router->url('users.show', ['id' => 42]);
        $this->assertEquals('/users/42', $url);
    }

    public function testUrlGenerationThrowsForUnknownRoute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('nonexistent');
    }

    public function testGroupNamePrefix(): void
    {
        $this->router->group(['as' => 'admin.', 'prefix' => '/admin'], function (Router $r) {
            $r->get('/dashboard', fn() => 'dashboard')->name('dashboard');
        });

        $match = $this->router->match('GET', '/admin/dashboard');
        $this->assertEquals('admin.dashboard', $match->getRoute()->getName());
        $url = $this->router->url('admin.dashboard');
        $this->assertEquals('/admin/dashboard', $url);
    }

    public function testFluentGroupBuilder(): void
    {
        $this->router->prefix('/api/v2')->middleware('auth', 'cors')->name('api.')->group(function (Router $r) {
            $r->get('/users', fn() => 'users_list')->name('users.index');
        });

        $match = $this->router->match('GET', '/api/v2/users');
        $this->assertEquals('api.users.index', $match->getRoute()->getName());
        $this->assertEquals(['auth', 'cors'], $match->getMiddleware());
    }

    public function testResourceRoutes(): void
    {
        $this->router->resource('/products', 'App\\Controllers\\ProductController');

        // Test index
        $match = $this->router->match('GET', '/products');
        $this->assertEquals('products.index', $match->getRoute()->getName());

        // Test show
        $match = $this->router->match('GET', '/products/42');
        $this->assertEquals('42', $match->getParameter('product'));

        // Test create
        $match = $this->router->match('GET', '/products/create');
        $this->assertEquals('products.create', $match->getRoute()->getName());

        // Test store
        $match = $this->router->match('POST', '/products');
        $this->assertEquals('products.store', $match->getRoute()->getName());

        // Test edit
        $match = $this->router->match('GET', '/products/42/edit');
        $this->assertEquals('products.edit', $match->getRoute()->getName());

        // Test update (PUT)
        $match = $this->router->match('PUT', '/products/42');
        $this->assertEquals('products.update', $match->getRoute()->getName());

        // Test update (PATCH)
        $match = $this->router->match('PATCH', '/products/42');
        $this->assertNotNull($match);

        // Test destroy
        $match = $this->router->match('DELETE', '/products/42');
        $this->assertEquals('products.destroy', $match->getRoute()->getName());
    }

    public function testResourceRouteUrlGeneration(): void
    {
        $this->router->resource('/products', 'App\\Controllers\\ProductController');

        $this->assertEquals('/products', $this->router->url('products.index'));
        $this->assertEquals('/products/create', $this->router->url('products.create'));
        $this->assertEquals('/products/5', $this->router->url('products.show', ['product' => 5]));
        $this->assertEquals('/products/5/edit', $this->router->url('products.edit', ['product' => 5]));
    }

    public function testRedirectRoute(): void
    {
        $this->router->redirect('/old-page', '/new-page', 301);
        $match = $this->router->match('GET', '/old-page');
        $this->assertIsCallable($match->getHandler());
    }

    public function testViewRoute(): void
    {
        $this->router->view('/about', 'pages.about', ['title' => 'About Us']);
        $match = $this->router->match('GET', '/about');
        $this->assertIsCallable($match->getHandler());
    }

    public function testJsonRoute(): void
    {
        $this->router->json('/api/status', ['status' => 'ok']);
        $match = $this->router->match('GET', '/api/status');
        $this->assertIsCallable($match->getHandler());
    }

    public function testAnyRouteMatchesMultipleMethods(): void
    {
        $this->router->any('/wildcard', fn() => 'any');

        $this->assertNotNull($this->router->match('GET', '/wildcard'));
        $this->assertNotNull($this->router->match('POST', '/wildcard'));
        $this->assertNotNull($this->router->match('PUT', '/wildcard'));
        $this->assertNotNull($this->router->match('DELETE', '/wildcard'));
    }

    public function testCurrentRoute(): void
    {
        $this->router->get('/dashboard', fn() => 'dash')->name('dashboard');
        $this->assertNull($this->router->current());
        $this->assertNull($this->router->currentRouteName());

        $this->router->match('GET', '/dashboard');

        $this->assertNotNull($this->router->current());
        $this->assertEquals('dashboard', $this->router->currentRouteName());
    }

    public function testWhereNumberConstraint(): void
    {
        $this->router->get('/items/{id}', fn() => 'item')->whereNumber('id');
        $match = $this->router->match('GET', '/items/99');
        $this->assertEquals('99', $match->getParameter('id'));

        $this->expectException(RouteNotFoundException::class);
        $this->router->match('GET', '/items/abc');
    }

    public function testWhereAlphaConstraint(): void
    {
        $this->router->get('/categories/{slug}', fn() => 'cat')->whereAlpha('slug');
        $match = $this->router->match('GET', '/categories/electronics');
        $this->assertEquals('electronics', $match->getParameter('slug'));

        $this->expectException(RouteNotFoundException::class);
        $this->router->match('GET', '/categories/123');
    }

    public function testWhereUuidConstraint(): void
    {
        $this->router->get('/orders/{uuid}', fn() => 'order')->whereUuid('uuid');
        $match = $this->router->match('GET', '/orders/550e8400-e29b-41d4-a716-446655440000');
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $match->getParameter('uuid'));

        $this->expectException(RouteNotFoundException::class);
        $this->router->match('GET', '/orders/not-a-uuid');
    }

    public function testNestedGroups(): void
    {
        $this->router->group(['prefix' => '/api', 'as' => 'api.'], function (Router $r) {
            $r->group(['prefix' => '/v1', 'as' => 'v1.', 'middleware' => ['auth']], function (Router $r) {
                $r->get('/users', fn() => 'users')->name('users.index');
            });
        });

        $match = $this->router->match('GET', '/api/v1/users');
        $this->assertEquals('api.v1.users.index', $match->getRoute()->getName());
        $this->assertEquals(['auth'], $match->getMiddleware());
    }

    public function testMultipleRouteMiddleware(): void
    {
        $this->router->get('/admin', fn() => 'admin')
            ->middleware('auth', 'admin')
            ->middleware('log');

        $match = $this->router->match('GET', '/admin');
        $this->assertEquals(['auth', 'admin', 'log'], $match->getMiddleware());
    }

    public function testControllerArrayHandler(): void
    {
        $this->router->get('/home', ['App\\Controllers\\HomeController', 'index']);
        $match = $this->router->match('GET', '/home');
        $handler = $match->getHandler();

        $this->assertIsArray($handler);
        $this->assertEquals('App\\Controllers\\HomeController', $handler[0]);
        $this->assertEquals('index', $handler[1]);
    }

    public function testControllerAtStringHandler(): void
    {
        $this->router->get('/contact', 'App\\Controllers\\ContactController@show');
        $match = $this->router->match('GET', '/contact');
        $handler = $match->getHandler();

        $this->assertIsString($handler);
        $this->assertStringContainsString('@', $handler);
    }

    // --- Facade Tests ---

    public function testRouteFacade(): void
    {
        $facade = \Switch\Router\Facade\Route::class;

        // Ensure getRouter returns a Router instance
        $router = $facade::getRouter();
        $this->assertInstanceOf(Router::class, $router);

        // Register a route via Facade
        $facade::get('/facade-test', fn() => 'facade_works');
        $match = $router->match('GET', '/facade-test');
        $this->assertEquals('facade_works', ($match->getHandler())());
    }

    public function testRouteFacadeSetRouter(): void
    {
        $facade = \Switch\Router\Facade\Route::class;
        $custom = new Router();
        $facade::setRouter($custom);

        $facade::get('/custom', fn() => 'custom_router');
        $match = $custom->match('GET', '/custom');
        $this->assertEquals('custom_router', ($match->getHandler())());

        // Reset for other tests
        $facade::setRouter(new Router());
    }

    protected function tearDown(): void
    {
    }
}
