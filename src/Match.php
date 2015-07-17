<?php

namespace TreeRoute;

/**
 * This model represents the result of an attempted match.
 *
 * @see Router::match()
 */
class Match
{
    /**
     * @param Route      $route
     * @param callable[] $methods
     * @param string[]   $params
     */
    public function __construct(Route $route, $params)
    {
        $this->route = $route;
        $this->params = $params;
    }

    /**
     * @var Route the matched Route
     */
    public $route;

    /**
     * @var callable[] map where HTTP method name => callable
     */
    public $methods;

    /**
     * @var string[] map where parameter name => parameter
     */
    public $params;
}
