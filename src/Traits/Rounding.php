<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use RoundingMode;

trait Rounding
{
    protected int $roundingPrecision = 0;

    protected RoundingMode $roundingMode = RoundingMode::HalfAwayFromZero;

    public function precision(int $precision = 0, RoundingMode $mode = RoundingMode::HalfAwayFromZero): self
    {
        $this->roundingPrecision = $precision;
        $this->roundingMode = $mode;

        return $this;
    }
}
