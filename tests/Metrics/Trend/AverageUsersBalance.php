<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Trend;

use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Trend\Trend;
use RoundlyConsulting\Metrics\Tests\Models\User;

class AverageUsersBalance extends Trend
{
    protected function calculate(): Result
    {
        return $this->average(User::query(), 'balance');
    }
}
