<?php

namespace mindplay\timber;

/**
 * This model defines a registry for Symbols and named routes
 */
class Registry
{
    /**
     * Symbols provide a convenient short-hand syntax for placeholder tokens.
     * The built-in standard symbols provide support for simplified named routes, such as:
     *
     * <pre>
     *     'user/<user_id:int>'
     *     'tags/<tag:slug>'
     * </pre>
     *
     * For which the resulting patterns would be:
     *
     * <pre>
     *     'user/(?<user_id:\d+>)'
     *     'tags/(?<slug:[a-z0-9-]>)'
     * </pre>
     *
     * @var Symbol[] map where symbol name => Symbol instance
     */
    public $symbols = array();
}
