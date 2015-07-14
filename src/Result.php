<?php

namespace TreeRoute;

/**
 * This class represents the result of an attempted Router dispatch.
 *
 * @see Router::resolve()
 */
class Result
{
    /**
     * @var string the attempted URL
     */
    public $url;

    /**
     * @var string the attempted HTTP method
     */
    public $method;

    /**
     * @var string matched route pattern
     */
    public $route;

    /**
     * @var string[] map where parameter name => parameter value
     */
    public $params = array();

    /**
     * @var callable
     */
    public $handler;
}
