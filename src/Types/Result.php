<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types;

interface Result
{
    /**
     * Rebuild the result from its {@see toArray()} form — how a cached result comes back.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
