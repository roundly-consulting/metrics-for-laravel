<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types;

interface Result
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
