<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Tests\Metrics\Partition\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;

it('returns count partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'admin' => 3.0,
                'user' => 2.0,
            ],
        ]);
});

it('returns sum partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'sum', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 250.0,
                'admin' => 216.0,
            ],
        ]);
});

it('returns sum partition metrics for all users registered today', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user', 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 150, 'type' => 'user', 'created_at' => '2023-03-10 10:00:00'],
        ['balance' => 130, 'type' => 'admin', 'created_at' => '2023-03-09 10:00:00'],
        ['balance' => 81, 'type' => 'admin', 'created_at' => '2023-03-10 10:00:00'],
        ['balance' => 5, 'type' => 'admin', 'created_at' => '2023-03-10 10:00:00'],
    ]);

    $metrics = Users::make('type', 'sum', 'balance')->range('TODAY')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 150.0,
                'admin' => 86.0,
            ],
        ]);
});

it('returns min partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'min', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 100.0,
                'admin' => 5.0,
            ],
        ]);
});

it('returns max partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'max', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 150.0,
                'admin' => 130.0,
            ],
        ]);
});

it('returns average partition metrics for all users', function () {
    createUsersForMetricsTesting([
        ['balance' => 100, 'type' => 'user'],
        ['balance' => 150, 'type' => 'user'],
        ['balance' => 130, 'type' => 'admin'],
        ['balance' => 81, 'type' => 'admin'],
        ['balance' => 5, 'type' => 'admin'],
    ]);

    $metrics = Users::make('type', 'average', 'balance')->toArray();

    expect($metrics['result'])
        ->toBeArray()
        ->toBe([
            'partitions' => [
                'user' => 125.0,
                'admin' => 72.0,
            ],
        ]);
});

it('regression: merges the NULL and empty-string groups instead of dropping rows', function (string $method, ?string $column, float $value, float $pro): void {
    createUsersForMetricsTesting([
        ['plan' => null, 'balance' => 10], ['plan' => null, 'balance' => 20],
        ['plan' => '', 'balance' => 60],
        ['plan' => 'pro', 'balance' => 5],
    ]);

    expect(Metrics::partition()->{$method}(User::query(), 'plan', $column)->result()->partitions())
        ->toBe(['' => $value, 'pro' => $pro]);
})->with([
    'count' => ['count', null, 3.0, 1.0],
    'sum' => ['sum', 'balance', 90.0, 5.0],
    'average' => ['average', 'balance', 30.0, 5.0],
    'max' => ['max', 'balance', 60.0, 5.0],
    'min' => ['min', 'balance', 10.0, 5.0],
]);

it('regression: ranks a merged group by its merged aggregate', function (): void {
    createUsersForMetricsTesting([
        ['plan' => null], ['plan' => null],
        ['plan' => ''], ['plan' => ''],
        ['plan' => 'pro'], ['plan' => 'pro'], ['plan' => 'pro'],
    ]);

    $partition = fn () => Metrics::partition()->count(User::query(), 'plan');

    expect($partition()->result()->partitions())->toBe(['' => 4.0, 'pro' => 3.0])
        ->and($partition()->limit(1)->result()->partitions())->toBe(['' => 4.0, 'Other' => 3.0]);
});

it('regression: keys a boolean group as 0 and 1, apart from the NULL group', function (): void {
    Schema::create('flags', function (Blueprint $table): void {
        $table->id();
        $table->boolean('active')->nullable();
        $table->timestamps();
    });

    $model = new class extends Model
    {
        protected $table = 'flags';
    };

    $model->newQuery()->insert([
        ['active' => null, 'created_at' => now()], ['active' => null, 'created_at' => now()],
        ['active' => false, 'created_at' => now()],
        ['active' => true, 'created_at' => now()],
    ]);

    $partitions = Metrics::partition()->count($model->newQuery(), 'active')->result()->partitions();
    ksort($partitions);

    $series = Metrics::trend()->count($model->newQuery(), 'id')->groupBy('active')->range('TODAY')->result()->series();
    ksort($series);

    expect($partitions)->toBe(['' => 2.0, 0 => 1.0, 1 => 1.0])
        ->and($series)->toBe(['' => ['2023-03-10' => 2.0], 0 => ['2023-03-10' => 1.0], 1 => ['2023-03-10' => 1.0]]);
});
