<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Metrics;

interface Result
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
