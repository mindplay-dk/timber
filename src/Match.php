<?php

namespace mindplay\timber;

/**
 * This model represents the result of an attempted match.
 *
 * @see Router::match()
 */
class Match
{
    /**
     * @param Route    $route  the Route that matched the URL
     * @param string   $url    the URL that was matched
     * @param string[] $params captured parameters
     */
    public function __construct(Route $route, $url, $params)
    {
        $this->route = $route;
        $this->url = $url;
        $this->params = $params;
    }

    /**
     * @var Route the matched Route
     */
    public $route;

    /**
     * @var string the URL that was matched
     */
    public $url;

    /**
     * @var string[] map where parameter name => parameter
     */
    public $params;
}
