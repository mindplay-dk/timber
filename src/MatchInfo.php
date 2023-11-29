<?php

namespace mindplay\timber;

/**
 * This model represents the result of an attempted match.
 *
 * @see Router::match()
 */
class MatchInfo
{
    /**
     * @param $route the Route that matched the URL
     * @param $url the URL that was matched
     * @param string[] $params captured parameters
     */
    public function __construct(Route $route, string $url, array $params)
    {
        $this->route = $route;
        $this->url = $url;
        $this->params = $params;
    }

    /**
     * @var Route the matched Route
     */
    public Route $route;

    /**
     * @var string the URL that was matched
     */
    public string $url;

    /**
     * @var string[] map where parameter name => parameter
     */
    public array $params;
}
