<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Progress;

use RoundlyConsulting\Metrics\Concerns\CalculatesProgress;
use RoundlyConsulting\Metrics\Types\Value\Value;

abstract class Progress extends Value
{
    use CalculatesProgress;
}
