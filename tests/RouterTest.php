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
}
