<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\LastQuarter;
use RoundlyConsulting\Metrics\Ranges\QuarterToDate;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Ranges\ThisQuarter;
use RoundlyConsulting\Metrics\Ranges\YearToDate;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

function formatRange(Range $range): string
{
    return $range->start()->toDateTimeString().' .. '.$range->end()->toDateTimeString();
}

/**
 * `subQuarter()` overflows: 2026-12-31 minus three months is "2026-09-31", which rolls
 * over to 2026-10-01 — still the current quarter. On every month-end day that has no twin
 * three months earlier, "last quarter" was this quarter and the change was 0%.
 */
it('resolves quarter ranges on month-end days without overflowing', function (string $now, string $range, string $expected): void {
    CarbonImmutable::setTestNow($now);

    $resolved = match ($range) {
        'last' => new LastQuarter,
        'last previous' => (new LastQuarter)->previous(),
        'this previous' => (new ThisQuarter)->previous(),
        'qtd previous' => (new QuarterToDate)->previous(),
    };

    expect(formatRange($resolved))->toBe($expected);
})->with([
    'dec 31 last quarter' => ['2026-12-31 12:00:00', 'last', '2026-07-01 00:00:00 .. 2026-09-30 23:59:59'],
    'dec 31 before last quarter' => ['2026-12-31 12:00:00', 'last previous', '2026-04-01 00:00:00 .. 2026-06-30 23:59:59'],
    'dec 31 this quarter previous' => ['2026-12-31 12:00:00', 'this previous', '2026-07-01 00:00:00 .. 2026-09-30 23:59:59'],
    'dec 31 qtd previous' => ['2026-12-31 12:00:00', 'qtd previous', '2026-07-01 00:00:00 .. 2026-09-30 12:00:00'],
    'mar 31 before last quarter' => ['2026-03-31 12:00:00', 'last previous', '2025-07-01 00:00:00 .. 2025-09-30 23:59:59'],
    'may 31 last quarter' => ['2026-05-31 12:00:00', 'last', '2026-01-01 00:00:00 .. 2026-03-31 23:59:59'],
    'may 31 qtd previous' => ['2026-05-31 12:00:00', 'qtd previous', '2026-01-01 00:00:00 .. 2026-02-28 12:00:00'],
]);

it('compares year to date on a leap day against the same point last year', function (): void {
    CarbonImmutable::setTestNow('2028-02-29 12:00:00');

    expect(formatRange((new YearToDate)->previous()))->toBe('2027-01-01 00:00:00 .. 2027-02-28 12:00:00');
});

/**
 * The previous period of a custom range is the window of the same length that ends one
 * second before it starts. Counting whole days truncated a one-day range to zero (so the
 * previous period WAS the current one) and let a month-long one overlap its predecessor.
 */
it('resolves the previous period of a custom range exactly', function (string $start, string $end, string $expected): void {
    expect(formatRange((new Custom($start, $end))->previous()))->toBe($expected);
})->with([
    'one day' => ['2026-01-02 00:00:00', '2026-01-02 23:59:59', '2026-01-01 00:00:00 .. 2026-01-01 23:59:59'],
    'one month' => ['2026-01-01 00:00:00', '2026-01-31 23:59:59', '2025-12-01 00:00:00 .. 2025-12-31 23:59:59'],
    'twelve hours' => ['2026-01-02 06:00:00', '2026-01-02 17:59:59', '2026-01-01 18:00:00 .. 2026-01-02 05:59:59'],
    // An end with an explicit time is taken as given, midnight included.
    'explicit midnight end' => ['2024-01-01 00:00:00', '2024-01-02 00:00:00', '2023-12-30 23:59:59 .. 2023-12-31 23:59:59'],
    // A date-only end is the end of that day, so the previous period lines up on whole days.
    'date-only month' => ['2026-01-01', '2026-01-31', '2025-12-01 00:00:00 .. 2025-12-31 23:59:59'],
    'date-only day' => ['2026-01-02', '2026-01-02', '2026-01-01 00:00:00 .. 2026-01-01 23:59:59'],
]);

it('regression: ends a custom range with a date-only end at the end of that day', function (string $end, string $expected): void {
    expect(formatRange(new Custom('2026-01-01', $end)))->toBe($expected);
})->with([
    'date only' => ['2026-01-31', '2026-01-01 00:00:00 .. 2026-01-31 23:59:59'],
    'date only, another format' => ['31.01.2026', '2026-01-01 00:00:00 .. 2026-01-31 23:59:59'],
    'explicit midnight' => ['2026-01-31 00:00:00', '2026-01-01 00:00:00 .. 2026-01-31 00:00:00'],
    'explicit time' => ['2026-01-31 12:30:00', '2026-01-01 00:00:00 .. 2026-01-31 12:30:00'],
]);

it('regression: counts the rows of the last day of a date-only custom range', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2023-01-15 12:00:00'],
        ['balance' => 1, 'created_at' => '2023-01-31 12:00:00'],
        ['balance' => 1, 'created_at' => '2023-02-01 00:00:00'],
    ]);

    $trends = Metrics::trend()->count(User::query(), 'id')->range(Period::Custom, '2023-01-01', '2023-01-31')->result()->trends();

    expect(Users::make()->range(Period::Custom, '2023-01-01', '2023-01-31')->result()->value())->toBe(2.0)
        ->and($trends['2023-01-31'])->toBe(1.0)
        ->and($trends)->not->toHaveKey('2023-02-01');
});

it('keeps a custom previous period on the wall clock across a daylight-saving change', function (): void {
    config()->set('metrics.timezone', 'Europe/Bratislava');

    // 2026-03-29 is a 23-hour day in Bratislava; its previous day is a full 24 hours.
    expect(formatRange((new Custom('2026-03-29 00:00:00', '2026-03-29 23:59:59'))->previous()))
        ->toBe('2026-03-28 00:00:00 .. 2026-03-28 23:59:59');
});

it('reports the change of a one-day custom range against the day before', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-01-01 10:00:00'],
        ['balance' => 1, 'created_at' => '2026-01-01 11:00:00'],
        ['balance' => 1, 'created_at' => '2026-01-02 10:00:00'],
    ]);

    $result = Users::make()
        ->range('CUSTOM', '2026-01-02 00:00:00', '2026-01-02 23:59:59')
        ->withChangeAgainstPreviousPeriod()
        ->result();

    expect($result->value())->toBe(1.0)
        ->and($result->previous())->toBe(2.0)
        ->and($result->change())->toBe(-50.0);
});
