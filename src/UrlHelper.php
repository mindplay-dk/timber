<?php

namespace TreeRoute;

use UnexpectedValueException;

/**
 * Abstract base class for URL creation helper classes.
 */
abstract class UrlHelper
{
    /**
     * @var int
     * @see sanitize()
     */
    protected $slug_max_length = 100;

    /**
     * String assertion and conversion
     *
     * @param mixed $value
     *
     * @return string
     *
     * @throws UnexpectedValueException if the given value cannot be converted to a string
     */
    protected function str($value)
    {
        if (is_scalar($value)) {
            return (string) $value;
        } elseif (is_callable(array($value, '__toString'))) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $value->__toString();
        }

        throw new UnexpectedValueException("unexpected type: " . gettype($value));
    }

    /**
     * String assertion and "slug" conversion, e.g. prepare text for use as a URL token
     *
     * @param mixed $value
     *
     * @return string sanitized string for use in URL tokens (lowercase a-z, 0-9 and hyphens)
     *
     * @link https://github.com/vito/chyrp/blob/35c646dda657300b345a233ab10eaca7ccd4ec10/includes/helpers.php#L515
     */
    protected function slug($value) {
        $string = $this->str($value);

        static $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
            "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "—", "–", ",", "<", ".", ">", "/", "?");

        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "-", $clean);
        $clean = preg_replace("/[^a-zA-Z0-9-]/", "", $clean);
        $clean = substr($clean, 0, $this->slug_max_length);
        $clean = function_exists('mb_strtolower') ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean);

        return $clean;
    }
}
