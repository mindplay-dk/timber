<?php

namespace TreeRoute;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;
use UnexpectedValueException;

class Router
{
    // TODO we're faced with a trade-off here.
    //
    // We need to decide between one of the following:
    //
    // 1. Handlers are currently callable - in the original TreeRoute they were
    //    just strings, requiring a convention and syntax of some sort, in order
    //    to dispatch a handler, e.g. "controller::action" syntax, something the
    //    original library didn't deal with. The issue here is with caching - a
    //    closure cannot be serialized, thus getRoutes() and setRoutes() are
    //    currently useless. We would need to return to handlers being just
    //    strings, in order to support caching by getting and setting routes.
    //
    // 2. Retain support for callable handlers, meaning, accept the fact that we
    //    cannot support getRoutes() and setRoutes() and that the resulting
    //    routes cannot be cached.
    //
    // 3. Drop support for URL creation and accept the fact that URL creation
    //    needs to happen in URL creation functions somewhere else, outside
    //    the scope of the router. This would simplify things, but it does
    //    mean that you will need to manually keep your URL creation
    //    functions in sync with your route definitions - maintaining code to
    //    ensure the URL creation functions create URLs that are valid for the
    //    route definitions.
    //
    // In the case of (1) we will be introducing a fair bit of new complexity,
    // in the form of a convention/syntax for the handler string format, but
    // also, we would need to reintroduce named routes, in order to support
    // URL creation - because you would not be calling the code that creates
    // the routes, obtaining a reference to routes at creation time cannot
    // happen, hence names (and probably namespaces) need to be introduced in
    // order to obtain route references from the Router, at any time, by name.
    //
    // There is no "best of both worlds" here - we either forego caching, or
    // we reintroduce named routes and namespaces, or we drop support for
    // named routes and URL creation altogether.

    const PARAM_PATTERN = '/(?<!\(\?)<([^\:]+)(?:$|\:([^>]+)|)>/';
    const SEPARATOR_PATTERN = '/^[\s\/]+|[\s\/]+$/';

    /**
     * @var Route root Route (e.g. corresponding to "/")
     */
    protected $root;

    /**
     * @var int
     * @see sanitize()
     */
    protected $slug_max_length = 100;

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

        // define Symbols for the default pattern-substitution:

        $this->defineSymbol(
            'int',
            '\d+',
            function ($value) {
                if (is_scalar($value)) {
                    $value = (string) $value;
                } elseif (is_callable(array($value, '__toString'))) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $value = $value->__toString();
                } else {
                    throw new UnexpectedValueException("unexpected type: " . gettype($value));
                }

                if (!ctype_digit($value)) {
                    throw new UnexpectedValueException("unexpected value: " . $value);
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

                            $params[$name] = isset($this->registry->symbols[$symbol]->decode)
                                ? call_user_func($this->registry->symbols[$symbol]->decode, $value)
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
}
