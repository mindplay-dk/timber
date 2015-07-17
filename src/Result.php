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
     * @var Route matched Route
     */
    public $route;

    /**
     * @var mixed[] map where parameter name => parameter value
     */
    public $params = array();

    /**
     * @var callable
     */
    public $handler;

    /**
     * @var Error|null error information (or NULL, if this Result is a success)
     */
    public $error;
}
