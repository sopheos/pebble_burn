<?php

use Pebble\Burn\CallableRoute;
use Pebble\Burn\ControllerRoute;
use Pebble\Burn\RouteException;
use Pebble\Burn\RouteInterface;
use Pebble\Burn\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function router(): Router
    {
        return new Router();
    }

    private function noop(): callable
    {
        return fn() => null;
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function testCallback()
    {
        $route = $this->router()->add('foo', '/index', fn() => 'index')->run('foo', '/index');

        self::assertInstanceOf(CallableRoute::class, $route);
        self::assertSame('foo', $route->method());
        self::assertSame('/index', $route->uri());
        self::assertSame('index', $route->execute());
    }

    public function testController()
    {
        $route = $this->router()->add('foo', '/index', Controller::class, 'index')->run('foo', '/index');

        self::assertInstanceOf(ControllerRoute::class, $route);
        self::assertSame([Controller::class, 'index'], $route->callback());
        self::assertSame('index', $route->execute());
    }

    /**
     * @dataProvider verbProvider
     */
    public function testVerbShortcuts(string $shortcut, string $expected)
    {
        $router = $this->router();
        $router->{$shortcut}('/', $this->noop());

        self::assertSame($expected, $router->run($expected, '/')->method());
    }

    public static function verbProvider(): array
    {
        return [
            'get' => ['get', 'GET'],
            'post' => ['post', 'POST'],
            'put' => ['put', 'PUT'],
            'patch' => ['patch', 'PATCH'],
            'delete' => ['delete', 'DELETE'],
            'options' => ['options', 'OPTIONS'],
            'cli' => ['cli', 'CLI'],
        ];
    }

    public function testGetpostRegistersBothVerbs()
    {
        $router = $this->router()->getpost('/login', $this->noop());

        self::assertSame('GET', $router->run('GET', '/login')->method());
        self::assertSame('POST', $router->run('POST', '/login')->method());
    }

    public function testSameMethodAndUriIsOverwritten()
    {
        $router = $this->router()
            ->get('/', fn() => 'first')
            ->get('/', fn() => 'second');

        self::assertSame('second', $router->run('GET', '/')->execute());
    }

    public function testGetpostRegistersTwoIndependentRoutes()
    {
        $router = $this->router()
            ->getpost('/login', fn() => 'shared')
            ->get('/login', fn() => 'replaced');

        self::assertSame('replaced', $router->run('GET', '/login')->execute());
        self::assertSame('shared', $router->run('POST', '/login')->execute());
    }

    public function testFalsyMethodArgumentBuildsACallableRoute()
    {
        $route = $this->router()->add('GET', '/x', Controller::class, '')->run('GET', '/x');

        self::assertInstanceOf(CallableRoute::class, $route);

        $this->expectException(RouteException::class);
        $route->execute();
    }

    public function testVerbsAreCaseSensitive()
    {
        $router = $this->router()->get('/', $this->noop());

        $this->expectException(RouteException::class);
        $router->run('get', '/');
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    public function testEmptyMethodAndUriDefaultToGetRoot()
    {
        $route = $this->router()->get('/', $this->noop())->run('', '');

        self::assertSame('GET', $route->method());
        self::assertSame('/', $route->uri());
    }

    public function testUnknownMethodThrows()
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('Route not found: DELETE /');

        $this->router()->get('/', $this->noop())->run('DELETE', '/');
    }

    public function testUnknownUriThrows()
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('Route not found: GET /nope');

        $this->router()->get('/', $this->noop())->run('GET', '/nope');
    }

    public function testRunOnlyResolvesAndDoesNotExecute()
    {
        $called = false;
        $route = $this->router()->get('/', function () use (&$called) {
            $called = true;
        })->run('GET', '/');

        self::assertFalse($called);
        $route->execute();
        self::assertTrue($called);
    }

    public function testExactMatchWinsOverAWildcardRoute()
    {
        $router = $this->router()
            ->get('/user/{id}', fn() => 'dynamic')
            ->get('/user/me', fn() => 'exact');

        self::assertSame('exact', $router->run('GET', '/user/me')->execute());
    }

    public function testFirstRegisteredWildcardRouteWins()
    {
        $router = $this->router()
            ->get('/{any}', fn() => 'first')
            ->get('/{id}', fn() => 'second');

        self::assertSame('first', $router->run('GET', '/42')->execute());
    }

    // -------------------------------------------------------------------------
    // Wildcards
    // -------------------------------------------------------------------------

    /**
     * @dataProvider wildcardProvider
     */
    public function testDefaultWildcards(string $pattern, string $uri, array $expected)
    {
        $route = $this->router()->get($pattern, $this->noop())->run('GET', $uri);

        self::assertSame($expected, $route->arguments());
    }

    public static function wildcardProvider(): array
    {
        return [
            'any' => ['/hello/{any}', '/hello/world', ['world']],
            'id' => ['/user/{id}', '/user/42', ['42']],
            'num negative' => ['/offset/{num}', '/offset/-5', ['-5']],
            'hex' => ['/color/{hex}', '/color/A1b2C3', ['A1b2C3']],
            'size' => ['/img/{size}', '/img/md', ['md']],
            'uuid' => [
                '/e/{uuid}',
                '/e/3f2504e0-4f89-11d3-9a0c-0305e82c3301',
                ['3f2504e0-4f89-11d3-9a0c-0305e82c3301'],
            ],
            'two wildcards' => ['/{any}/{id}', '/post/7', ['post', '7']],
        ];
    }

    /**
     * @dataProvider wildcardMismatchProvider
     */
    public function testWildcardsRejectNonMatchingValues(string $pattern, string $uri)
    {
        $this->expectException(RouteException::class);

        $this->router()->get($pattern, $this->noop())->run('GET', $uri);
    }

    public static function wildcardMismatchProvider(): array
    {
        return [
            'id is digits only' => ['/user/{id}', '/user/abc'],
            'any stops at slash' => ['/hello/{any}', '/hello/a/b'],
            'anchored at both ends' => ['/user/{id}', '/user/42/edit'],
            'query string not stripped' => ['/user/{id}', '/user/42?x=1'],
            'invalid date' => ['/d/{date}', '/d/2023-02-29'],
        ];
    }

    public function testCustomWildcard()
    {
        $route = $this->router()
            ->wildcard('slug', '([a-z-]+)')
            ->get('/p/{slug}', $this->noop())
            ->run('GET', '/p/hello-world');

        self::assertSame(['hello-world'], $route->arguments());
    }

    public function testWildcardsMergeOverDefaults()
    {
        $route = $this->router()
            ->wildcards(['size' => '(big)'])
            ->get('/img/{size}', $this->noop())
            ->run('GET', '/img/big');

        self::assertSame(['big'], $route->arguments());
    }

    public function testUnknownWildcardIsMatchedLiterally()
    {
        $router = $this->router()->get('/p/{nope}', $this->noop());

        self::assertSame([], $router->run('GET', '/p/{nope}')->arguments());

        $this->expectException(RouteException::class);
        $router->run('GET', '/p/anything');
    }

    public function testRawRegexInAUriIsNotInterpreted()
    {
        // The readme says URIs may be regexes; run() preg_quote()s them, so they never are.
        $router = $this->router()->get('/hello/([^/]+)', $this->noop());

        $this->expectException(RouteException::class);
        $router->run('GET', '/hello/world');
    }

    public function testAllWildcardIsSplitIntoSeveralArguments()
    {
        $route = $this->router()->get('/files/{all}', $this->noop())->run('GET', '/files/a/b/c/');

        self::assertSame(['a', 'b', 'c'], $route->arguments());
    }

    public function testEmptyCaptureYieldsOneEmptyStringArgument()
    {
        $route = $this->router()->get('/files/{all}', $this->noop())->run('GET', '/files/');

        self::assertSame([''], $route->arguments());
    }

    // -------------------------------------------------------------------------
    // Export & singleton
    // -------------------------------------------------------------------------

    public function testExportGroupsMethodsByUriAndSortsByUri()
    {
        $router = $this->router()
            ->get('/zebra', $this->noop())
            ->getpost('/alpha', $this->noop());

        self::assertSame([
            '/alpha' => ['GET', 'POST'],
            '/zebra' => ['GET'],
        ], $router->export());
    }

    public function testGetInstanceIsMemoized()
    {
        self::assertSame(Router::getInstance(), Router::getInstance());
        self::assertNotSame(Router::getInstance(), new Router());
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testDateWildcardPassesEveryNestedGroupAsAnArgument()
    {
        // BUG: the {date} regex contains nested capturing groups; each one becomes
        // an argument, and their number depends on the branch that matched.
        $router = $this->router()->get('/d/{date}', $this->noop());

        $leap = $router->run('GET', '/d/2024-02-29')->arguments();
        self::assertCount(17, $leap);
        self::assertSame('2024-02-29', $leap[0]);

        self::assertCount(28, $router->run('GET', '/d/2023-01-15')->arguments());
    }

    public function testWildcardNameWithRegexCharactersIsNeverSubstituted()
    {
        // BUG: the search key is '\{name\}' built without preg_quote(), so a name
        // containing '-' or '.' never matches the preg_quote()d URI.
        $router = $this->router()
            ->wildcard('my-id', '([0-9]+)')
            ->get('/m/{my-id}', $this->noop());

        $this->expectException(RouteException::class);
        $router->run('GET', '/m/5');
    }

    public function testRunReturnsTheSharedRouteInstance()
    {
        // BUG: routes are stored as objects and setArguments() mutates them, so a
        // second run() overwrites the arguments of a route obtained earlier.
        $router = $this->router()->get('/u/{id}', $this->noop());

        $first = $router->run('GET', '/u/1');
        $second = $router->run('GET', '/u/2');

        self::assertSame($first, $second);
        self::assertSame(['2'], $first->arguments());
    }

    public function testRouteIsAnInterfaceImplementation()
    {
        self::assertInstanceOf(
            RouteInterface::class,
            $this->router()->get('/', $this->noop())->run('GET', '/')
        );
    }
}
