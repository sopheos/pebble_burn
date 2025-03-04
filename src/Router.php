<?php

namespace Pebble\Burn;

/**
 * Router
 *
 * A simple RESTfull router
 *
 * @author mathieu
 */
class Router
{
    private static ?Router $instance = null;

    /**
     * @var Route[][]
     */
    private array $routes = [];
    private array $wildcards = [];

    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->wildcards = [
            'all' => '(.*)',
            'any' => '([^/]+)',
            'id'  => '([0-9]+)',
            'num' => '(-?[0-9]+)',
            'hex' => '([A-Fa-f0-9]+)',
            'size' => '(xs|sm|md|lg)',
            'uuid' => '([a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})',
            'date' => '((((((1[26]|2[048])00)|[12]\d([2468][048]|[13579][26]|0[48]))-((((0[13578]|1[02])-(0[1-9]|[12]\d|3[01]))|((0[469]|11)-(0[1-9]|[12]\d|30)))|(02-(0[1-9]|[12]\d))))|((([12]\d([02468][1235679]|[13579][01345789]))|((1[1345789]|2[1235679])00))-((((0[13578]|1[02])-(0[1-9]|[12]\d|3[01]))|((0[469]|11)-(0[1-9]|[12]\d|30)))|(02-(0[1-9]|1\d|2[0-8]))))))'
        ];
    }

    /**
     * @return static
     */
    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * @param string $shortcut
     * @param string $pattern
     * @return static
     */
    public function wildcard(string $shortcut, string $pattern): static
    {
        $this->wildcards[$shortcut] = $pattern;
        return $this;
    }

    /**
     * @param array $wildcards
     * @return static
     */
    public function wildcards(array $wildcards): static
    {
        $this->wildcards = array_merge($this->wildcards, $wildcards);
        return $this;
    }

    /**
     * @param string $http_method
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function add(string $http_method, string $uri, mixed $controller, mixed $method = null): static
    {

        $this->routes[$http_method][$uri] = $method
            ? new ControllerRoute($http_method, $uri, [$controller, $method])
            : new CallableRoute($http_method, $uri, $controller);
        return $this;
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function get(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('GET', $uri, $controller, $method);
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function post(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('POST', $uri, $controller, $method);
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function getpost(string $uri, mixed $controller, mixed $method = null): static
    {
        $this->get($uri, $controller, $method);
        $this->post($uri, $controller, $method);
        return $this;
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function put(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('PUT', $uri, $controller, $method);
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function patch(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('PATCH', $uri, $controller, $method);
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function delete(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('DELETE', $uri, $controller, $method);
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function options(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('OPTIONS', $uri, $controller, $method);
    }

    /**
     * @param string $uri
     * @param mixed $controller
     * @param mixed $method
     * @return static
     */
    public function cli(string $uri, mixed $controller, mixed $method = null): static
    {
        return $this->add('CLI', $uri, $controller, $method);
    }

    // -------------------------------------------------------------------------

    /**
     * @param string $http_method
     * @param string $uri
     */
    public function run(string $http_method, string $uri): RouteInterface
    {
        // Default
        if (!$http_method) $http_method = 'GET';
        if (!$uri) $uri = '/';

        // Method not found
        if (!isset($this->routes[$http_method])) {
            throw new RouteException("Route not found: {$http_method} {$uri}");
        }

        // Exact match
        if (isset($this->routes[$http_method][$uri])) {
            return $this->routes[$http_method][$uri];
        }

        // Regex match
        $search = [];
        $replace = [];
        foreach ($this->wildcards as $k => $v) {
            $search[] = '\{' . $k . '\}';
            $replace[] = $v;
        }

        foreach ($this->routes[$http_method] as $u => $route) {
            if (mb_strpos($u, '{') !== false) {
                $matches = [];
                $pattern = '#^' . str_replace($search, $replace, preg_quote($u, '#')) . '$#';

                if (preg_match($pattern, $uri, $matches)) {
                    return $route->setArguments(self::getParams($matches));
                }
            }
        }

        // Not found
        throw new RouteException("Route not found: {$http_method} {$uri}");
    }

    /**
     * @param array $matches
     * @return array
     */
    private static function getParams(array $matches): array
    {
        array_shift($matches);

        $params = [];
        foreach ($matches as $match) {
            foreach (explode('/', trim($match, '/')) as $param) {
                $params[] = $param;
            }
        }

        return $params;
    }

    // -------------------------------------------------------------------------

    /**
     * @return array
     */
    public function export(): array
    {
        $export = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $uri => $handler) {
                $export[$uri][] = $method;
            }
        }

        ksort($export);

        return $export;
    }

    // -------------------------------------------------------------------------
}
