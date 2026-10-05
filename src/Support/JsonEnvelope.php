<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

/**
 * A metric envelope shaped for a JSON response. Its keyed maps — a trend's buckets and
 * series, a partition's groups, labels and formatted values — become objects: a map whose
 * keys happen to run 0, 1, … would otherwise encode as a JSON array, so the response shape
 * would flip with the data. `toArray()` keeps plain arrays, because it feeds the cache.
 *
 * @internal
 */
final class JsonEnvelope
{
    private const array MAPS = ['trends', 'partitions', 'labels', 'formatted'];

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public static function of(array $envelope): array
    {
        $result = $envelope['result'] ?? null;

        if (! is_array($result)) {
            return $envelope;
        }

        foreach (self::MAPS as $map) {
            if (is_array($result[$map] ?? null)) {
                $result[$map] = (object) $result[$map];
            }
        }

        if (is_array($result['series'] ?? null)) {
            $result['series'] = (object) array_map(
                fn (mixed $buckets): mixed => is_array($buckets) ? (object) $buckets : $buckets,
                $result['series'],
            );
        }

        $envelope['result'] = $result;

        return $envelope;
    }
}
