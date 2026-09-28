<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;
use RoundlyConsulting\Enums\Exceptions\EnumException;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\Users as UsersTrend;
use RoundlyConsulting\Metrics\Tests\Metrics\Trend\UsersBalance;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Traits\Ranges;

$periodLabels = [
    'Days7' => '7 Days',
    'Days14' => '14 Days',
    'Days30' => '30 Days',
    'Days60' => '60 Days',
    'Days90' => '90 Days',
    'Days365' => '365 Days',
    'Yesterday' => 'Yesterday',
    'Today' => 'Today',
    'WeekToDate' => 'Week To Date',
    'MonthToDate' => 'Month To Date',
    'QuarterToDate' => 'Quarter To Date',
    'YearToDate' => 'Year To Date',
    'ThisWeek' => 'This Week',
    'LastWeek' => 'Last Week',
    'ThisMonth' => 'This Month',
    'LastMonth' => 'Last Month',
    'ThisQuarter' => 'This Quarter',
    'LastQuarter' => 'Last Quarter',
    'ThisYear' => 'This Year',
    'LastYear' => 'Last Year',
    'Custom' => 'Custom',
    'All' => 'All',
];

$periodOptionsMap = [
    7 => '7 Days',
    14 => '14 Days',
    30 => '30 Days',
    60 => '60 Days',
    90 => '90 Days',
    365 => '365 Days',
    'YESTERDAY' => 'Yesterday',
    'TODAY' => 'Today',
    'WTD' => 'Week To Date',
    'MTD' => 'Month To Date',
    'QTD' => 'Quarter To Date',
    'YTD' => 'Year To Date',
    'THIS_WEEK' => 'This Week',
    'LAST_WEEK' => 'Last Week',
    'THIS_MONTH' => 'This Month',
    'LAST_MONTH' => 'Last Month',
    'THIS_QUARTER' => 'This Quarter',
    'LAST_QUARTER' => 'Last Quarter',
    'THIS_YEAR' => 'This Year',
    'LAST_YEAR' => 'Last Year',
    'CUSTOM' => 'Custom',
    'ALL' => 'All',
];

it('accepts a Period enum and a string interchangeably', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => now()->startOfDay()],
    ]);

    $fromEnum = Users::make()->range(Period::Today)->toArray();
    $fromString = Users::make()->range('TODAY')->toArray();

    expect($fromEnum)->toBe($fromString)
        ->and($fromEnum['range']['current'])->toBe('TODAY');
});

it('accepts a Unit enum and a string interchangeably', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 100, 'created_at' => '2023-02-26 10:00:00'],
        ['balance' => 80, 'created_at' => '2023-02-27 10:00:00'],
    ]);

    $fromEnum = UsersBalance::make()->unit(Unit::Day)->toArray();
    $fromString = UsersBalance::make()->unit('DAY')->toArray();

    expect($fromEnum)->toBe($fromString);
});

it('ignores an unknown unit string', function (): void {
    $metric = UsersBalance::make()->daily()->unit('NONSENSE');

    expect($metric->toArray())->toBeArray();
});

it('lists period options with translatable labels', function () use ($periodOptionsMap): void {
    expect(Period::toOptions()->all())->toBe($periodOptionsMap);
});

it('keeps the host-facing ranges map unchanged', function () use ($periodOptionsMap): void {
    $host = new class
    {
        use Ranges;
    };

    expect($host->ranges())->toBe($periodOptionsMap);
});

it('reads every period label via the readable override', function () use ($periodLabels): void {
    foreach (Period::cases() as $case) {
        expect($case->readable())->toBe($periodLabels[$case->name])
            ->and($case->label())->toBe($periodLabels[$case->name]);
    }
});

it('lists period labels in declaration order', function () use ($periodLabels): void {
    expect(Period::labels()->all())->toBe(array_values($periodLabels));
});

it('lists period values in declaration order', function () use ($periodLabels): void {
    expect(Period::values()->all())->toBe([
        '7', '14', '30', '60', '90', '365',
        'YESTERDAY', 'TODAY', 'WTD', 'MTD', 'QTD', 'YTD',
        'THIS_WEEK', 'LAST_WEEK', 'THIS_MONTH', 'LAST_MONTH',
        'THIS_QUARTER', 'LAST_QUARTER', 'THIS_YEAR', 'LAST_YEAR',
        'CUSTOM', 'ALL',
    ])
        ->and(Period::names()->all())->toBe(array_keys($periodLabels))
        ->and(Period::count())->toBe(22);
});

it('builds a period validation rule from the backed values', function (): void {
    expect(Period::validationRule())->toBe(
        'in:7,14,30,60,90,365,YESTERDAY,TODAY,WTD,MTD,QTD,YTD,THIS_WEEK,'
        .'LAST_WEEK,THIS_MONTH,LAST_MONTH,THIS_QUARTER,LAST_QUARTER,THIS_YEAR,LAST_YEAR,CUSTOM,ALL'
    );
});

it('exposes period option DTOs', function (): void {
    $options = Period::options();

    expect($options)->toHaveCount(22)
        ->and($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('7')
        ->and($options->first()->label)->toBe('7 Days')
        ->and($options->first()->name)->toBe('Days7');
});

it('resolves period cases by name and label', function (): void {
    expect(Period::fromName('WeekToDate'))->toBe(Period::WeekToDate)
        ->and(Period::tryFromName('nope'))->toBeNull()
        ->and(Period::fromLabel('Month To Date'))->toBe(Period::MonthToDate)
        ->and(Period::tryFromLabel('nope'))->toBeNull()
        ->and(Period::hasName('Today'))->toBeTrue()
        ->and(Period::hasValue('WTD'))->toBeTrue()
        ->and(Period::hasValue('NOPE'))->toBeFalse();
});

it('throws when resolving a period by an unknown name', function (): void {
    Period::fromName('Nope');
})->throws(EnumException::class);

it('throws when resolving a period by an unknown label', function (): void {
    Period::fromLabel('Nope');
})->throws(EnumException::class);

it('returns null for an unknown period value', function (): void {
    expect(Period::tryFrom('NONSENSE'))->toBeNull();
});

it('combines period instances', function (): void {
    expect(Period::Today->is(Period::Today))->toBeTrue()
        ->and(Period::Today->isNot(Period::Yesterday))->toBeTrue()
        ->and(Period::Today->isIn([Period::Today, Period::Yesterday]))->toBeTrue()
        ->and(Period::Today->isNotIn([Period::Yesterday, Period::All]))->toBeTrue();
});

it('exposes the unit helper surface', function (): void {
    expect(Unit::validationRule())->toBe('in:MINUTE,HOUR,DAY,WEEK,MONTH,YEAR')
        ->and(Unit::labels()->all())->toBe(['Minute', 'Hour', 'Day', 'Week', 'Month', 'Year'])
        ->and(Unit::toOptions()->all())->toBe([
            'MINUTE' => 'Minute',
            'HOUR' => 'Hour',
            'DAY' => 'Day',
            'WEEK' => 'Week',
            'MONTH' => 'Month',
            'YEAR' => 'Year',
        ])
        ->and(Unit::Day->readable())->toBe('Day')
        ->and(Unit::values()->all())->toBe(['MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR'])
        ->and(Unit::count())->toBe(6);
});

it('resolves unit cases by name and label', function (): void {
    expect(Unit::fromName('Hour'))->toBe(Unit::Hour)
        ->and(Unit::tryFromLabel('Week'))->toBe(Unit::Week)
        ->and(Unit::tryFromName('nope'))->toBeNull()
        ->and(Unit::hasValue('DAY'))->toBeTrue();
});

it('exposes unit option DTOs', function (): void {
    $options = Unit::options();

    expect($options)->toHaveCount(6)
        ->and($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('MINUTE')
        ->and($options->first()->label)->toBe('Minute')
        ->and($options->first()->name)->toBe('Minute');
});

it('throws when resolving a range for the unbounded all period', function (): void {
    Period::All->toRange();
})->throws(InvalidRangeException::class);

it('resolves a custom range through the period enum', function (): void {
    $range = Period::Custom->toRange('2023-03-01 00:00:00', '2023-03-05 00:00:00');

    expect($range->start()->toDateString())->toBe('2023-03-01')
        ->and($range->end()->toDateString())->toBe('2023-03-05');
});

/**
 * `createFromFormat()` without `!` fills every field the format leaves out from "now" —
 * the day of month for `Y-m`, the month and day for `Y`, the time for `Y-m-d`. On the 29th
 * to 31st, `2026-02` became March 3rd; an all-time monthly trend then started its axis in
 * March and lost February.
 */
it('parses every bucket key to the start of its bucket, whatever the day today', function (Unit $unit, string $key, string $start): void {
    CarbonImmutable::setTestNow('2026-03-31 17:45:12');
    Carbon::setTestNow('2026-03-31 17:45:12');

    expect($unit->parse($key)->toDateTimeString())->toBe($start);
})->with([
    'minute' => [Unit::Minute, '2026-02-03 10:11:00', '2026-02-03 10:11:00'],
    'hour' => [Unit::Hour, '2026-02-03 10:00', '2026-02-03 10:00:00'],
    'day' => [Unit::Day, '2026-02-03', '2026-02-03 00:00:00'],
    'week' => [Unit::Week, '2026-06', '2026-02-02 00:00:00'],
    'month' => [Unit::Month, '2026-02', '2026-02-01 00:00:00'],
    'year' => [Unit::Year, '2025', '2025-01-01 00:00:00'],
]);

it('keeps every month of an all-time monthly trend on the 31st', function (): void {
    Carbon::setTestNow('2026-03-31 12:00:00');

    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-01-15 09:00:00'],
        ['balance' => 1, 'created_at' => '2026-02-15 09:00:00'],
        ['balance' => 1, 'created_at' => '2026-03-15 09:00:00'],
    ]);

    expect(UsersTrend::make()->monthly()->range('ALL')->result()->trends())->toBe([
        '2026-01' => 1.0,
        '2026-02' => 1.0,
        '2026-03' => 1.0,
    ]);
});
