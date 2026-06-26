<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Types\Partition\PartitionResult;
use RoundlyConsulting\Metrics\Types\Progress\ProgressResult;
use RoundlyConsulting\Metrics\Types\Trend\TrendResult;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

it('exposes typed accessors on a value result', function (): void {
    $result = new ValueResult(value: 12.0, previous: 8.0, change: 50.0);

    expect($result->value())->toBe(12.0)
        ->and($result->previous())->toBe(8.0)
        ->and($result->change())->toBe(50.0)
        ->and($result->isIncrease())->toBeTrue();
});

it('exposes typed accessors on a trend result', function (): void {
    $result = new TrendResult(['2023-01' => 10.0, '2023-02' => 20.0]);

    expect($result->trends())->toBe(['2023-01' => 10.0, '2023-02' => 20.0])
        ->and($result->labels())->toBe(['2023-01', '2023-02'])
        ->and($result->values())->toBe([10.0, 20.0]);
});

it('exposes typed accessors on a progress result', function (): void {
    $result = new ProgressResult(value: 4.0, target: 8.0, progress: 50.0, avoid: false, previous: 2.0, previousProgress: 25.0);

    expect($result->value())->toBe(4.0)
        ->and($result->target())->toBe(8.0)
        ->and($result->progress())->toBe(50.0)
        ->and($result->previous())->toBe(2.0)
        ->and($result->isIncrease())->toBeTrue();
});

it('exposes typed accessors on a partition result', function (): void {
    $result = new PartitionResult(['admin' => 3.0, 'user' => 2.0]);

    expect($result->partitions())->toBe(['admin' => 3.0, 'user' => 2.0])
        ->and($result->labels())->toBe(['admin', 'user'])
        ->and($result->values())->toBe([3.0, 2.0]);
});
