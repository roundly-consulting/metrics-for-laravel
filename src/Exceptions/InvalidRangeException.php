<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

use Exception;

final class InvalidRangeException extends Exception
{
    public static function for(string $range): static
    {
        return new self("Provided `{$range}` could not be resolved.");
    }
}
