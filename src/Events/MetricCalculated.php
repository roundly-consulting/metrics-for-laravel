<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Events;

/**
 * Fired every time a metric's result is resolved, for instrumentation and
 * slow-metric logging. The key is present for metrics resolved through the
 * registry and null for ad-hoc builders.
 */
final class MetricCalculated
{
    public function __construct(
        public readonly ?string $key,
        public readonly string $range,
        public readonly float $durationMs,
        public readonly bool $fromCache,
    ) {}
}
