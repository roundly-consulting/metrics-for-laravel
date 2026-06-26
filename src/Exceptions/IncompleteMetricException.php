<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

final class IncompleteMetricException extends MetricsException
{
    public static function missingQuery(): static
    {
        return new self('No query was provided. Call one of count(), sum(), average(), max() or min() before resolving the metric.');
    }
}
