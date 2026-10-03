<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;

/**
 * Strict string reads of the host's metrics settings: a key that is not set (null, or blank
 * — `''` or whitespace, a host's `KEY=`) takes the default; anything else must be a string or
 * the read throws the package's {@see InvalidConfigurationException} naming the key — never
 * a silent fallback.
 *
 * @internal
 */
final class MetricsConfig
{
    public static function string(string $key, mixed $value, string $default): string
    {
        if (self::isUnset($value)) {
            return $default;
        }

        if (! is_string($value)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [%s] must be a non-empty string, [%s] given.',
                $key,
                is_scalar($value) ? var_export($value, true) : get_debug_type($value),
            ));
        }

        return $value;
    }

    /** An optional string setting: not set (null or blank) is null, anything else must be a string. */
    public static function optionalString(string $key, mixed $value): ?string
    {
        return self::isUnset($value) ? null : self::string($key, $value, '');
    }

    /** Not set: null, or a blank string (`''` or whitespace — a host's `KEY=`). */
    public static function isUnset(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
