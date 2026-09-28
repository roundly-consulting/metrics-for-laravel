<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Support\Timezones;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Mysql;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Postgres;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Sqlite;

it('moves a datetime column by whole minutes in every driver grammar', function (): void {
    expect((new Sqlite)->addMinutes('created_at', 120))->toBe("datetime(created_at, '+120 minutes')")
        ->and((new Sqlite)->addMinutes('created_at', -330))->toBe("datetime(created_at, '-330 minutes')")
        ->and((new Mysql)->addMinutes('created_at', -60))->toBe('(created_at + interval -60 minute)')
        ->and((new Postgres)->addMinutes('created_at', 45))->toBe("(created_at + interval '45 minutes')");
});

it('resolves the storage and reporting clocks', function (): void {
    config()->set('app.timezone', 'UTC');
    config()->set('metrics.timezone', null);

    expect(Timezones::storage())->toBe('UTC')
        ->and(Timezones::reporting())->toBe('UTC')
        ->and(Timezones::reporting('Asia/Tokyo'))->toBe('Asia/Tokyo');

    config()->set('metrics.timezone', 'Europe/Bratislava');
    config()->set('app.timezone', '');

    expect(Timezones::reporting())->toBe('Europe/Bratislava')
        ->and(Timezones::storage())->toBe(date_default_timezone_get());
});

it('leaves the column alone when both clocks keep the same offset', function (): void {
    $sql = Timezones::reportingColumn(
        new Sqlite, 'created_at', 'Europe/Bratislava', 'Europe/Vienna',
        CarbonImmutable::parse('2026-01-01 00:00:00'), CarbonImmutable::parse('2026-12-31 23:59:59'),
    );

    expect($sql)->toBe('created_at');
});

it('applies a single offset when neither clock changes inside the span', function (): void {
    $sql = Timezones::reportingColumn(
        new Sqlite, 'created_at', 'UTC', 'Asia/Kolkata',
        CarbonImmutable::parse('2026-01-01 00:00:00'), CarbonImmutable::parse('2026-12-31 23:59:59'),
    );

    expect($sql)->toBe("datetime(created_at, '+330 minutes')");
});

it('cuts the span at each daylight-saving change of either clock', function (): void {
    // Bratislava storage, UTC reporting: CET (+1) until 2026-03-29 02:00 local, then CEST (+2).
    $sql = Timezones::reportingColumn(
        new Sqlite, 'created_at', 'Europe/Bratislava', 'UTC',
        CarbonImmutable::parse('2026-03-01 00:00:00', 'Europe/Bratislava'), CarbonImmutable::parse('2026-04-30 23:59:59', 'Europe/Bratislava'),
    );

    expect($sql)->toBe(
        "case when created_at < '2026-03-29 03:00:00' then datetime(created_at, '-60 minutes') "
        ."else datetime(created_at, '-120 minutes') end"
    );
});

it('returns an empty all-time trend on another clock when there are no rows', function (): void {
    config()->set('metrics.timezone', 'Europe/Bratislava');

    expect(Metrics::trend()->count(User::query(), 'id')->range('ALL')->result()->trends())->toBe([]);
});
