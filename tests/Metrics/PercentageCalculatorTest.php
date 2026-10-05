<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Traits\PercentageCalculator;

function percentageCalculator(): object
{
    return new class
    {
        use PercentageCalculator;
    };
}

it('regression: measures the change against the size of the previous value', function (float $current, float $previous, float $change): void {
    expect(percentageCalculator()->calculateChangePercentage($current, $previous))->toBe($change);
})->with([
    'negative base, rising' => [-50.0, -100.0, 50.0],
    'negative base, falling' => [-150.0, -100.0, -50.0],
    'negative base, crossing zero' => [50.0, -100.0, 150.0],
    'positive base, rising' => [150.0, 100.0, 50.0],
    'positive base, falling' => [50.0, 100.0, -50.0],
    'zero base, positive' => [40.0, 0.0, 100.0],
    'zero base, negative' => [-40.0, 0.0, -100.0],
    'zero base, zero' => [0.0, 0.0, 0.0],
]);

it('regression: reports a rise from a negative value as an increase with a positive change', function (): void {
    createUsersForMetricsTesting([
        ['balance' => -100, 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => -50, 'created_at' => '2023-03-10 09:00:00'],
    ]);

    $result = Metrics::value()->sum(User::query(), 'balance')->range('TODAY')->withChangeAgainstPreviousPeriod()->result();

    expect($result->change())->toBe(50.0)
        ->and($result->isIncrease())->toBeTrue();
});
