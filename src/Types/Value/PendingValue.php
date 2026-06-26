<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Value;

use RoundlyConsulting\Metrics\Concerns\AggregatesValues;
use RoundlyConsulting\Metrics\Concerns\BuildsValueMetric;
use RoundlyConsulting\Metrics\Metrics;

final class PendingValue extends Metrics
{
    use AggregatesValues;
    use BuildsValueMetric;
}
