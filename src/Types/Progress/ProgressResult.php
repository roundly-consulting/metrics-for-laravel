<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Progress;

use RoundlyConsulting\Metrics\Support\Cast;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

/**
 * A value measured against a target. It is a {@see ValueResult}, so `value()`,
 * `previous()`, `change()` and `isIncrease()` read the same as on a value metric.
 */
final class ProgressResult extends ValueResult
{
    public function __construct(
        float $value,
        protected float $target,
        protected float $progress,
        protected bool $avoid,
        ?float $previous = null,
        protected ?float $previousProgress = null,
        ?float $change = null,
    ) {
        parent::__construct($value, $previous, $change);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $change = is_array($data['change'] ?? null) ? $data['change'] : [];

        return new self(
            value: Cast::float($data['value'] ?? null),
            target: Cast::float($data['target'] ?? null),
            progress: Cast::float($data['progress'] ?? null),
            avoid: ($data['avoid'] ?? false) === true,
            previous: Cast::nullableFloat($data['previous'] ?? null),
            previousProgress: Cast::nullableFloat($data['previous_progress'] ?? null),
            change: Cast::nullableFloat($change['percentage'] ?? null),
        );
    }

    public function target(): float
    {
        return $this->target;
    }

    public function progress(): float
    {
        return $this->progress;
    }

    public function previousProgress(): ?float
    {
        return $this->previousProgress;
    }

    /**
     * Whether the target is a ceiling to stay under rather than a goal to reach.
     */
    public function avoid(): bool
    {
        return $this->avoid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'progress' => $this->progress,
            'previous' => $this->previous,
            'previous_progress' => $this->previousProgress,
            'target' => $this->target,
            'avoid' => $this->avoid,
            'change' => [
                'percentage' => $this->change,
                'is_increase' => $this->value > $this->previous,
                'progress' => $this->progress - $this->previousProgress,
                'value' => $this->value - $this->previous,
            ],
        ];
    }
}
