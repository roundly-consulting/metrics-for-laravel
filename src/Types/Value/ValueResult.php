<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Value;

use RoundlyConsulting\Metrics\Types\Result;

final class ValueResult implements Result
{
    public function __construct(
        protected float $value,
        protected ?float $previous = null,
        protected ?float $change = null
    ) {}

    public function value(): float
    {
        return $this->value;
    }

    public function previous(): ?float
    {
        return $this->previous;
    }

    public function change(): ?float
    {
        return $this->change;
    }

    public function isIncrease(): bool
    {
        return $this->value > $this->previous;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'previous' => $this->previous,
            'change' => [
                'percentage' => $this->change,
                'is_increase' => $this->value > $this->previous,
            ],
        ];
    }
}
