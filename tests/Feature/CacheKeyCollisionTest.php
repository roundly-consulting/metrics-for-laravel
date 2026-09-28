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
