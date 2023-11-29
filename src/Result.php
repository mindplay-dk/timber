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
     * Handler name for the resolved HTTP method
     */
    public string $handler;

    public function __construct(string $url, string $method, Route $route, array $params, string $handler)
    {
        $this->url = $url;
        $this->method = $method;
        $this->route = $route;
        $this->params = $params;
        $this->handler = $handler;
    }
}
