<?php

namespace TreeRoute;

use Closure;
use InvalidArgumentException;
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
     * @var Route[] map where route name => named Route instance
     */
    protected $named_routes = array();

    /**
     * A map of regular expression pattern substitutions to apply to every
     * pattern encountered, as a means fo pre-processing patterns. Provides a
     * useful means of adding your own custom patterns for convenient reuse.
     *
     * @var callable[] map where full regular expression => substitution closure
     */
    protected $substitutions = array();

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
     * @var Symbol[] map where symbol name => Symbol instance
     */
    protected $symbols = array();

    /**
     * @var int
     * @see sanitize()
     */
    protected $slug_max_length = 100;

    /**
     * @var string temporary route prefix
     * @see with()
     */
    private $prefix;

    /**
     * Initialize Router with default substitutions and symbols.
     */
    public function __construct()
    {
        $this->root = new Route($this);

        // define Symbols for the default pattern-substitution:

        $this->defineSymbol(
            'int',
            '\d+',
            function ($value) {
                $value = strval($value);

                if (!ctype_digit($value)) {
                    throw new UnexpectedValueException("invalid symbol value: " . $value);
                }

                return $value;
            },
            function ($value) {
                if (!ctype_digit($value)) {
                    throw new UnexpectedValueException("unexpected parameter value: " . $value);
                }

                return intval(ltrim($value, '0'));
            }
        );

        $this->defineSymbol(
            'slug',
            '[a-z0-9-]+',
            function ($value) {
                $value = strval($value);

                return $this->sanitize($value);
            }
        );
    }

    /**
     * @param $string
     *
     * @return string
     *
     * @link https://github.com/vito/chyrp/blob/35c646dda657300b345a233ab10eaca7ccd4ec10/includes/helpers.php#L515
     */
    protected function sanitize($string) {
        static $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
            "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "—", "–", ",", "<", ".", ">", "/", "?");

        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "-", $clean);
        $clean = preg_replace("/[^a-zA-Z0-9-]/", "", $clean);
        $clean = substr($clean, 0, $this->slug_max_length);
        $clean = function_exists('mb_strtolower') ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean);

        return $clean;
    }

    /**
     * Define a substitution pattern used to preprocess route patterns
     *
     * @param string  $pattern regular expression pattern
     * @param Closure $func    replacement callback: `function (string[] $matches) : string`
     *
     * @return void
     *
     * @see
     */
    public function defineSubstitution($pattern, $func)
    {
        $this->substitutions[$pattern] = $func;
    }

    /**
     * Define a symbol name for use in parameter definitions in route patterns
     *
     * @param string        $name       symbol name
     * @param string        $expression replacement regular expression
     * @param callable|null $encode     optional function to encode a symbol value: `function (mixed $value) : string`
     * @param callable|null $decode     optional function to decode a symbol value: `function (string $value) : mixed`
     *
     * @return void
     *
     * @see $symbols
     */
    public function defineSymbol($name, $expression, $encode = null, $decode = null)
    {
        $symbol = new Symbol();

        $symbol->name = $name;
        $symbol->expression = $expression;
        $symbol->encode = $encode;
        $symbol->decode = $decode;

        $this->symbols[$name] = $symbol;
    }

    /**
     * Prepares a regular expression pattern by applying substitution patterns to it.
     *
     * @param string $pattern unprocessed pattern
     *
     * @return string pre-processed pattern
     *
     * @throws RuntimeException if the regular expression fails to execute
     *
     * @see $substitutions
     * @see addRoute()
     */
    protected function preparePattern($pattern)
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

                            $params[$name] = isset($this->symbols[$symbol]->decode)
                                ? call_user_func($this->symbols[$symbol]->decode, $value)
                                : $value;
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
                $current,
                $params
            );
        }
    }

    /**
     * @param string|string[] $methods HTTP request method (or list of methods)
     * @param string $pattern
     * @param $handler
     *
     * @return Route the created Route object
     */
    public function addRoute($methods, $pattern, $handler)
    {
        $methods = (array) $methods;
        $pattern = $this->prefix . $pattern;

        $parts = explode('?', $pattern, 1);
        $parts = explode('/', preg_replace(self::SEPARATOR_PATTERN, '', $parts[0]));

        if (sizeof($parts) === 1 && $parts[0] === '') {
            $parts = [];
        }

        $current = $this->root;

        foreach ($parts as $part) {
            $part_pattern = $this->preparePattern($part);

            $params = array();

            $part_pattern = preg_replace_callback(
                self::PARAM_PATTERN,
                function ($matches) use (&$params) {
                    $name = $matches[1];
                    $pattern = '[^\/]+';
                    $symbol = null;

                    if (isset($matches[2])) {
                        $pattern = $matches[2];

                        if (isset($this->symbols[$pattern])) {
                            $symbol = $this->symbols[$pattern];
                            $pattern = $symbol->expression;
                        }
                    }

                    $params[$name] = $symbol
                        ? $symbol->name
                        : $pattern;

                    return "(?<{$name}>{$pattern})";
                },
                $part_pattern
            );

            if (strpos($part_pattern, '(?<') !== false) {
                // pattern contains named parameter capture
                if (!isset($current->regexps[$part_pattern])) {
                    $current->regexps[$part_pattern] = new Route($this);
                }
                $current = $current->regexps[$part_pattern];
            } else {
                // pattern does not contain parameter capture
                if (!isset($current->children[$part])) {
                    $current->children[$part] = new Route($this);
                }
                $current = $current->children[$part];
            }

            $current->params = $params;
        }

        $current->pattern = $pattern;

        foreach ($methods as $method) {
            $current->methods[strtoupper($method)] = $handler;
        }

        return $current;
    }

    /**
     * @param string $name route name
     * @param array $params
     *
     * @return string
     */
    public function createUrl($name, $params = array())
    {
        if (! isset($this->named_routes[$name])) {
            throw new InvalidArgumentException("no route with the given name has been defined: {$name}");
        }

        $route = $this->named_routes[$name];

        $pattern = $this->preparePattern($route->pattern);

        return preg_replace_callback(
            self::PARAM_PATTERN,
            function ($matches) use ($params) {
                $name = $matches[1];

                if (isset($matches[2])) {
                    $pattern = $matches[2];

                    if (isset($this->symbols[$pattern]->encode)) {
                        return call_user_func($this->symbols[$pattern]->encode, $params[$name]);
                    }
                }

                return $params[$name];
            },
            $pattern
        );
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
        $saved = $this->prefix;

        $this->prefix .= $prefix;

        $func($this);

        $this->prefix = $saved;
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
            return array_keys($match->route->methods);
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

            if (isset($match->route->methods[$method])) {
                $result->handler = $match->route->methods[$method];
            } else {
                $result->error = new Error(405, 'Method Not Allowed');
                $result->error->allowed = array_keys($match->route->methods);
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
        return $this->root;
    }

    /**
     * @param Route $routes
     */
    public function setRoutes($routes)
    {
        $this->root = $routes;
    }

    /**
     * @param Route $route
     */
    public function registerNamedRoute(Route $route)
    {
        if (isset($this->named_routes[$route->name])) {
            throw new RuntimeException("duplicate route name: {$route->name}");
        }

        $this->named_routes[$route->name] = $route;
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
