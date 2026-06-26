<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Illuminate\Contracts\Support\Arrayable;
use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Traits\Description;
use RoundlyConsulting\Metrics\Traits\Humanize;
use RoundlyConsulting\Metrics\Traits\Makeable;
use RoundlyConsulting\Metrics\Traits\Name;
use RoundlyConsulting\Metrics\Traits\Prefix;
use RoundlyConsulting\Metrics\Traits\Ranges;
use RoundlyConsulting\Metrics\Traits\Rounding;
use RoundlyConsulting\Metrics\Traits\Suffix;

/**
 * @phpstan-consistent-constructor
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Metrics implements Arrayable
{
    use Description;
    use Humanize;
    use Makeable;
    use Name;
    use Prefix;
    use Ranges;
    use Rounding;
    use Suffix;

    public function __construct()
    {
        $this->setup();
    }

    protected function setup(): void
    {
        //
    }

    abstract protected function calculate(): Result;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => filled($this->name) ? $this->name : $this->humanize(class_basename($this)),
            'description' => $this->description,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'range' => [
                'current' => $this->range,
                'available' => $this->ranges(),
                'custom' => [
                    'start' => $this->customRangeStart,
                    'end' => $this->customRangeEnd,
                ],
            ],
            'result' => $this->calculate()->toArray(),
        ];
    }
}
