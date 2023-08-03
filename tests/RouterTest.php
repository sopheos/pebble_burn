<?php

use Pebble\Burn\RouteInterface;
use Pebble\Burn\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testCallback()
    {
        $router = Router::create();
        $router->add('foo', '/index', function () {
            return 'index';
        });
        $route = $router->run('foo', '/index');

        self::assertIsObject($route);
        self::assertInstanceOf(RouteInterface::class, $route);
        self::assertSame('foo', $route->method());
        self::assertSame('/index', $route->uri());

        $res = $route->execute();
        self::assertSame('index', $res);
    }

    public function testController()
    {
        $router = Router::create();
        $router->add('foo', '/index', Controller::class, 'index');
        $route = $router->run('foo', '/index');

        self::assertIsObject($route);
        self::assertInstanceOf(RouteInterface::class, $route);
        self::assertSame('foo', $route->method());
        self::assertSame('/index', $route->uri());

        $res = $route->execute();
        self::assertSame('index', $res);
    }

    public function testArguments()
    {
        $router = Router::create();
        $router->add('foo', '/bar/{any}/{any}', function ($arg1, $arg2) {
            return $arg1 . $arg2;
        });
        $route = $router->run('foo', '/bar/arg1/arg2');

        self::assertIsObject($route);
        self::assertInstanceOf(RouteInterface::class, $route);

        $res = $route->execute();
        self::assertSame('arg1arg2', $res);
    }
}
