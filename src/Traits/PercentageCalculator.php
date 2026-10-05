<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use RoundingMode;

trait PercentageCalculator
{
    /**
     * The change from `$previous` to `$current` as a percentage of the previous value's size,
     * so its sign always says which way the value moved — from -100 to -50 is +50%. From
     * zero, any rise is +100%, any fall -100%.
     */
    public function calculateChangePercentage(
        float $current,
        float $previous,
        int $roundingPrecision = 0,
        RoundingMode $roundingMode = RoundingMode::HalfAwayFromZero,
    ): float {
        if ($previous === 0.0) {
            $change = ($current <=> 0.0) * 100.0;
        } else {
            $change = (($current - $previous) / abs($previous)) * 100;
        }

        return round($change, $roundingPrecision, $roundingMode);
    }

    public function calculatePercentage(
        float $current,
        float $max,
        int $roundingPrecision = 0,
        RoundingMode $roundingMode = RoundingMode::HalfAwayFromZero,
    ): float {
        if ($max === 0.0) {
            $result = $current > 0.0 ? 100.0 : 0.0;
        } else {
            $result = ($current / $max) * 100;
        }

        return round($result, $roundingPrecision, $roundingMode);
    }
}
