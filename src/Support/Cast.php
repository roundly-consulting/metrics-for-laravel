<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

/**
 * Narrow the untyped values of a cached result envelope back to the types the result
 * objects declare.
 *
 * @internal
 */
final class Cast
{
    public static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return array<string, float>
     */
    public static function floats(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $floats = [];

        foreach ($values as $key => $value) {
            $floats[(string) $key] = self::float($value);
        }

        return $floats;
    }

    /**
     * @return array<string, string>
     */
    public static function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $key => $value) {
            $strings[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        return $strings;
    }
}
