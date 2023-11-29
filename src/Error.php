<?php

namespace mindplay\timber;

/**
 * This model represents an error generated while attempting to dispatch a Router.
 *
 * @see Result::$error
 */
class Error
{
    public function __construct(int $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * @var int error code
     */
    public int $code;

    /**
     * @var string error message
     */
    public string $message;

    /**
     * @var string[] list of allowed HTTP methods
     */
    public array $allowed = [];
}
