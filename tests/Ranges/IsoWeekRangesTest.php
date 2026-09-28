<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Metrics\Enums\Unit;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Ranges\LastWeek;
use RoundlyConsulting\Metrics\Ranges\ThisWeek;
use RoundlyConsulting\Metrics\Ranges\WeekToDate;
use RoundlyConsulting\Metrics\Tests\Models\User;

/**
 * The week ranges are ISO weeks — Monday to Sunday — whatever the locale, the same weeks
 * the WEEK trend buckets (`o-W`) count in. Carbon's bare `startOfWeek()` reads the locale:
 * `en_US` starts on Sunday and `ar` on Saturday, so THIS_WEEK moved with `App::setLocale()`
 * (which the cache key does not see) and a weekly trend over it straddled two ISO buckets.
 */
afterEach(function (): void {
    app()->setLocale('en');
    Carbon::setLocale('en');
});

it('starts every week range on Monday whatever the locale', function (string $locale): void {
    CarbonImmutable::setTestNow('2026-09-30 12:00:00'); // a Wednesday
    app()->setLocale($locale);
    Carbon::setLocale($locale);

    $format = fn ($range): string => $range->start()->toDateTimeString().' .. '.$range->end()->toDateTimeString();

    expect($format(new ThisWeek))->toBe('2026-09-28 00:00:00 .. 2026-10-04 23:59:59')
        ->and($format((new ThisWeek)->previous()))->toBe('2026-09-21 00:00:00 .. 2026-09-27 23:59:59')
        ->and($format(new LastWeek))->toBe('2026-09-21 00:00:00 .. 2026-09-27 23:59:59')
        ->and($format((new LastWeek)->previous()))->toBe('2026-09-14 00:00:00 .. 2026-09-20 23:59:59')
        ->and($format(new WeekToDate))->toBe('2026-09-28 00:00:00 .. 2026-09-30 12:00:00')
        ->and($format((new WeekToDate)->previous()))->toBe('2026-09-21 00:00:00 .. 2026-09-23 12:00:00');
})->with(['en', 'en_US', 'de', 'ar']);

it('agrees between a weekly trend and a value over this week in a Sunday-first locale', function (): void {
    CarbonImmutable::setTestNow('2026-09-30 12:00:00');
    app()->setLocale('en_US');
    Carbon::setLocale('en_US');

    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => '2026-09-27 10:00:00'], // Sunday: ISO week 39
        ['balance' => 1, 'created_at' => '2026-09-30 10:00:00'], // Wednesday: ISO week 40
    ]);

    expect(Metrics::trend()->count(User::query(), 'id')->weekly()->range('THIS_WEEK')->result()->trends())->toBe(['2026-40' => 1.0])
        ->and(Metrics::value()->count(User::query())->range('THIS_WEEK')->result()->value())->toBe(1.0);
});

it('parses a week key to Monday 00:00 and its end to Sunday 23:59:59 whatever the locale', function (): void {
    Carbon::setLocale('en_US');

    expect(Unit::Week->parse('2026-40')->toDateTimeString())->toBe('2026-09-28 00:00:00')
        ->and(Unit::Week->parse('2026-40', true)->toDateTimeString())->toBe('2026-10-04 23:59:59');
});
