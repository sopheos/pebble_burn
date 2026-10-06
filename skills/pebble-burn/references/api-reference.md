# pebble-burn — API cheat sheet

Quick lookup by intent. This is not exhaustive. Read the source in `vendor/sopheos/pebble_burn/src/` for exact signatures and for edge cases not covered here.

## Router (`Pebble\Burn\Router`)

Build with `new Router()`, or share one through `Router::getInstance(): static` (lazy singleton, no reset).

### Registration

All registration methods are fluent (they return `static`).

| Intent | Method |
| ------ | ------ |
| Register under an arbitrary method string | `add(string $http_method, string $uri, mixed $controller, mixed $method = null): static` |
| `GET` / `POST` | `get(...)` / `post(...)` |
| Both `GET` and `POST` in one call | `getpost(...)` |
| `PUT` / `PATCH` / `DELETE` / `OPTIONS` | `put(...)` / `patch(...)` / `delete(...)` / `options(...)` |
| Register under the `'CLI'` pseudo-method | `cli(...)` |

Every shortcut has the signature `(string $uri, mixed $controller, mixed $method = null): static`.

- If `$method` is falsy, `add()` builds a `CallableRoute` wrapping `$controller`.
- If `$method` is truthy, it builds a `ControllerRoute` wrapping `[$controller, $method]`, instantiated at `execute()` time.

### Wildcards

| Intent | Method |
| ------ | ------ |
| Add or replace one placeholder | `wildcard(string $shortcut, string $pattern): static` |
| Merge several over the defaults | `wildcards(array $wildcards): static` |

Built-in table. A usable pattern has exactly **one** capturing group:

| Placeholder | Regex | Matches |
| ----------- | ----- | ------- |
| `{all}` | `(.*)` | anything, **including `/`**; expands into several arguments |
| `{any}` | `([^/]+)` | one non-empty path segment |
| `{id}` | `([0-9]+)` | unsigned integer |
| `{num}` | `(-?[0-9]+)` | optionally negative integer |
| `{hex}` | `([A-Fa-f0-9]+)` | hexadecimal |
| `{size}` | `(xs\|sm\|md\|lg)` | one of those four literals |
| `{uuid}` | `([a-fA-F0-9]{8}-…-[a-fA-F0-9]{12})` | canonical 8-4-4-4-12 UUID |
| `{date}` | validated `YYYY-MM-DD` with leap years | **broken**: nested groups make 17 to 28 arguments |

### Resolution

| Intent | Method |
| ------ | ------ |
| Resolve one request to a route (does **not** run it) | `run(string $http_method, string $uri): RouteInterface` |
| List registered routes, `ksort`ed by URI | `export(): array`, shaped `['/uri' => ['GET', 'POST'], …]` |

`run()` defaults an empty method to `'GET'` and an empty URI to `'/'`. It tries an exact match first, then every URI containing `{`, in registration order. It throws `RouteException("Route not found: {$method} {$uri}")`.

## RouteInterface (`Pebble\Burn\RouteInterface`)

| Intent | Method |
| ------ | ------ |
| Captured URL parameters (strings, in pattern order) | `arguments(): array` |
| Replace the arguments (what `run()` calls) | `setArguments(array $arguments): static` |
| The method and URI this route was registered under | `method(): string` / `uri(): string` |
| The raw handler | `callback(): mixed` |
| Invoke the handler | `execute(): mixed` |

`RouteAbstract::execute()` always throws `RouteException("{METHOD} {uri} is not callable")`. Both subclasses fall back to it.

## CallableRoute / ControllerRoute

- `CallableRoute::execute()` calls `call_user_func_array($callback, $arguments)` when `is_callable($callback)` is true.
- `ControllerRoute::execute()` calls `(new $callback[0])->{$callback[1]}(...$arguments)`.

## Services (`Pebble\Burn\Services`, abstract)

| Intent | Method |
| ------ | ------ |
| One instance per subclass | `static::getInstance(): static` |
| Drop the instance (triggers `__destruct` → `stop()` if unreferenced) | `static::destroy()` |
| Lifecycle hooks (empty by default) | `start()` / `stop()` |
| Replace the whole config | `setConfig(array $config): static` |
| Read one key (`null` if missing) | `config(string $name): mixed` |
| Set or read the environment | `setEnv(string $env): static` / `env(): string` (throws if unset) |
| Environment tests | `isProd()` / `isTest()` / `isDev()` |
| `config['path'] . $path` | `path(string $path = ''): string` |

Constants: `ENV_PROD = 'production'`, `ENV_TEST = 'testing'`, `ENV_DEV = 'developpement'`.
