<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('returns a json response directly from a metric', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    $response = Metrics::value()->count(User::query())->range('TODAY')->toResponse(request());

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getData(true)['result']['value'])->toEqual(1.0);
});
