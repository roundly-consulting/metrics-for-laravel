<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Ranges\ThisYear;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('resolves ranges in a per-metric timezone override', function (): void {
    // "now" is fixed at 2023-03-10 10:00:00 UTC. In Pacific/Kiritimati (+14)
    // that is already 2023-03-11, so a row created on 2023-03-11 is "today"
    // there but not in UTC.
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-03-11 05:00:00'],
    ]);

    $utc = Users::make()->range('TODAY')->toArray();
    $kiritimati = Users::make()->range('TODAY')->timezone('Pacific/Kiritimati')->toArray();

    expect($utc['result']['value'])->toBe(0.0)
        ->and($kiritimati['result']['value'])->toBe(1.0);
});

it('overrides the configured timezone on the range itself', function (): void {
    config()->set('metrics.timezone', 'Asia/Tokyo');

    $range = (new ThisYear)->usingTimezone('America/New_York');

    expect($range->start()->getTimezone()->getName())->toBe('America/New_York');
});
