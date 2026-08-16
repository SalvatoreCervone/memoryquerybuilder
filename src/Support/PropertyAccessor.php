<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Support;

/**
 * Universal property accessor for arrays and objects.
 * Supports dot-notation (e.g. 'user.address.city') for deep nested access.
 * Handles: associative arrays, stdClass, objects with getters, ArrayAccess.
 */
class PropertyAccessor
{
    /**
     * Retrieve a value from an array or object using dot-notation.
     * Returns $default if any segment of the path is not found.
     */
    public static function get(mixed $item, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        // Fast path: no dot → single-level access
        if (!str_contains($key, '.')) {
            return self::getSegment($item, $key, $default);
        }

        $segments = explode('.', $key);
        $current = $item;

        foreach ($segments as $segment) {
            if ($current === null) {
                return $default;
            }

            $current = self::getSegment($current, $segment, null);

            if ($current === null) {
                return $default;
            }
        }

        return $current ?? $default;
    }

    /**
     * Get a single segment value from an array or object.
     */
    private static function getSegment(mixed $item, string $key, mixed $default): mixed
    {
        // Array access (covers both plain arrays and ArrayAccess)
        if (is_array($item)) {
            return array_key_exists($key, $item) ? $item[$key] : $default;
        }

        if ($item instanceof \ArrayAccess) {
            return isset($item[$key]) ? $item[$key] : $default;
        }

        if (is_object($item)) {
            // Try camelCase getter first: 'first_name' → getFirstName()
            $getter = 'get' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($item, $getter)) {
                return $item->{$getter}();
            }

            // Try isXxx() getter for booleans: 'active' → isActive()
            $isGetter = 'is' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($item, $isGetter)) {
                return $item->{$isGetter}();
            }

            // Direct public property access (check visibility via Reflection to avoid fatal on private)
            if (property_exists($item, $key)) {
                try {
                    $rp = new \ReflectionProperty($item, $key);
                    if ($rp->isPublic()) {
                        return $item->{$key};
                    }
                } catch (\ReflectionException) {
                    // fall through
                }
            }

            // Try magic __get
            if (method_exists($item, '__get')) {
                return $item->{$key};
            }
        }

        return $default;
    }

    /**
     * Check if a key exists on an item (array or object).
     */
    public static function has(mixed $item, string $key): bool
    {
        $value = self::get($item, $key, '__MQB_UNDEFINED__');
        return $value !== '__MQB_UNDEFINED__';
    }
}
