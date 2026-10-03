<?php

use RoundlyConsulting\Metrics\Tests\Fixtures\PublishSandboxTestCase;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Tests\TestCase;

// Every top-level entry except Publish/, not `->in(__DIR__)`: Pest binds one test case per
// file and throws TestCaseAlreadyInUse when a blanket bind and a directory bind both claim
// it. Globbing keeps the blanket default for every other file, new ones included.
uses(TestCase::class)->in(...array_diff(glob(__DIR__.'/*') ?: [], [__DIR__.'/Publish']));

// Publishing writes files: into a throwaway config/ set before boot, never the testbench
// skeleton every parallel process loads its configuration from.
uses(PublishSandboxTestCase::class)->in('Publish');

function createUsersForMetricsTesting(array $balances = [100]): void
{
    User::query()->insert(
        array_map(fn (float|array $balance) => is_array($balance) ? $balance : ['balance' => $balance], $balances)
    );
}
