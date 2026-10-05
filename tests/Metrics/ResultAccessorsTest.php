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

it('exposes the series buckets on a grouped trend result', function (): void {
    $result = new TrendResult(
        ['2023-01' => 30.0],
        ['pro' => ['2023-01' => 20.0], 'free' => ['2023-01' => 10.0]],
    );

    expect($result->series())->toBe(['pro' => ['2023-01' => 20.0], 'free' => ['2023-01' => 10.0]])
        ->and($result->trends())->toBe(['2023-01' => 30.0]);
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

it('regression: leaves the change of a value result empty without a comparison', function (float $value): void {
    $result = new ValueResult($value);

    expect($result->toArray()['change'])->toBe(['percentage' => null, 'is_increase' => null])
        ->and($result->isIncrease())->toBeFalse()
        ->and(ValueResult::fromArray($result->toArray())->toArray())->toBe($result->toArray());
})->with([-5.0, 0.0, 5.0]);

it('regression: leaves the change of a progress result empty without a comparison', function (): void {
    $result = new ProgressResult(value: -2.0, target: 5.0, progress: -40.0, avoid: false);

    expect($result->toArray()['change'])->toBe(['percentage' => null, 'is_increase' => null, 'progress' => null, 'value' => null])
        ->and($result->isIncrease())->toBeFalse()
        ->and(ProgressResult::fromArray($result->toArray())->toArray())->toBe($result->toArray());
});

it('fills the change of a result with a comparison, a fall included', function (): void {
    $value = new ValueResult(value: -5.0, previous: 5.0, change: -200.0);
    $progress = new ProgressResult(value: 2.0, target: 4.0, progress: 50.0, avoid: false, previous: 3.0, previousProgress: 75.0, change: -33.0);

    expect($value->toArray()['change'])->toBe(['percentage' => -200.0, 'is_increase' => false])
        ->and($value->isIncrease())->toBeFalse()
        ->and($progress->toArray()['change'])->toBe(['percentage' => -33.0, 'is_increase' => false, 'progress' => -25.0, 'value' => -1.0]);
});
