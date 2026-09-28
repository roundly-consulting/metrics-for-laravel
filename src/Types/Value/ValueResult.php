<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Value;

use RoundlyConsulting\Metrics\Support\Cast;
use RoundlyConsulting\Metrics\Types\Progress\ProgressResult;
use RoundlyConsulting\Metrics\Types\Result;

/**
 * A single number, optionally with the previous period's value and the percentage
 * change between them. Not final: {@see ProgressResult} is a value result with a target.
 */
class ValueResult implements Result
{
    public function __construct(
        protected float $value,
        protected ?float $previous = null,
        protected ?float $change = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $change = is_array($data['change'] ?? null) ? $data['change'] : [];

        return new self(
            value: Cast::float($data['value'] ?? null),
            previous: Cast::nullableFloat($data['previous'] ?? null),
            change: Cast::nullableFloat($change['percentage'] ?? null),
        );
    }

    public function value(): float
    {
        return $this->value;
    }

    public function previous(): ?float
    {
        return $this->previous;
    }

    /**
     * The percentage change against the previous value, when a comparison was requested.
     */
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
