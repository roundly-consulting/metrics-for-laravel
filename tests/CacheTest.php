<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\ScopedUsers;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('does not cache when disabled by default', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $first = Users::make()->range('TODAY')->toArray();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $second = Users::make()->range('TODAY')->toArray();

    expect($first['result']['value'])->toBe(1.0)
        ->and($second['result']['value'])->toBe(2.0);
});

it('caches the metric result for the configured ttl', function (): void {
    config()->set('metrics.cache.enabled', true);
    config()->set('metrics.cache.store', 'array');

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    expect(Users::make()->range('TODAY')->toArray()['result']['value'])->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    // Within the TTL the cached value is returned unchanged.
    expect(Users::make()->range('TODAY')->toArray()['result']['value'])->toBe(1.0);

    Carbon::setTestNow(now()->addSeconds(400));

    // Past the TTL the metric is recomputed.
    expect(Users::make()->range('TODAY')->toArray()['result']['value'])->toBe(2.0);
});

it('uses a distinct cache key per range', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([
        ['balance' => 1, 'created_at' => now()],
        ['balance' => 1, 'created_at' => now()->subDays(3)],
    ]);

    $today = Users::make()->range('TODAY')->toArray()['result']['value'];
    $monthToDate = Users::make()->range('MTD')->toArray()['result']['value'];

    expect($today)->toBe(1.0)
        ->and($monthToDate)->toBe(2.0);
});

it('does not cache when dontCache is used', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $first = Users::make()->range('TODAY')->dontCache()->toArray();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $second = Users::make()->range('TODAY')->dontCache()->toArray();

    expect($first['result']['value'])->toBe(1.0)
        ->and($second['result']['value'])->toBe(2.0);
});

it('caches via a per-metric ttl and a custom key', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    Users::make()->range('TODAY')->cacheKey('users:today')->cache(120)->toArray();

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $cached = Users::make()->range('TODAY')->cacheKey('users:today')->cache(120)->toArray();

    expect($cached['result']['value'])->toBe(1.0)
        ->and(cache()->has('users:today'))->toBeTrue();
});

it('caches until a given datetime', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $metric = Metrics::value()
        ->count(User::query())
        ->range('TODAY')
        ->cacheFor(now()->addHour());

    expect($metric->toArray()['result']['value'])->toBe(1.0);

    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    expect(
        Metrics::value()->count(User::query())->range('TODAY')->cacheFor(now()->addHour())->toArray()['result']['value']
    )->toBe(1.0);
});

it('regression: keeps two cached instances of one metric class with different constructor state apart', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([10, 20, 30]);

    expect(Users::make('count')->result()->value())->toBe(3.0)
        ->and(Users::make('sum', 'balance')->result()->value())->toBe(60.0);
});

it('regression: keeps the cached results of two tenant models apart', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([
        ['balance' => 1, 'type' => 'admin'],
        ['balance' => 1, 'type' => 'user'],
        ['balance' => 1, 'type' => 'user'],
    ]);

    $admin = User::query()->where('type', 'admin')->firstOrFail();
    $user = User::query()->where('type', 'user')->firstOrFail();

    expect(ScopedUsers::make($admin)->result()->value())->toBe(1.0)
        ->and(ScopedUsers::make($user)->result()->value())->toBe(2.0)
        ->and(ScopedUsers::make($admin)->result()->value())->toBe(1.0);
});

it('keys a class-based metric by its state, so the same state is still served from the cache', function (mixed $state): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([1]);

    expect(ScopedUsers::make(null, $state)->result()->value())->toBe(1.0);

    createUsersForMetricsTesting([1]);

    expect(ScopedUsers::make(null, $state)->result()->value())->toBe(1.0)
        ->and(ScopedUsers::make()->result()->value())->toBe(2.0);
})->with([
    'a string' => ['eu'],
    'an enum' => [Period::Today],
    'a moment' => [new DateTimeImmutable('2023-03-10 10:00:00')],
    'an array of values' => [[1, 'two', [Period::Today]]],
    'an eloquent query' => [fn () => User::query()->where('type', 'admin')],
    'a query builder' => [fn () => DB::table('users')->where('type', 'admin')],
    'an unsaved model' => [fn () => new User(['type' => 'admin'])],
    'an injected service' => [new ArrayObject],
]);

it('keeps cached results apart when only an eloquent query in its state differs', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([1]);

    expect(ScopedUsers::make(null, User::query()->where('type', 'admin'))->result()->value())->toBe(1.0);

    createUsersForMetricsTesting([1]);

    expect(ScopedUsers::make(null, User::query()->where('type', 'user'))->result()->value())->toBe(2.0);
});

it('skips the cache for a metric holding state with no stable identity', function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([1]);

    expect(ScopedUsers::make(null, fn (): int => 1)->result()->value())->toBe(1.0);

    createUsersForMetricsTesting([1]);

    expect(ScopedUsers::make(null, [fn (): int => 1])->result()->value())->toBe(2.0)
        ->and(ScopedUsers::make(null, fn (): int => 1)->result()->value())->toBe(2.0);
});

it('regression: a registered template cached until a moment stops caching once the moment has passed', function (): void {
    Metrics::register('tpl', Users::make()->cacheFor(now()->addHour()));

    createUsersForMetricsTesting([1]);
    Carbon::setTestNow(now()->addHours(2));

    expect(Metrics::get('tpl')->result()->value())->toBe(1.0);

    createUsersForMetricsTesting([1]);
    Carbon::setTestNow(now()->addMinutes(10));

    expect(Metrics::get('tpl')->result()->value())->toBe(2.0);
});

it('regression: a registered template cached until a moment expires at that moment, not a full ttl after the first read', function (): void {
    Metrics::register('tpl', Users::make()->cacheFor(now()->addHour()));

    createUsersForMetricsTesting([1]);
    Carbon::setTestNow(now()->addMinutes(30));

    expect(Metrics::get('tpl')->result()->value())->toBe(1.0);

    createUsersForMetricsTesting([1]);
    Carbon::setTestNow(now()->addMinutes(15));

    expect(Metrics::get('tpl')->result()->value())->toBe(1.0);

    Carbon::setTestNow(now()->addMinutes(20));

    expect(Metrics::get('tpl')->result()->value())->toBe(2.0);
});
