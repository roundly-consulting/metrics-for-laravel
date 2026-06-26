<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

abstract class BaseRange implements Range
{
    protected bool $previous = false;

    public function previous(): Range
    {
        $instance = clone $this;
        $instance->previous = true;

        return $instance;
    }
}
