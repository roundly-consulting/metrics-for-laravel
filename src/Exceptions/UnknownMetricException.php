<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

final class UnknownMetricException extends MetricsException
{
    public static function forKey(string $key): static
    {
        return new self("No metric is registered under the `{$key}` key.");
    }
}
