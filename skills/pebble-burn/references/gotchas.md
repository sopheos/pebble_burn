# pebble-burn — gotchas

Things the method names don't tell you, grouped by class. Every item below is pinned by a test in `tests/`. Items marked **(bug)** are listed in the package's `TODO.md` and may be fixed in a later version. Check the test of the same name in `vendor/sopheos/pebble_burn/tests/` to see the current behavior.

## Router — registration

- **HTTP methods are case-sensitive.** Routes are stored as `$routes[$httpMethod][$uri]`, so `run('get', '/')` throws for a route registered with `get('/')`.
- **Re-registering the same method + URI silently overwrites.** The last `add()` wins and there is no warning.
- **The fourth argument decides the route class.** `add()` builds a `ControllerRoute` only when `$method` is truthy. Passing `''` or `null` produces a `CallableRoute` wrapping the class-name string alone, which fails at `execute()` with "is not callable".
- **`getpost()` registers two independent routes.**

## Router — matching

- **Placeholders are `{name}` lookups, not regexes.** Older versions of the README said URIs may be regexes. They can't be: `run()` only does dynamic matching for URIs containing `{`, and it `preg_quote()`s them first. `/hello/([^/]+)` only matches that literal string.
- **An unknown `{name}` is matched literally.** `/p/{nope}` only resolves the URI `/p/{nope}`. A typo in a placeholder name turns into a route that never matches, with no error.
- **(bug) A wildcard name containing `-` or `.` is never substituted.** The search key is built as `'\{' . $name . '\}'` without `preg_quote()`, while the URI is quoted, so `{my-id}` becomes `\{my\-id\}` and is never replaced. Stick to `[A-Za-z0-9_]` names.
- **(bug) `{date}` passes every nested group as an argument.** `/d/{date}` matching `2024-02-29` gives 17 arguments, and `2023-01-15` gives 28. The full date is the first argument, the rest are fragments or empty strings. Register your own single-group date wildcard.
- **`{all}` expands into several arguments.** Every capture goes through `explode('/', trim($match, '/'))`, so `/files/a/b/c` gives three arguments.
- **An empty capture yields one empty-string argument.** `/files/` gives `['']`.
- **Surrounding slashes are trimmed off each capture.** `/files/a/b/c/` gives `['a', 'b', 'c']`.
- **Patterns are anchored at both ends.** `/user/{id}` matches neither `/user/42/edit` nor `/user/42?x=1`. Strip the query string before calling `run()`.
- **Exact matches win over wildcards**, regardless of registration order. **Among wildcard routes the first registered one wins**, so `/{any}` registered before `/{id}` swallows `/42`.
- **Captured values are always strings.**
- **Unknown method and unmatched URI throw the same exception.** There is no separate "405 Method Not Allowed" path.
- **`run()` resolves but does not execute.**
- **(bug) `run()` returns the stored route object, not a copy.** `setArguments()` mutates it in place, so `$a = run('GET', '/u/1'); $b = run('GET', '/u/2');` leaves `$a === $b` with arguments `['2']`. Execute each route before resolving the next one, especially in long-running workers.
- **`Router::getInstance()` is a private static with no reset.** Use `new Router()` in tests.

## Routes

- **`ControllerRoute` instantiates with `new $classname`, with no arguments and no container.** A required constructor parameter raises `\ArgumentCountError`, and an unknown class raises `\Error`. Neither is wrapped in a `RouteException`.
- **The controller is constructed on every `execute()`**, not at registration.
- **`RouteException` doubles as "not callable".** A single `catch (RouteException)` around `run()->execute()` reports a misconfigured handler as a 404.
- **`setArguments()` replaces the arguments, it does not append.**

## Services

- **One instance per concrete subclass.** `App::getInstance()` and `Other::getInstance()` are distinct. Calling `Services::getInstance()` on the abstract class itself fatals.
- **`start()` is never called for you**, but **`stop()` runs from `__destruct`**, including when `destroy()` drops the last reference and at script shutdown.
- **`setConfig()` replaces the whole array.** It does not merge.
- **`env()` throws `InvalidArgumentException` until `setEnv()` is called**, and so do `isProd()`, `isDev()` and `isTest()`.
- **`ENV_DEV` is `'developpement'`** (French spelling). Compare against the constant, never a literal `'development'`.
- **`path()` is a plain concatenation** of `config['path']` and the argument, with no separator added.
