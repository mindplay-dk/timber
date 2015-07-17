<?php

namespace TreeRoute;

use Closure;

/**
 * This class represents a symbol to be replaced with a regular expression.
 */
class Symbol
{
    /**
     * @var string symbol
     */
    public $name;

    /**
     * @var string replacement regular expression
     */
    public $expression;

    /**
     * @var Closure|null optional function to encode a symbol value: `function (mixed $value) : string`
     */
    public $encode;

    /**
     * @var Closure|null optional function to decode parameter values: `function (string $value) : mixed`
     */
    public $decode;
}
