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

/**
 * A keyed map whose keys happen to run 0, 1, … encodes as a JSON array, so the response
 * shape used to flip with the data — an object one day, an array the next.
 */
it('regression: encodes a partition\'s keyed maps as json objects whatever their keys', function (array $types): void {
    createUsersForMetricsTesting(array_map(fn (string $type): array => ['type' => $type], $types));

    $partition = fn () => Metrics::partition()->count(User::query(), 'type')
        ->labelUsing(fn (int|string $key): string => 'type '.$key)
        ->formatUsing(fn (float $value): string => (string) $value);

    $result = json_decode($partition()->toResponse(request())->getContent())->result;

    expect($result->partitions)->toBeObject()
        ->and($partition()->toArray()['result']['partitions'])->toBeArray();

    if ($types !== []) {
        expect($result->labels)->toBeObject()
            ->and($result->formatted)->toBeObject();
    }
})->with([
    'keys in order' => [['0', '0', '1']],
    'keys out of order' => [['0', '1', '1']],
    'no rows' => [[]],
]);

it('regression: encodes a trend\'s keyed maps as json objects whatever their keys', function (): void {
    createUsersForMetricsTesting([['type' => '0', 'created_at' => now()], ['type' => '1', 'created_at' => now()]]);

    $grouped = json_decode(Metrics::trend()->count(User::query(), 'id')->groupBy('type')->toResponse(request())->getContent())->result;
    $empty = json_decode(Metrics::trend()->count(User::query()->where('type', 'none'), 'id')->toResponse(request())->getContent())->result;

    expect($grouped->trends)->toBeObject()
        ->and($grouped->series)->toBeObject()
        ->and($grouped->series->{'0'})->toBeObject()
        ->and($empty->trends)->toBeObject();
});

it('regression: encodes a dashboard\'s keyed maps as json objects', function (): void {
    createUsersForMetricsTesting([['type' => '0'], ['type' => '0'], ['type' => '1']]);

    Metrics::register('types', fn () => Metrics::partition()->count(User::query(), 'type'));
    Metrics::register('count', fn () => Metrics::value()->count(User::query()));

    $dashboard = json_decode(Metrics::dashboard(['types', 'count'])->toResponse(request())->getContent());

    expect($dashboard->types->result->partitions)->toBeObject()
        ->and($dashboard->count->result->value)->toEqual(3.0)
        ->and(Metrics::dashboard(['types'])->toArray()['types']['result']['partitions'])->toBe([0 => 2.0, 1 => 1.0]);
});
