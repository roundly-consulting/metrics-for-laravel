<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Value;

use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Value\Value;

/**
 * A class-based metric whose result depends on its constructor state — the shape of a
 * per-tenant metric. `$extra` carries state the metric does not read, so the cache key's
 * handling of each kind of value can be tested on its own.
 */
class ScopedUsers extends Value
{
    public function __construct(protected ?User $owner = null, protected mixed $extra = null)
    {
        parent::__construct();
    }

    protected function calculate(): Result
    {
        $query = User::query();

        if ($this->owner !== null) {
            $query->where('type', $this->owner->type);
        }

        return $this->count($query);
    }
}
