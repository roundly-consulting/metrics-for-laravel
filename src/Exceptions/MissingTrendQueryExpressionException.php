<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

use Exception;

final class MissingTrendQueryExpressionException extends Exception
{
    public static function forDriver(string $driver): static
    {
        return new self("Missing trend query expression for `{$driver}` driver.");
    }
}
