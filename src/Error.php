<?php

namespace mindplay\timber;

/**
 * This model represents an error generated while attempting to dispatch a Router.
 */
class Error
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
     * @var int HTTP status code
     */
    public int $status;

    /**
     * @var string HTTP status message
     */
    public string $message;

    /**
     * @var string[] list of allowed HTTP methods
     */
    public array $allowed = [];

    public function __construct(string $url, string $method, int $status, string $message, array $allowed)
    {
        $this->url = $url;
        $this->method = $method;
        $this->status = $status;
        $this->message = $message;
        $this->allowed = $allowed;
    }
}
