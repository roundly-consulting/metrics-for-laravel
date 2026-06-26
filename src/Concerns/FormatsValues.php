<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Closure;

trait FormatsValues
{
    /**
     * @var (Closure(float): string)|null
     */
    private ?Closure $formatter = null;

    /**
     * Format the metric's numeric value(s) into a display string, exposed under
     * a "formatted" key alongside the raw result.
     *
     * @param  Closure(float): string  $formatter
     */
    public function formatUsing(Closure $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function applyFormatting(array $result): array
    {
        if ($this->formatter === null) {
            return $result;
        }

        $formatter = $this->formatter;

        if (array_key_exists('value', $result)) {
            $result['formatted'] = $formatter((float) $result['value']);
        } elseif (array_key_exists('trends', $result) && is_array($result['trends'])) {
            $result['formatted'] = array_map(static fn (mixed $value): string => $formatter((float) $value), $result['trends']);
        } elseif (array_key_exists('partitions', $result) && is_array($result['partitions'])) {
            $result['formatted'] = array_map(static fn (mixed $value): string => $formatter((float) $value), $result['partitions']);
        }

        return $result;
    }
}
