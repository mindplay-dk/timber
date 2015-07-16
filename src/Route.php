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
    public $route;

    /**
     * @var callable[] map where HTTP method => callable method handler
     */
    public $methods = array();

    /**
     * @var Route[] list of nested Route instances
     */
    public $childs = array();

    /**
     * @var Route[] map where regular expression => nested Route instance
     */
    public $regexps = array();

    /**
     * @var callable|null route initialization function (Router $router) : void
     */
    public $init;
}
