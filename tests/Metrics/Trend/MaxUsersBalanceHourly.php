<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Trend;

use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Trend\Trend;

class MaxUsersBalanceHourly extends Trend
{
    protected function calculate(): Result
    {
        return $this->hourly()
            ->max(User::query(), 'balance');
    }
}
