<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Partition\PartitionResult;

it('caps partitions to the top N and rolls the rest into other', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'a'], ['type' => 'a'], ['type' => 'a'],
        ['type' => 'b'], ['type' => 'b'],
        ['type' => 'c'],
        ['type' => 'd'],
    ]);

    $metrics = Users::make('type')->limit(2)->toArray();

    expect($metrics['result']['partitions'])->toBe([
        'a' => 3.0,
        'b' => 2.0,
        'Other' => 2.0,
    ]);
});

it('does not cap when there are fewer groups than the limit', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'a'],
        ['type' => 'b'],
    ]);

    $metrics = Users::make('type')->limit(5)->toArray();

    expect($metrics['result']['partitions'])->toEqualCanonicalizing(['a' => 1.0, 'b' => 1.0]);
});

it('uses a custom other label', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'a'], ['type' => 'a'],
        ['type' => 'b'],
        ['type' => 'c'],
    ]);

    $metrics = Users::make('type')->limit(1)->otherLabel('Rest')->toArray();

    expect($metrics['result']['partitions'])->toBe(['a' => 2.0, 'Rest' => 2.0]);
});

it('uses the configured other label', function (): void {
    config()->set('metrics.partition.other_label', 'Remaining');

    createUsersForMetricsTesting([
        ['type' => 'a'], ['type' => 'a'],
        ['type' => 'b'],
    ]);

    $metrics = Users::make('type')->limit(1)->toArray();

    expect($metrics['result']['partitions'])->toBe(['a' => 2.0, 'Remaining' => 1.0]);
});

it('maps raw group keys to display labels', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'a'], ['type' => 'a'],
        ['type' => 'b'],
    ]);

    $metrics = Users::make('type')->labelUsing(fn (int|string $key): string => strtoupper((string) $key))->toArray();

    expect($metrics['result'])
        ->toHaveKey('labels')
        ->and($metrics['result']['partitions'])->toBe(['a' => 2.0, 'b' => 1.0])
        ->and($metrics['result']['labels'])->toBe(['a' => 'A', 'b' => 'B']);
});

it('does not relabel the other bucket', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'a'], ['type' => 'a'], ['type' => 'a'],
        ['type' => 'b'],
        ['type' => 'c'],
    ]);

    $metrics = Users::make('type')->limit(1)->labelUsing(fn (int|string $key): string => 'X-'.$key)->toArray();

    expect($metrics['result']['labels'])->toBe(['a' => 'X-a', 'Other' => 'Other']);
});

it('exposes raw keys alongside resolved labels', function (): void {
    $result = new PartitionResult(['a' => 2.0, 'b' => 1.0], ['a' => 'Alpha', 'b' => 'Beta']);

    expect($result->keys())->toBe(['a', 'b'])
        ->and($result->labels())->toBe(['Alpha', 'Beta'])
        ->and($result->toArray())->toBe([
            'partitions' => ['a' => 2.0, 'b' => 1.0],
            'labels' => ['a' => 'Alpha', 'b' => 'Beta'],
        ]);
});

it('falls back to raw keys when no resolver is set', function (): void {
    $result = new PartitionResult(['a' => 2.0]);

    expect($result->labels())->toBe(['a'])
        ->and($result->toArray())->toBe(['partitions' => ['a' => 2.0]]);
});

/**
 * The "Other" bucket is the tail rolled up with the metric's own aggregate. Summing it was
 * right only for count and sum: three groups averaging 50, 40 and 30 are not "120 on
 * average", and their maximum is 50.
 */
it('rolls the tail up with the metric\'s own aggregate', function (string $method, float $other): void {
    createUsersForMetricsTesting([
        ['type' => 'a', 'balance' => 200],
        ['type' => 'b', 'balance' => 50], ['type' => 'b', 'balance' => 50],
        ['type' => 'c', 'balance' => 40],
        ['type' => 'd', 'balance' => 30],
    ]);

    $partitions = Metrics::partition()->{$method}(User::query(), 'type', 'balance')
        ->precision(1)
        ->limit(1)
        ->result()
        ->partitions();

    expect($partitions['Other'])->toBe($other)
        ->and(count($partitions))->toBe(2);
})->with([
    // By count, b (two rows) is the top group and a, c, d are the tail.
    'count' => ['count', 3.0],
    // Otherwise a (200) is the top group and the tail's rows are 50, 50, 40 and 30.
    'sum' => ['sum', 170.0],
    'average' => ['average', 42.5],
    'max' => ['max', 50.0],
    'min' => ['min', 30.0],
]);

it('rolls a real group named like the other bucket into it instead of overwriting it', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'Other'], ['type' => 'Other'], ['type' => 'Other'],
        ['type' => 'SK'], ['type' => 'SK'],
        ['type' => 'CZ'],
    ]);

    // The real "Other" (3) and the tail (CZ, 1) share the one bucket; no row is lost.
    expect(Metrics::partition()->count(User::query(), 'type')->limit(1)->result()->partitions())
        ->toBe(['SK' => 2.0, 'Other' => 4.0]);
});

it('leaves a real group named like the other bucket alone when nothing is capped', function (): void {
    createUsersForMetricsTesting([
        ['type' => 'Other'], ['type' => 'Other'],
        ['type' => 'SK'],
    ]);

    $result = Metrics::partition()->count(User::query(), 'type')
        ->limit(5)
        ->labelUsing(fn (int|string $key): string => 'label '.$key)
        ->result();

    expect($result->partitions())->toBe(['Other' => 2.0, 'SK' => 1.0])
        ->and($result->toArray()['labels'])->toBe(['Other' => 'label Other', 'SK' => 'label SK']);
});

it('counts the rows of the NULL group', function (): void {
    createUsersForMetricsTesting([
        ['plan' => null], ['plan' => null],
        ['plan' => 'pro'],
    ]);

    expect(Metrics::partition()->count(User::query(), 'plan')->result()->partitions())
        ->toBe(['' => 2.0, 'pro' => 1.0]);
});
