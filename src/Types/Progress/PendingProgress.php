<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Progress;

use RoundlyConsulting\Metrics\Concerns\AggregatesValues;
use RoundlyConsulting\Metrics\Concerns\BuildsValueMetric;
use RoundlyConsulting\Metrics\Concerns\CalculatesProgress;
use RoundlyConsulting\Metrics\Metrics;

final class PendingProgress extends Metrics
{
    use AggregatesValues;
    use BuildsValueMetric;
    use CalculatesProgress {
        CalculatesProgress::resolveResult insteadof AggregatesValues;
        CalculatesProgress::cacheDiscriminators insteadof AggregatesValues;
    }
}
