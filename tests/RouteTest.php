<?php

use Pebble\Burn\CallableRoute;
use Pebble\Burn\ControllerRoute;
use Pebble\Burn\RouteException;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    // -------------------------------------------------------------------------
    // CallableRoute
    // -------------------------------------------------------------------------

    public function testCallableRouteSpreadsArguments()
    {
        $route = (new CallableRoute('GET', '/', fn($a, $b) => $a . $b))->setArguments(['x', 'y']);

        self::assertSame('xy', $route->execute());
    }

    public function testSetArgumentsReplacesAndIsFluent()
    {
        $route = new CallableRoute('GET', '/', fn() => null);

        self::assertSame($route, $route->setArguments(['a']));
        $route->setArguments(['b']);
        self::assertSame(['b'], $route->arguments());
    }

    public function testNonCallableCallbackThrows()
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('GET /x is not callable');

        (new CallableRoute('GET', '/x', 'NoSuchFunction'))->execute();
    }

    // -------------------------------------------------------------------------
    // ControllerRoute
    // -------------------------------------------------------------------------

    public function testControllerRouteInstantiatesTheClass()
    {
        self::assertSame('index', (new ControllerRoute('GET', '/', [Controller::class, 'index']))->execute());
    }

    public function testControllerRouteWithoutMethodThrows()
    {
        $this->expectException(RouteException::class);

        (new ControllerRoute('GET', '/', [Controller::class]))->execute();
    }

    public function testUnknownControllerClassRaisesAnError()
    {
        $this->expectException(Error::class);

        (new ControllerRoute('GET', '/', ['NoSuchController', 'index']))->execute();
    }

    public function testControllerWithRequiredConstructorArgumentsFailsOutsideRouteException()
    {
        // No container: `new $classname` is called without arguments.
        $this->expectException(ArgumentCountError::class);

        (new ControllerRoute('GET', '/', [ControllerWithDependency::class, 'index']))->execute();
    }
}
