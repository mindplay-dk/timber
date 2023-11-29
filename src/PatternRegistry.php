<?php

namespace mindplay\timber;

/**
 * This model defines a registry for route patterns.
 */
class PatternRegistry
{
    /**
     * @var Pattern[] map where pattern name => Pattern instance
     */
    public array $patterns = [];
}
