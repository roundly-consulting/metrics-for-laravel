<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\QueryExpressions\ConstantKeyQueryExpression;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;

it('fills empty buckets with zero by default', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->daily()->toArray();

    expect($metrics['result']['trends'])->toBe([
        '2023-02-26' => 100.0,
        '2023-02-27' => 0.0,
        '2023-02-28' => 300.0,
    ]);
});

it('omits empty buckets when gap filling is disabled', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 300, 'created_at' => '2023-02-28 10:00:00'],
    ]);

    $metrics = UsersBalance::make()->daily()->withoutGapFilling()->toArray();

    expect($metrics['result']['trends'])->toBe([
        '2023-02-26' => 100.0,
        '2023-02-28' => 300.0,
    ]);
});

/**
 * The gap-filled axis used to step from the raw range start, and only the keys it generated
 * survived. When the start did not sit on a bucket boundary the steps skipped the bucket
 * that held "now" — or, for months, overflowed straight past a shorter month — and every
 * row in the skipped bucket vanished from the trend.
 */
describe('bucket alignment', function (): void {
    beforeEach(function (): void {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00'); // a Monday

        createUsersForMetricsTesting([
            ['balance' => 1, 'created_at' => '2026-09-28 09:00:00'],
            ['balance' => 1, 'created_at' => '2026-09-28 10:00:00'],
            ['balance' => 1, 'created_at' => '2026-09-22 10:00:00'],
        ]);
    });

    it('keeps the current week of a weekly year-to-date trend', function (): void {
        $trends = Users::make()->weekly()->range('YTD')->result()->trends();

        expect(array_key_first($trends))->toBe('2026-01')
            ->and(array_key_last($trends))->toBe('2026-40')
            ->and($trends['2026-40'])->toBe(2.0)
            ->and(array_sum($trends))->toBe(3.0);
    });

    it('keeps the current week of a weekly month-to-date trend', function (): void {
        expect(Users::make()->weekly()->range('MTD')->result()->trends())->toBe([
            '2026-36' => 0.0,
            '2026-37' => 0.0,
            '2026-38' => 0.0,
            '2026-39' => 1.0,
            '2026-40' => 2.0,
        ]);
    });

    it('keeps the current month of a monthly day-count trend', function (): void {
        expect(Users::make()->monthly()->range('90')->result()->trends())->toBe([
            '2026-06' => 0.0,
            '2026-07' => 0.0,
            '2026-08' => 0.0,
            '2026-09' => 3.0,
        ]);
    });

    it('keeps the current year of a yearly day-count trend', function (): void {
        expect(Users::make()->yearly()->range('365')->result()->trends())->toBe([
            '2025' => 0.0,
            '2026' => 3.0,
        ]);
    });
});

it('does not overflow past a short month when stepping monthly', function (): void {
    CarbonImmutable::setTestNow('2026-03-31 12:00:00');

    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-01-31 13:00:00'],
        ['balance' => 1, 'created_at' => '2026-02-15 09:00:00'],
        ['balance' => 1, 'created_at' => '2026-03-30 09:00:00'],
    ]);

    // 60 days before 2026-03-31 is 2026-01-30; +1 month from there used to land on 03-02.
    expect(Users::make()->monthly()->range('60')->result()->trends())->toBe([
        '2026-01' => 1.0,
        '2026-02' => 1.0,
        '2026-03' => 1.0,
    ]);
});

it('never drops a bucket the database returned, even one the axis did not generate', function (): void {
    config()->set('metrics.trend_drivers', [
        DB::connection()->getDriverName() => ConstantKeyQueryExpression::class,
    ]);

    createUsersForMetricsTesting([['balance' => 5, 'created_at' => '2023-03-10 09:00:00']]);

    // The driver keys every row as 'x'. The axis never generates it; the row must survive.
    expect(UsersBalance::make()->daily()->range('TODAY')->result()->trends())->toBe([
        '2023-03-10' => 0.0,
        'x' => 5.0,
    ]);
});
