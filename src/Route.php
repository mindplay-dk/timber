<?php

namespace TreeRoute;

use RuntimeException;

/**
 * This class represents a route, or part of a route, within a Router.
 */
class Route
{
    /**
     * @param Router     $owner
     */
    public function __construct(Router $owner)
    {
        $this->owner = $owner;
    }

    /**
     * @var Router
     */
    private $owner;

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
     * @var string|null route name (or NULL, if this is not a named route)
     */
    public $name;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function name($name)
    {
        if ($this->name) {
            throw new RuntimeException("route already has a name: {$name}");
        }

        $this->name = $name;

        $this->owner->registerNamedRoute($this);

        return $this;
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
