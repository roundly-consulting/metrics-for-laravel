<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

final class UnexpectedResultException extends MetricsException
{
    public static function for(string $metric, string $expected, string $actual): static
    {
        return new self("Metric [{$metric}] must calculate a [{$expected}], but calculated a [{$actual}].");
    }
}
