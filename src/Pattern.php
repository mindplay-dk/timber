<?php

namespace mindplay\timber;

use Closure;

/**
 * This class represents a named pattern to be replaced with a regular expression.
 * 
 * The built-in standard patterns provide common patterns, such as `int` and `slug`:
 *
 *     'user/<user_id:int>'
 *     'tags/<tag:slug>'
 *
 * For which the resulting patterns would be:
 *
 *     'user/(?<user_id:\d+>)'
 *     'tags/(?<slug:[a-z0-9-]>)'
 */
class Pattern
{
    /**
     * Pattern name
     */
    public string $name;

    /**
     * Replacement regular expression pattern
     */
    public string $expression;

    /**
     * @var (callable(string $value): mixed) | null Optional function to parse parameter values
     */
    public ?Closure $parse;
}
