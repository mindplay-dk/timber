<?php

namespace TreeRoute;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;
use UnexpectedValueException;

class Router
{
    const PARAM_PATTERN = '/(?<!\(\?)<([^\:]+)(?:$|\:([^>]+)|)>/';
    const SEPARATOR_PATTERN = '/^[\s\/]+|[\s\/]+$/';

    /**
     * @var Route root Route (e.g. corresponding to "/")
     */
    protected $root;

    /**
     * @var Registry name and Symbol registry
     */
    protected $registry;

    /**
     * Initialize Router with default substitutions and symbols.
     */
    public function __construct()
    {
        $this->registry = new Registry();

        $this->root = new Route($this->registry);

        // define Symbols for pattern-substitution and parameter parsing:

        $this->defineSymbol('int', '\d+', function ($value) {
            if (!ctype_digit($value)) {
                throw new UnexpectedValueException("unexpected parameter value: " . $value);
            }

            return intval(ltrim($value, '0'));
        });

        $this->defineSymbol('slug', '[a-z0-9-]+');
    }

    /**
     * Define a symbol name for use in parameter definitions in route patterns
     *
     * @param string $name symbol name
     * @param string $expression replacement regular expression
     * @param callable|null $parse optional function to parse a symbol value: `function (string $value) : mixed`
     *
     * @see $symbols
     */
    public function defineSymbol($name, $expression, $parse = null)
    {
        $symbol = new Symbol();

        $symbol->name = $name;
        $symbol->expression = $expression;
        $symbol->parse = $parse;

        $this->registry->symbols[$name] = $symbol;
    }

    /**
     * @param string $url
     *
     * @return Match|null match information (or NULL, if no match was found)
     */
    protected function match($url)
    {
        $parts = explode('?', $url, 1);
        $parts = explode('/', preg_replace(self::SEPARATOR_PATTERN, '', $parts[0]));
        if (sizeof($parts) === 1 && $parts[0] === '') {
            $parts = [];
        }
        $params = [];
        $current = $this->root;

        foreach ($parts as $part) {
            if (isset($current->children[$part])) {
                $current = $current->children[$part];
            } else {
                foreach ($current->regexps as $pattern => $route) {
                    /** @var int|bool $match result of preg_match() against $pattern */
                    $match = @preg_match('#^' . $pattern . '(?=$)#', $part, $matches);

                    if ($match === false) {
                        throw new RuntimeException("invalid pattern '{$pattern}' (preg_match returned false)");
                    }

                    if ($match === 1) {
                        $current = $route;

                        foreach ($matches as $name => $value) {
                            if (is_int($name)) {
                                continue; // skip substring captures without name
                            }

                            $symbol = $current->params[$name];

                            $params[$name] = isset($this->registry->symbols[$symbol]->parse)
                                ? call_user_func($this->registry->symbols[$symbol]->parse, $value)
                                : $value;
                        }

                        continue 2;
                    }
                }
                return null;
            }
        }

        if (!isset($current->handlers)) {
            return null;
        } else {
            return new Match(
                $current,
                $params
            );
        }
    }

    /**
     * @param string $pattern
     *
     * @return Route the created Route object
     */
    public function route($pattern)
    {
        return $this->root->route($pattern);
    }

    /**
     * @param string $url
     *
     * @return string[]|null list of supported HTTP method names
     */
    public function getMethods($url)
    {
        $match = $this->match($url);

        if (!$match) {
            return null;
        } else {
            return array_keys($match->route->handlers);
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

            if (isset($match->route->handlers[$method])) {
                $result->handler = $match->route->handlers[$method];
            } else {
                $result->error = new Error(405, 'Method Not Allowed');
                $result->error->allowed = array_keys($match->route->handlers);
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
                $name = $param->getName();

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
        return $this->root;
    }

    /**
     * @param Route $routes
     */
    public function setRoutes($routes)
    {
        $this->root = $routes;
    }
}
