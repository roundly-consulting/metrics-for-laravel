<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Progress;

use RoundlyConsulting\Metrics\Types\Result;

final class ProgressResult implements Result
{
    public function __construct(
        protected float $value,
        protected float $target,
        protected float $progress,
        protected bool $avoid,
        protected ?float $previous = null,
        protected ?float $previousProgress = null,
    ) {}

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
                'is_increase' => $this->value > $this->previous,
                'progress' => $this->progress - $this->previousProgress,
                'value' => $this->value - $this->previous,
            ],
        ];
    }
}
