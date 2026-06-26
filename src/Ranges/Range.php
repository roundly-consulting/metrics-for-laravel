<?php

namespace RoundlyConsulting\Metrics\Ranges;

use Carbon\CarbonImmutable;

interface Range
{
    public function previous(): Range;

    public function start(): CarbonImmutable;

    public function end(): CarbonImmutable;
}
