<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Makeable
{
    /**
     * @no-named-arguments
     */
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }
}
