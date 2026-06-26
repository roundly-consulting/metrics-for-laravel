<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

interface Range
{
    public function previous(): Range;

    public function start(): CarbonImmutable;

    public function end(): CarbonImmutable;
}
