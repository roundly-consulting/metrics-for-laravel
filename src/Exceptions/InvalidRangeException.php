<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

final class InvalidRangeException extends MetricsException
{
    public static function for(string $range): static
    {
        return new self("Provided `{$range}` could not be resolved.");
    }
}
