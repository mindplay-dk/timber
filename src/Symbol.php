<?php

namespace mindplay\timber;

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
     * @var Closure|null optional function to parse parameter values: `function (string $value) : mixed`
     */
    public $parse;
}
