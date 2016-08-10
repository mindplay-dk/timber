<?php

namespace mindplay\timber;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Abstract base class for URL creation helper classes.
 */
abstract class UrlHelper
{
    /**
     * @var int default max. length of slug strings
     * @see slug()
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
     * @param mixed    $value input string (or string-castable object/value; assumes UTF-8)
     * @param int|null $max_length optional max. length of slug string (defaults to $this->slug_max_length)
     *
     * @return string sanitized string for use in URL tokens (lowercase a-z, 0-9 and hyphens)
     *
     * @throws InvalidArgumentException if the given string contains no allowed characters
     */
    protected function slug($value, $max_length = null) {
        $string = $this->str($value);

        $clean = mb_strtolower($string, 'UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean); // collate diacritics, e.g. å => a
        $clean = preg_replace('/[^a-zA-Z0-9]+/', '-', $clean); //
        $clean = preg_replace('/(^-+|-+$)/', '', $clean);

        if (strlen($clean) === 0) {
            throw new InvalidArgumentException("the given string contains no allowed characters");
        }

        return substr($clean, 0, $max_length ?: $this->slug_max_length);
    }
}
