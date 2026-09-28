<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Progress;

use RoundlyConsulting\Metrics\Concerns\AggregatesValues;
use RoundlyConsulting\Metrics\Concerns\BuildsValueMetric;
use RoundlyConsulting\Metrics\Concerns\CalculatesProgress;
use RoundlyConsulting\Metrics\Metric;

final class PendingProgress extends Metric
{
    use AggregatesValues;
    use BuildsValueMetric;
    use CalculatesProgress {
        CalculatesProgress::resolveResult insteadof AggregatesValues;
        CalculatesProgress::cacheDiscriminators insteadof AggregatesValues;
        CalculatesProgress::result insteadof AggregatesValues;
    }
}
