<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RoundlyConsulting\Metrics\Enums\Period;

/**
 * Resolves a group of registered metrics into a single keyed envelope, sharing
 * one date range. Authorization stays the host application's responsibility.
 *
 * @implements Arrayable<string, mixed>
 */
final class Dashboard implements Arrayable, Responsable
{
    private ?string $range = null;

    private ?string $customRangeStart = null;

    private ?string $customRangeEnd = null;

    private ?string $timezone = null;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        private readonly MetricsManager $manager,
        private readonly array $keys,
    ) {}

    /**
     * Apply a shared range to every metric in the dashboard.
     */
    public function range(Period|string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null): self
    {
        $this->range = $range instanceof Period ? $range->value : $range;
        $this->customRangeStart = $customRangeStart;
        $this->customRangeEnd = $customRangeEnd;

        return $this;
    }

    /**
     * Resolve every metric in a shared timezone.
     */
    public function timezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $envelope = [];

        foreach ($this->keys as $key) {
            $metric = $this->manager->get($key);

            if ($this->range !== null) {
                $metric->range($this->range, $this->customRangeStart, $this->customRangeEnd);
            }

            if ($this->timezone !== null) {
                $metric->timezone($this->timezone);
            }

            $envelope[$key] = $metric->toArray();
        }

        return $envelope;
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray());
    }
}
