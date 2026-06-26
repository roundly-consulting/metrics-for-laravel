<?php

use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function createUsersForMetricsTesting(array $balances = [100]): void
{
    User::query()->insert(
        array_map(fn (float|array $balance) => is_array($balance) ? $balance : ['balance' => $balance], $balances)
    );
}
