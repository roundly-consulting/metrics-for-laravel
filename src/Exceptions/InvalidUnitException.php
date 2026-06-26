<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

final class InvalidUnitException extends MetricsException
{
    public static function for(string $unit): static
    {
        return new self("Provided `{$unit}` could not be mapped to correct value.");
    }
}
