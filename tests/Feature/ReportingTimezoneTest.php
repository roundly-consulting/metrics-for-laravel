<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users as UsersTrend;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users as UsersValue;
use RoundlyConsulting\Metrics\Tests\Models\User;

/**
 * The reporting timezone (`metrics.timezone`, `timezone()`, `Dashboard::timezone()`) is the
 * zone a range is *resolved* and a trend is *labelled* in. The rows themselves are stored in
 * the application timezone (UTC here), the way Eloquent writes them.
 *
 * Laravel binds a Carbon as its bare `Y-m-d H:i:s` wall clock, so a range resolved in
 * Europe/Bratislava used to be applied to UTC data as if it were UTC: every window was
 * shifted by the offset, and trend buckets were keyed by the UTC hour/day.
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-09-15 10:00:00');
    config()->set('metrics.timezone', 'Europe/Bratislava');
});

it('converts a reporting-timezone range to the storage timezone before querying', function (): void {
    // 23:30 UTC on the 14th is 01:30 on the 15th in Bratislava (CEST, +2): today there.
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => '2026-09-14 23:30:00']]);

    expect(UsersValue::make()->range('TODAY')->result()->value())->toBe(1.0)
        ->and(UsersValue::make()->range('YESTERDAY')->result()->value())->toBe(0.0);
});

it('reads a custom range as reporting-timezone wall clock', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-09-14 22:30:00'], // 00:30 on the 15th, local
        ['balance' => 10, 'created_at' => '2026-09-15 22:30:00'], // 00:30 on the 16th, local
    ]);

    $value = UsersValue::make('sum', 'balance')->range('CUSTOM', '2026-09-15 00:00:00', '2026-09-15 23:59:59')->result()->value();

    expect($value)->toBe(1.0);
});

it('labels hourly trend buckets in the reporting timezone', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => '2026-09-14 22:30:00']]);

    $trends = UsersTrend::make()->hourly()->range('TODAY')->result()->trends();

    expect($trends['2026-09-15 00:00'])->toBe(1.0)
        ->and(array_sum($trends))->toBe(1.0)
        ->and(array_key_first($trends))->toBe('2026-09-15 00:00')
        ->and(array_key_last($trends))->toBe('2026-09-15 23:00');
});

it('labels daily buckets across a daylight-saving change', function (): void {
    // Bratislava leaves CEST (+2) for CET (+1) at 01:00 UTC on 2026-10-25.
    Carbon::setTestNow('2026-10-27 10:00:00');

    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-10-23 22:30:00'], // 24th 00:30 CEST
        ['balance' => 1, 'created_at' => '2026-10-24 22:30:00'], // 25th 00:30 CEST
        ['balance' => 1, 'created_at' => '2026-10-25 22:30:00'], // 25th 23:30 CET
        ['balance' => 1, 'created_at' => '2026-10-25 23:30:00'], // 26th 00:30 CET
    ]);

    $trends = UsersTrend::make()->daily()
        ->range('CUSTOM', '2026-10-24 00:00:00', '2026-10-26 23:59:59')
        ->result()->trends();

    expect($trends)->toBe([
        '2026-10-24' => 1.0,
        '2026-10-25' => 2.0,
        '2026-10-26' => 1.0,
    ]);
});

it('labels an all-time trend in the reporting timezone', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-09-13 23:30:00'], // 14th local
        ['balance' => 1, 'created_at' => '2026-09-14 23:30:00'], // 15th local
    ]);

    expect(Metrics::trend()->count(User::query(), 'id')->daily()->range('ALL')->result()->trends())->toBe([
        '2026-09-14' => 1.0,
        '2026-09-15' => 1.0,
    ]);
});

it('labels every series of a grouped trend in the reporting timezone', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'created_at' => '2026-09-14 23:30:00'],
        ['type' => 'free', 'created_at' => '2026-09-14 21:30:00'],
    ]);

    $result = UsersTrend::make()->daily()->range('CUSTOM', '2026-09-14 00:00:00', '2026-09-15 23:59:59')->groupBy('type')->result();

    expect($result->series())->toBe([
        'free' => ['2026-09-14' => 1.0, '2026-09-15' => 0.0],
        'pro' => ['2026-09-14' => 0.0, '2026-09-15' => 1.0],
    ])->and($result->trends())->toBe(['2026-09-14' => 1.0, '2026-09-15' => 1.0]);
});

it('filters partitions by the converted range', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'pro', 'created_at' => '2026-09-14 23:30:00'], // today, local
        ['type' => 'free', 'created_at' => '2026-09-14 21:30:00'], // yesterday, local
    ]);

    expect(Metrics::partition()->count(User::query(), 'type')->range('TODAY')->result()->partitions())
        ->toBe(['pro' => 1.0]);
});

it('honours a per-metric timezone over the configured one', function (): void {
    config()->set('metrics.timezone', null);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => '2026-09-14 23:30:00']]);

    expect(UsersValue::make()->range('TODAY')->result()->value())->toBe(0.0)
        ->and(UsersValue::make()->range('TODAY')->timezone('Europe/Bratislava')->result()->value())->toBe(1.0)
        ->and(UsersTrend::make()->hourly()->range('TODAY')->timezone('Europe/Bratislava')->result()->trends()['2026-09-15 01:00'])->toBe(1.0);
});

it('applies a dashboard timezone to the query, not just the labels', function (): void {
    config()->set('metrics.timezone', null);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => '2026-09-14 23:30:00']]);

    Metrics::register('users', UsersValue::class);

    expect(Metrics::dashboard(['users'])->range('TODAY')->timezone('Europe/Bratislava')->toArray()['users']['result']['value'])
        ->toBe(1.0);
});

it('handles a half-hour offset', function (): void {
    config()->set('metrics.timezone', 'Asia/Kolkata'); // +05:30, no DST

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => '2026-09-14 18:45:00']]); // 00:15 on the 15th

    $trends = UsersTrend::make()->hourly()->range('TODAY')->result()->trends();

    expect($trends['2026-09-15 00:00'])->toBe(1.0)
        ->and(array_sum($trends))->toBe(1.0);
});
