<?php

namespace TreeRoute;

/**
 * This class represents a route, or part of a route, within a Router.
 */
class Route
{
    /**
     * @var string route pattern
     */
    public $pattern;

    /**
     * @var string[] map where parameter name => regular expression pattern (or symbol name)
     */
    public $params;

    /**
     * @var callable[] map where HTTP method => callable method handler
     */
    public $handlers = array();

    /**
     * @var Route[] list of nested Route instances
     */
    public $children = array();

    /**
     * @var Route[] map where regular expression => nested Route instance
     */
    public $regexps = array();

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param string $pattern
     *
     * @return Route the created Route object
     */
    public function route($pattern)
    {
        $parts = explode('?', $pattern, 1);
        $parts = explode('/', preg_replace(Router::SEPARATOR_PATTERN, '', $parts[0]));

        if (sizeof($parts) === 1 && $parts[0] === '') {
            $parts = [];
        }

        $current = $this;
        $pattern = $this->pattern;

        foreach ($parts as $part) {
            $pattern .= '/' . $part;
            $params = array();

            $part = preg_replace_callback(
                Router::PARAM_PATTERN,
                function ($matches) use (&$params) {
                    $name = $matches[1];
                    $pattern = '[^\/]+';
                    $symbol = null;

                    if (isset($matches[2])) {
                        $pattern = $matches[2];

                        if (isset($this->registry->symbols[$pattern])) {
                            $symbol = $this->registry->symbols[$pattern];
                            $pattern = $symbol->expression;
                        }
                    }

                    $params[$name] = $symbol
                        ? $symbol->name
                        : $pattern;

                    return "(?<{$name}>{$pattern})";
                },
                $part
            );

            if (strpos($part, '(?<') !== false) {
                // pattern contains named parameter capture
                if (!isset($current->regexps[$part])) {
                    $current->regexps[$part] = new Route($this->registry);
                }
                $current = $current->regexps[$part];
            } else {
                // pattern does not contain parameter capture
                if (!isset($current->children[$part])) {
                    $current->children[$part] = new Route($this->registry);
                }
                $current = $current->children[$part];
            }

            $current->params = $params;
            $current->pattern = $pattern;
        }

        return $current;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function options($handler)
    {
        $this->handlers['OPTIONS'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function get($handler)
    {
        $this->handlers['GET'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function head($handler)
    {
        $this->handlers['HEAD'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function post($handler)
    {
        $this->handlers['POST'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function put($handler)
    {
        $this->handlers['PUT'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function delete($handler)
    {
        $this->handlers['DELETE'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function trace($handler)
    {
        $this->handlers['TRACE'] = $handler;

        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function connect($handler)
    {
        $this->handlers['CONNECT'] = $handler;

        return $this;
    }
}
