<?php

namespace mindplay\timber;

use RuntimeException;
use UnexpectedValueException;

class Router
{
    /**
     * Parameter pattern; matches tokens like `<foo>` or `<foo:bar>`
     */
    const PARAM_PATTERN = "/(?<!\\(\\?)<([^\\:>]+)(?:$|\\:([^>]+)|)>/"; # (?<!\(\?)<([^\:>]+)(?:$|\:([^>]+)|)>

    /**
     * The root Route, corresponding to the "/" path.
     */
    private Route $root;

    /**
     * e
     */
    private PatternRegistry $registry;

    /**
     * Initialize Router with default substitutions and symbols.
     */
    public function __construct()
    {
        $this->registry = new PatternRegistry();

        $this->root = new Route($this->registry);

        // define Symbols for pattern-substitution and parameter parsing:

        $this->definePattern('int', '\d+', function ($value) {
            if (!ctype_digit($value)) {
                throw new UnexpectedValueException("unexpected parameter value: " . $value);
            }

            return intval(ltrim($value, '0'));
        });

        $this->definePattern('slug', '[a-z0-9-]+');
    }

    /**
     * Define a named pattern for use in route pattern parameters.
     *
     * @param string $name pattern name
     * @param string $expression replacement regular expression
     * @param callable(string $value): mixed $parse optional function to parse a symbol value.
     *
     * @see $patterns
     */
    public function definePattern(string $name, string $expression, ?callable $parse = null): void
    {
        $symbol = new Pattern();

        $symbol->name = $name;
        $symbol->expression = $expression;
        $symbol->parse = $parse;

        $this->registry->patterns[$name] = $symbol;
    }

    /**
     * @param string $url
     *
     * @return MatchInfo|null match information (or NULL, if no match was found)
     */
    protected function match(string $url): ?MatchInfo
    {
        if (strpos($url, '?') !== false) {
            throw new RuntimeException("unexpected query string in \$url: {$url}");
        }

        $url = trim($url, '/');

        $parts = explode('/', $url);

        if (count($parts) === 1 && $parts[0] === '') {
            $parts = [];
        }

        $params = [];

        $current = $this->root;

        foreach ($parts as $index => $part) {
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

                            $params[$name] = isset($this->registry->patterns[$symbol]->parse)
                                ? call_user_func($this->registry->patterns[$symbol]->parse, $value)
                                : $value;
                        }

                        continue 2;
                    }
                }

                if (isset($current->wildcard)) {
                    if ($current->wildcard_name) {
                        $params[$current->wildcard_name] = implode('/', array_slice($parts, $index));
                    }

                    $current = $current->wildcard;

                    break;
                }

                return null;
            }
        }

        if (!isset($current->handlers)) {
            return null;
        } else {
            return new MatchInfo(
                $current,
                '/' . $url,
                $params
            );
        }
    }

    /**
     * @param string $pattern
     *
     * @return Route the created Route object
     */
    public function route(string $pattern): Route
    {
        return $this->root->route($pattern);
    }

    /**
     * @param string $url
     *
     * @return string[]|null list of supported HTTP method names (or NULL, if no route matches the given URL)
     */
    public function getMethods(string $url): ?array
    {
        $match = $this->match($url);

        if (! $match) {
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
    public function resolve(string $method, string $url): Result
    {
        $match = $this->match($url);

        $result = new Result();
        $result->url = $match ? $match->url : '/' . ltrim($url, '/');
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

    public function getRoutes(): Route
    {
        return $this->root;
    }

    public function setRoutes(Route $routes): void
    {
        $this->root = $routes;
    }
}
