---
name: pebble-burn
description: How to correctly register and dispatch HTTP/CLI routes and set up the application services singleton using the sopheos/pebble_burn PHP library (namespace Pebble\Burn — classes Router, CallableRoute, ControllerRoute, RouteAbstract, RouteInterface, RouteException, and the abstract Services base class). Use this whenever the project's composer.json requires sopheos/pebble_burn, code imports from Pebble\Burn\*, or you're asked to add/change a route, an endpoint's URL, a URL parameter, a front controller, a CLI command entrypoint, a 404 path, or an app-wide services/config/environment object in a PHP project that has this library available — even if the request is phrased generically like "add a page at /users/:id", "wire up this controller" or "where do I read the config" without naming the library. Also check this before writing a raw preg_match dispatch loop, a $_SERVER['REQUEST_URI'] switch, or a hand-rolled service locator in such a project, since this library replaces those and has non-obvious and in places broken behavior (placeholders are {name} not regexes, {all} expands into several arguments, {date} passes a variable number of junk arguments, wildcard names with '-' or '.' never match, run() returns a shared mutable route and does not execute it, controllers are built with no constructor arguments) that hand-rolled code would miss.
---

# pebble-burn

`sopheos/pebble_burn` is a tiny, framework-agnostic PHP 8.1+ request router plus an abstract `Services` base class. You register `method + URI pattern -> handler` pairs, then resolve one incoming `method + URI` into a route object that you invoke yourself. `Services` is a per-subclass singleton that holds config, an environment name and `start()`/`stop()` hooks. The library does **not** read superglobals, emit headers, provide middleware or route groups, or give you a DI container.

Namespace: `Pebble\Burn\*`. Source lives in `vendor/sopheos/pebble_burn/src/`. Read it directly when you need an exact method signature; this skill focuses on *how the pieces fit together* and the behavior that isn't obvious from the method names.

## Orientation

- `Router` is the registry. Build it with `new Router()` or use the shared `Router::getInstance()`. Register routes with the verb shortcuts, then call `run($method, $uri)` once.
- `run()` returns a `RouteInterface` and **resolves only**. You call `->execute()` yourself.
- `CallableRoute` is what `add()` builds when you pass a closure or callable.
- `ControllerRoute` is what `add()` builds when you pass a class name **and** a method name. `execute()` does `new $classname`, then calls the method.
- `RouteException` is thrown when nothing matches (your 404 hook) and when a handler is not callable.
- `Services` is an abstract base for the app's service hub. `AppServices::getInstance()` gives one instance per subclass. It provides `setConfig()`/`config()`, `setEnv()`/`env()`/`isProd()`/`isDev()`/`isTest()`, `path()`, and `start()`/`stop()` hooks (`stop()` runs from `__destruct`).

For a full method cheat sheet and the wildcard table, see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md`. Read it before debugging a route that "should match".

## Core recipes

### Front controller

```php
use Pebble\Burn\Router;
use Pebble\Burn\RouteException;

$router = Router::getInstance();

$router->get('/', HomeController::class, 'index');
$router->get('/user/{id}', UserController::class, 'show');
$router->post('/user', UserController::class, 'store');

try {
    $uri = parse_url('http://dummy' . ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    echo $router->run($_SERVER['REQUEST_METHOD'] ?? 'CLI', $uri)->execute();
} catch (RouteException) {
    http_response_code(404);
}
```

Pass the **path only** to `run()`. Patterns are anchored at both ends, so a query string makes the route miss.

### Registering a handler

The fourth argument decides the route class:

```php
// Closure or any callable -> CallableRoute
$router->get('/ping', fn() => 'pong');
$router->get('/ping', [$serviceInstance, 'method']);   // already-built object: fine

// Class name + method name -> ControllerRoute (instantiated on execute)
$router->get('/user/{id}', UserController::class, 'show');
```

`ControllerRoute` calls `new $classname` with **no arguments**. If a controller needs dependencies, build it yourself and register `[$instance, 'method']` as a callable, or have it pull them from your `Services` singleton.

### URL parameters

Placeholders are `{name}`, where `name` is a key in the wildcard table. They are **not** regexes:

```php
$router->get('/user/{id}', UserController::class, 'show');   // /user/42 -> show('42')
$router->get('/{any}/{id}', PostController::class, 'show');  // /post/7  -> show('post', '7')

$router->wildcard('slug', '([a-z0-9-]+)');                   // custom: exactly ONE capturing group
$router->get('/p/{slug}', PostController::class, 'show');
```

Captured values arrive as **strings**, spread positionally in pattern order. Name custom wildcards with letters, digits and `_` only (a `-` or `.` in the name silently breaks them). Use **one capturing group** and no nested groups; use `(?:…)` inside if you need grouping.

**Do not use `{date}`.** Its regex has nested capturing groups, so the handler receives 17 to 28 arguments. Register your own instead:

```php
$router->wildcard('ymd', '([0-9]{4}-[0-9]{2}-[0-9]{2})');
```

### Catch-all segments

`{all}` matches across `/`, but every capture is then exploded on `/`:

```php
$router->get('/files/{all}', FileController::class, 'serve');
// GET /files/a/b/c -> serve('a', 'b', 'c')
public function serve(string ...$segments) { $path = implode('/', $segments); }
```

### CLI entrypoint

```php
$router->cli('/import/{any}', ImportController::class, 'run');
$router->run('CLI', '/' . implode('/', array_slice($argv, 1)))->execute();
```

### Services singleton

```php
use Pebble\Burn\Services;

class App extends Services
{
    public function start() { /* open connections */ }
    public function stop()  { /* close them; called from __destruct */ }
}

App::getInstance()
    ->setEnv(Services::ENV_PROD)          // 'production' | 'testing' | 'developpement'
    ->setConfig(require 'config.php')     // replaces the whole array
    ->start();                            // not called automatically

if (App::getInstance()->isDev()) { /* ... */ }
App::getInstance()->path('/storage');     // config['path'] . '/storage', plain concatenation
```

## Behavior to keep in mind while writing code

- **`run()` resolves, it does not dispatch.** Nothing runs until `->execute()`.
- **`run()` returns the stored route object itself.** It mutates the route's arguments in place, so a second `run()` on the same pattern overwrites the arguments of a route you got earlier. Execute a route before resolving another request.
- **Placeholders are `{name}`, never raw regexes**, despite what older versions of the README said. URIs are `preg_quote()`d.
- **`{all}` expands into several arguments.** Accept them variadically.
- **`{date}` is broken.** It passes every nested group as an argument.
- **Wildcard names must be `[A-Za-z0-9_]`.** A name containing `-` or `.` is never substituted, and the route silently never matches.
- **HTTP methods are case-sensitive.** Always pass upper-case verbs.
- **Re-registering the same method + URI silently overwrites.**
- **Exact matches win over wildcards. Among wildcard routes, the first registered one wins.**
- **`ControllerRoute` instantiates with no constructor arguments.** Missing dependencies raise `ArgumentCountError`, not `RouteException`.
- **`Router::getInstance()` and `Services::getInstance()` are statics.** `Router` has no reset. `Services` has `destroy()`. Use `new Router()` in tests.
- **`Services::env()` throws `InvalidArgumentException` until `setEnv()` is called**, and so do `isProd()`/`isDev()`/`isTest()`. The dev constant is spelled `'developpement'`.

Read `references/gotchas.md` for the rest (empty captures, trailing slashes, `export()` shape, `Services` details) before assuming a pattern behaves like a framework router's.
