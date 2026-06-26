<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users;
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
