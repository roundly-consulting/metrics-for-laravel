<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Fake;

use RoundlyConsulting\Metrics\Types\Result;

final class FakeResult implements Result
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(private readonly array $result) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->result;
    }
}
