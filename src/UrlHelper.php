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

        static $latin1 = [
            // https://github.com/jbroadway/urlify
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A','Ă' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I',
            'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O', 'Ø' => 'O', 'Œ' => 'OE' ,'Ș' => 'S','Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U',
            'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ă' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o', 'ø' => 'o', 'œ' => 'oe', 'ș' => 's', 'ț' => 't', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y'
        ];

        $clean = str_replace(array_keys($latin1), array_values($latin1), $string);
        $clean = mb_strtolower($clean, 'UTF-8');
        $clean = preg_replace('/[^a-zA-Z0-9]+/u', '-', $clean); // reduce disallowed char ranges to dashes
        $clean = preg_replace('/[^a-zA-Z0-9-]+/u', '', $clean); // remove any remaining disallowed chars
        $clean = preg_replace('/(^-+|-+$)/', '', $clean); // strip leading/trailing dashes

        if (strlen($clean) === 0) {
            throw new InvalidArgumentException("the given string contains no allowed characters");
        }

        return substr($clean, 0, $max_length ?: $this->slug_max_length);
    }

    /**
     * Replaces a route template, with placeholders such as "<foo>" or "<foo:bar>", with
     * a set of replacement values.
     *
     * @param string $template route template
     * @param array  $values   map where token name => replacement value
     *
     * @return string
     */
    protected function replace($template, $values)
    {
        return preg_replace_callback(
            Router::PARAM_PATTERN,
            function ($matches) use ($values) {
                $name = $matches[1];

                if (!isset($values[$name])) {
                    throw new InvalidArgumentException("missing replacement value for token: {$name}");
                }

                return $values[$name];
            },
            $template
        );
    }
}
