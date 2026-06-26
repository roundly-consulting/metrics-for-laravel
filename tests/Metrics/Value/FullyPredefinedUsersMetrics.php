<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Value;

use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Value\Value;
use RoundlyConsulting\Metrics\Tests\Models\User;

class FullyPredefinedUsersMetrics extends Value
{
    protected function setup(): void
    {
        $this
            ->name('My custom name')
            ->description('My custom description')
            ->prefix('Balance:')
            ->suffix('USD')
            ->precision(4)
            ->range('TODAY')
            ->withChangeAgainstPreviousPeriod();
    }

    protected function calculate(): Result
    {
        return $this->sum(User::query(), 'balance');
    }
}
