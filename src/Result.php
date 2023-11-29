<?php

namespace mindplay\timber;

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
    public string $url;

    /**
     * @var string the attempted HTTP method
     */
    public string $method;

    /**
     * @var Route matched Route
     */
    public Route $route;

    /**
     * Map where parameter name => parameter value
     */
    public array $params = [];

    /**
     * Handler defined for the attempted HTTP method (or NULL, if this Result is an error) // TODO improve this: union types?
     */
    public ?string $handler; // TODO use a callable type? (callbacks can't be serialized!)

    /**
     * Error information (or NULL, if this Result is a success)
     */
    public ?Error $error = null;
}
