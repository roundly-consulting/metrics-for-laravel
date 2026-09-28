<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Models\User;

/**
 * An inline builder keeps the Eloquent query it was given. The trend and partition SQL is
 * built on that query's base builder, and `applyScopes()` returns the builder itself when
 * the model has no global scopes — so each calculation used to add its select, its range
 * `whereBetween` and its grouping to the metric's own query. A second calculation then ran
 * with both windows applied and came back empty.
 */
beforeEach(function (): void {
    // Test "now" is 2023-03-10 10:00:00.
    createUsersForMetricsTesting([
        ['balance' => 1, 'type' => 'a', 'created_at' => '2023-03-10 09:00:00'],
        ['balance' => 1, 'type' => 'b', 'created_at' => '2023-03-09 09:00:00'],
    ]);
});

it('recalculates a trend under a second range without the first one stuck to its query', function (): void {
    $trend = Metrics::trend()->count(User::query(), 'id')->daily();

    $today = $trend->range('TODAY')->result()->trends();
    $yesterday = $trend->range('YESTERDAY')->result()->trends();

    expect($today)->toBe(['2023-03-10' => 1.0])
        ->and($yesterday)->toBe(['2023-03-09' => 1.0]);
});

it('recalculates a partition under a second range without the first one stuck to its query', function (): void {
    $partition = Metrics::partition()->count(User::query(), 'type');

    $today = $partition->range('TODAY')->result()->partitions();
    $yesterday = $partition->range('YESTERDAY')->result()->partitions();

    expect($today)->toBe(['a' => 1.0])
        ->and($yesterday)->toBe(['b' => 1.0]);
});

it('leaves the query it was given untouched', function (): void {
    $query = User::query();
    $sql = $query->toSql();

    Metrics::trend()->count($query, 'id')->range('TODAY')->result();
    Metrics::partition()->count($query, 'type')->range('TODAY')->result();

    expect($query->toSql())->toBe($sql);
});
