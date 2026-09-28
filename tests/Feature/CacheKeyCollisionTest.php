<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Models\User;

beforeEach(function (): void {
    config()->set('metrics.cache.enabled', true);

    createUsersForMetricsTesting([
        ['balance' => 10, 'type' => 'admin', 'created_at' => now()],
        ['balance' => 20, 'type' => 'user', 'created_at' => now()],
        ['balance' => 30, 'type' => 'user', 'created_at' => now()],
    ]);
});

it('keeps two cached ad-hoc builders over different queries apart', function (): void {
    $admins = Metrics::value()->count(User::query()->where('type', 'admin'))->toArray();
    $everyone = Metrics::value()->count(User::query())->toArray();

    expect($admins['result']['value'])->toBe(1.0)
        ->and($everyone['result']['value'])->toBe(3.0);
});

it('keeps two cached ad-hoc builders over different aggregates apart', function (): void {
    $count = Metrics::value()->count(User::query())->toArray();
    $sum = Metrics::value()->sum(User::query(), 'balance')->toArray();

    expect($count['result']['value'])->toBe(3.0)
        ->and($sum['result']['value'])->toBe(60.0);
});

it('keeps two cached registered metrics of the same class apart', function (): void {
    Metrics::register('admins', fn () => Metrics::value()->count(User::query()->where('type', 'admin')));
    Metrics::register('users', fn () => Metrics::value()->count(User::query()->where('type', 'user')));

    expect(Metrics::get('admins')->toArray()['result']['value'])->toBe(1.0)
        ->and(Metrics::get('users')->toArray()['result']['value'])->toBe(2.0);
});

it('keeps two cached ad-hoc trends over different queries apart', function (): void {
    $admins = Metrics::trend()->count(User::query()->where('type', 'admin'), 'id')->range('7')->result();
    $everyone = Metrics::trend()->count(User::query(), 'id')->range('7')->result();

    expect(array_sum($admins->values()))->toBe(1.0)
        ->and(array_sum($everyone->values()))->toBe(3.0);
});

it('keeps two cached ad-hoc partitions over different queries apart', function (): void {
    $admins = Metrics::partition()->count(User::query()->where('type', 'admin'), 'type')->result();
    $everyone = Metrics::partition()->count(User::query(), 'type')->result();

    expect($admins->keys())->toBe(['admin'])
        ->and($everyone->keys())->toBe(['user', 'admin']);
});

/**
 * Everything that changes a cached result must be part of its key. Precision and the
 * rounding mode are applied inside calculate(), so a result cached at one precision was
 * served at another; the reporting timezone moves every range; and the translated "Other"
 * label is a key of the cached partition.
 */
it('keeps two cached results at different precisions apart', function (): void {
    createUsersForMetricsTesting([['balance' => 0.4567, 'type' => 'admin', 'created_at' => now()]]);

    $sum = fn () => Metrics::value()->sum(User::query(), 'balance');

    expect($sum()->precision(0)->result()->value())->toBe(60.0)
        ->and($sum()->precision(3)->result()->value())->toBe(60.457)
        ->and($sum()->precision(2, RoundingMode::TowardsZero)->result()->value())->toBe(60.45)
        ->and($sum()->precision(2)->result()->value())->toBe(60.46);
});

it('keeps two cached results under different configured timezones apart', function (): void {
    // "now" is 2023-03-10 10:00 UTC — already the 11th in Pacific/Kiritimati (+14).
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => '2023-03-11 05:00:00']]);

    $today = fn () => Metrics::value()->count(User::query())->range('TODAY')->result()->value();

    $utc = $today();
    config()->set('metrics.timezone', 'Pacific/Kiritimati');

    // Kiritimati's today is 10:00 UTC on the 10th to 09:59:59 UTC on the 11th: the three
    // rows created at "now" plus the one at 05:00 on the 11th. A stale entry would say 3.
    expect($utc)->toBe(3.0)
        ->and($today())->toBe(4.0);
});

it('resolves partition labels on every read instead of caching them', function (): void {
    $partition = fn () => Metrics::partition()->count(User::query(), 'type');

    $first = $partition()->labelUsing(fn (int|string $key): string => 'Slovakia')->result();
    $second = $partition()->labelUsing(fn (int|string $key): string => 'Slovensko')->result();

    expect($first->labels())->toBe(['Slovakia', 'Slovakia'])
        ->and($second->labels())->toBe(['Slovensko', 'Slovensko']);
});

it('keeps the translated other bucket of each locale apart', function (): void {
    app('translator')->addLines(['*.Other' => 'Ostatné'], 'sk');

    $partition = fn () => Metrics::partition()->count(User::query(), 'type')->limit(1)->result()->keys();

    $english = $partition();
    app()->setLocale('sk');
    $slovak = $partition();
    app()->setLocale('en');

    expect($english)->toBe(['user', 'Other'])
        ->and($slovak)->toBe(['user', 'Ostatné']);
});
