<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

beforeEach(function (): void {
    Metric::register('users', fn () => Users::make());
    Metric::register('balance', fn () => Users::make('sum', 'balance'));
});

it('resolves many metrics into one keyed envelope sharing a range', function (): void {
    createUsersForMetricsTesting([
        ['balance' => 10, 'created_at' => now()],
        ['balance' => 20, 'created_at' => now()],
    ]);

    $data = Metric::dashboard(['users', 'balance'])->range(Period::Today)->toArray();

    expect($data)->toHaveKeys(['users', 'balance'])
        ->and($data['users']['result']['value'])->toBe(2.0)
        ->and($data['balance']['result']['value'])->toBe(30.0)
        ->and($data['users']['range']['current'])->toBe('TODAY');
});

it('returns the envelope as a json response', function (): void {
    $response = Metric::dashboard(['users'])->toResponse(request());

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true))->toHaveKey('users');
});

it('applies a shared timezone to every metric', function (): void {
    $data = Metric::dashboard(['users'])->range('TODAY')->timezone('Asia/Tokyo')->toArray();

    expect($data)->toHaveKey('users');
});
