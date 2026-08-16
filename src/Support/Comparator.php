<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Support;

use MemoryQueryBuilder\Exceptions\InvalidQueryException;

/**
 * Evaluates comparison operators between two values.
 * Supports: =, ==, ===, !=, <>, !==, <, <=, >, >=,
 *           like, not like, ilike, contains, starts_with, ends_with,
 *           in, not in, between, not between, regexp, not regexp,
 *           null checks (handled upstream).
 */
class Comparator
{
    /**
     * Evaluate: ($left $operator $right)
     */
    public static function evaluate(mixed $left, string $operator, mixed $right): bool
    {
        return match (strtolower(trim($operator))) {
            '=', '=='   => $left == $right,
            '==='       => $left === $right,
            '!=', '<>'  => $left != $right,
            '!=='       => $left !== $right,
            '<'         => $left < $right,
            '<='        => $left <= $right,
            '>'         => $left > $right,
            '>='        => $left >= $right,

            'like'      => self::likeMatch((string) $left, (string) $right, caseSensitive: true),
            'not like'  => !self::likeMatch((string) $left, (string) $right, caseSensitive: true),
            'ilike'     => self::likeMatch((string) $left, (string) $right, caseSensitive: false),
            'not ilike' => !self::likeMatch((string) $left, (string) $right, caseSensitive: false),

            'contains'          => str_contains((string) $left, (string) $right),
            'not contains'      => !str_contains((string) $left, (string) $right),
            'icontains'         => str_contains(strtolower((string) $left), strtolower((string) $right)),
            'starts_with'       => str_starts_with((string) $left, (string) $right),
            'not starts_with'   => !str_starts_with((string) $left, (string) $right),
            'ends_with'         => str_ends_with((string) $left, (string) $right),
            'not ends_with'     => !str_ends_with((string) $left, (string) $right),

            'in'        => is_array($right) && in_array($left, $right, strict: false),
            'not in'    => is_array($right) && !in_array($left, $right, strict: false),

            'between'   => self::between($left, $right),
            'not between' => !self::between($left, $right),

            'regexp'    => self::regexp((string) $left, (string) $right),
            'not regexp' => !self::regexp((string) $left, (string) $right),

            default     => throw new InvalidQueryException(
                "Unsupported operator: '{$operator}'. Supported operators: =, !=, <>, <, <=, >, >=, like, not like, ilike, contains, starts_with, ends_with, in, not in, between, not between, regexp, not regexp."
            ),
        };
    }

    /**
     * SQL-style LIKE pattern matching.
     * Supports % (any chars) and _ (single char) wildcards.
     */
    private static function likeMatch(string $value, string $pattern, bool $caseSensitive = true): bool
    {
        // Escape regex meta characters except % and _
        $regexPattern = preg_quote($pattern, '/');

        // Replace SQL wildcards with regex equivalents
        $regexPattern = str_replace(['%', '_'], ['.*', '.'], $regexPattern);
        $regexPattern = '/^' . $regexPattern . '$/';

        if (!$caseSensitive) {
            $regexPattern .= 'i';
        }

        return (bool) preg_match($regexPattern, $value);
    }

    /**
     * Check if a value falls within a range [min, max].
     * Expects $range to be an array with exactly 2 elements: [min, max].
     */
    private static function between(mixed $value, mixed $range): bool
    {
        if (!is_array($range) || count($range) !== 2) {
            throw new InvalidQueryException('BETWEEN operator requires an array with exactly 2 elements: [min, max].');
        }

        [$min, $max] = $range;
        return $value >= $min && $value <= $max;
    }

    /**
     * Match a value against a regular expression.
     * Automatically adds delimiters if missing.
     */
    private static function regexp(string $value, string $pattern): bool
    {
        // Wrap in delimiters if the pattern doesn't have them
        if (!preg_match('/^([\/~#|]).*\1[imsxuADJU]*$/', $pattern)) {
            $pattern = '/' . $pattern . '/';
        }

        return (bool) preg_match($pattern, $value);
    }
}
