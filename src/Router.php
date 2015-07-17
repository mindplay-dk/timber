<?php

namespace TreeRoute;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

class Router
{
    const SEPARATOR_REGEXP = '/^[\s\/]+|[\s\/]+$/';

    /**
     * @var Route
     */
    private $routes;

    /**
     * A map of regular expression pattern substitutions to apply to every
     * pattern encountered, as a means fo pre-processing patterns. Provides a
     * useful means of adding your own custom patterns for convenient reuse.
     *
     * @var callable[] map where full regular expression => substitution closure
     */
    public $substitutions = array();

    /**
     * Symbols used by the built-in standard substitution pattern, which provides
     * a convenient short-hand syntax for placeholder tokens. The built-in standard
     * symbols are:
     *
     * <pre>
     *     'int'  => '\d+'
     *     'slug' => '[a-z0-9-]+'
     * </pre>
     *
     * Which provides support for simplified named routes, such as:
     *
     * <pre>
     *     'user/<user_id:int>'
     *     'tags/<tag:slug>'
     * </pre>
     *
     * For which the resulting patterns would be:
     *
     * <pre>
     *     'user/(?<user_id:\d+>)'
     *     'tags/(?<slug:[a-z0-9-]>)'
     * </pre>
     *
     * @var string[] map where symbol name => partial regular expression
     */
    public $symbols = array();

    /**
     * @var string temporary route prefix
     * @see _with()
     */
    private $prefix;

    public function __construct()
    {
        $this->routes = new Route();

        // define a default pattern-substitution with support for some common symbols:

        $router = $this;

        $this->substitutions['/(?<!\(\?)<([^\:]+)(?:$|\:([^>]+)|)>/'] = function ($matches) use ($router) {
            $pattern = '[^\/]+';

            if (isset($matches[2])) {
                $pattern = $matches[2];

                if (isset($router->symbols[$pattern])) {
                    $pattern = $router->symbols[$pattern];
                }
            }

            return "(?<{$matches[1]}>{$pattern})";
        };

        // define common symbols for the default pattern-substitution:

        $this->symbols = array(
            'int'  => '\d+',
            'slug' => '[a-z0-9-]+',
        );
    }

    /**
     * Prepares a regular expression pattern by applying the patterns and callbacks
     * defined by {@link $substitutions} to it.
     *
     * @param string $pattern unprocessed pattern
     *
     * @return string pre-processed pattern
     *
     * @throws RuntimeException if the regular expression fails to execute
     */
    private function preparePattern($pattern)
    {
        foreach ($this->substitutions as $subpattern => $fn) {
            $pattern = @preg_replace_callback($subpattern, $fn, $pattern);

            if ($pattern === null) {
                throw new RuntimeException("invalid substitution pattern: {$subpattern}");
            }
        }

        return $pattern;
    }

    /**
     * @param string $url
     *
     * @return Match|null match information (or NULL, if no match was found)
     */
    private function match($url)
    {
        $parts = explode('?', $url, 1);
        $parts = explode('/', preg_replace(self::SEPARATOR_REGEXP, '', $parts[0]));
        if (sizeof($parts) === 1 && $parts[0] === '') {
            $parts = [];
        }
        $params = [];
        $current = $this->routes;

        foreach ($parts as $part) {
            if (isset($current->childs[$part])) {
                $current = $current->childs[$part];
                if ($current->init) {
                    $this->init($current);
                }
            } else {
                foreach ($current->regexps as $pattern => $route) {
                    /** @var int|bool $match result of preg_match() against $pattern */
                    $match = @preg_match('#^' . $pattern . '(?=$)#', $part, $matches);

                    if ($match === false) {
                        throw new RuntimeException("invalid pattern '{$pattern}' (preg_match returned false)");
                    }

                    if ($match === 1) {
                        $current = $route;

                        if ($current->init) {
                            $this->init($current);
                        }

                        foreach ($matches as $name => $value) {
                            $params[$name] = $value;
                        }

                        continue 2;
                    }
                }
                return null;
            }
        }

        if (!isset($current->methods)) {
            return null;
        } else {
            return new Match(
                $current->route,
                $current->methods,
                $params
            );
        }
    }

    /**
     * @param Route $route
     */
    private function init(Route $route)
    {
        $saved = $this->prefix;

        $this->prefix .= $route->route;

        call_user_func($route->init, $this);

        $route->init = null;

        $this->prefix = $saved;
    }

    /**
     * @param string|string[] $methods HTTP request method (or list of methods)
     * @param string $route
     * @param $handler
     *
     * @return Route the created Route object
     */
    public function addRoute($methods, $route, $handler)
    {
        $methods = (array) $methods;
        $route = $this->prefix . $route;

        $parts = explode('?', $route, 1);
        $parts = explode('/', preg_replace(self::SEPARATOR_REGEXP, '', $parts[0]));
        if (sizeof($parts) === 1 && $parts[0] === '') {
            $parts = [];
        }

        $current = $this->routes;

        foreach ($parts as $part) {
            $pattern = $this->preparePattern($part);

            if (strpos($pattern, '(?<') !== false) {
                // pattern contains named parameter capture
                if (!isset($current->regexps[$pattern])) {
                    $current->regexps[$pattern] = new Route();
                }
                $current = $current->regexps[$pattern];
            } else {
                // pattern does not contain parameter capture
                if (!isset($current->childs[$part])) {
                    $current->childs[$part] = new Route();
                }
                $current = $current->childs[$part];
            }
        }

        $current->route = $route;

        foreach ($methods as $method) {
            $current->methods[strtoupper($method)] = $handler;
        }

        return $current;
    }

    /**
     * Configure the Router with a given route prefix, which will be
     * applied to all the routes created in the given callback.
     *
     * @param string $prefix
     * @param callable $func function (Router $router) : void
     *
     * @return void
     */
    public function with($prefix, callable $func)
    {
        $route = $this->addRoute(array(), $prefix, null);

        $route->init = $func;
    }

    /**
     * @param string $url
     *
     * @return string[]|null list of supported HTTP method names
     */
    public function getMethods($url)
    {
        $route = $this->match($url);
        if (!$route) {
            return null;
        } else {
            return array_keys($route->methods);
        }
    }

    /**
     * @param string $method HTTP method name
     * @param string $url
     *
     * @return Result
     */
    public function resolve($method, $url)
    {
        $match = $this->match($url);

        $result = new Result();
        $result->url = $url;
        $result->method = $method;

        if (!$match) {
            $result->error = new Error(404, 'Not Found');
        } else {
            $result->route = $match->route;
            $result->params = $match->params;

            if (isset($match->methods[$method])) {
                $result->handler = $match->methods[$method];
            } else {
                $result->error = new Error(405, 'Method Not Allowed');
                $result->error->allowed = array_keys($match->methods);
            }
        }

        return $result;
    }

    /**
     * @param string $method HTTP method name
     * @param string $url
     *
     * @return mixed|Error return value from the dispatched handler (or an instance of Error)
     */
    public function dispatch($method, $url)
    {
        $result = $this->resolve($method, $url);

        if ($result->error) {
            return $result->error;
        }

        if ($result->handler instanceof Closure) {
            $reflection = new ReflectionFunction($result->handler);
        } elseif (is_array($result->handler)) {
            $reflection = new ReflectionMethod($result->handler[0], $result->handler[1]);
        }

        $params = array();

        if (isset($reflection)) {
            foreach ($reflection->getParameters() as $param) {
                $name= $param->getName();

                if (isset($result->params[$name])) {
                    $params[$name] = $result->params[$name];
                } elseif ($param->isOptional()) {
                    $params[$name] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException("unable to dispatch handler - missing parameter: {$name}");
                }
            }
        }

        return call_user_func_array($result->handler, $params);
    }

    /**
     * @return Route
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @param Route $routes
     */
    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function options($route, $handler)
    {
        return $this->addRoute('OPTIONS', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function get($route, $handler)
    {
        return $this->addRoute('GET', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function head($route, $handler)
    {
        return $this->addRoute('HEAD', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function post($route, $handler)
    {
        return $this->addRoute('POST', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function put($route, $handler)
    {
        return $this->addRoute('PUT', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function delete($route, $handler)
    {
        return $this->addRoute('DELETE', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function trace($route, $handler)
    {
        return $this->addRoute('TRACE', $route, $handler);
    }

    /**
     * @param string $route
     * @param callable $handler
     *
     * @return Route
     */
    public function connect($route, $handler)
    {
        return $this->addRoute('CONNECT', $route, $handler);
    }
}
