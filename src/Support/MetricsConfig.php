<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;

/**
 * Strict string reads of the host's metrics settings: an unset (null) key takes the
 * default; anything else must be a non-empty string or the read throws the package's
 * {@see InvalidConfigurationException} naming the key — never a silent fallback.
 *
 * @internal
 */
final class MetricsConfig
{
    public static function string(string $key, mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [%s] must be a non-empty string, [%s] given.',
                $key,
                is_scalar($value) ? var_export($value, true) : get_debug_type($value),
            ));
        }

        return $value;
    }

    /** An optional string setting: null stays null, anything else must be a non-empty string. */
    public static function optionalString(string $key, mixed $value): ?string
    {
        return $value === null ? null : self::string($key, $value, '');
    }
}
