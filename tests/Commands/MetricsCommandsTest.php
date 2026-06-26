<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('lists registered metrics', function (): void {
    Metric::register('registered_users', Users::class);

    $this->artisan('metrics:list')
        ->assertSuccessful()
        ->expectsOutputToContain('registered_users');
});

it('warns when no metrics are registered', function (): void {
    $this->artisan('metrics:list')
        ->assertSuccessful()
        ->expectsOutputToContain('No metrics are registered');
});

it('shows a metric envelope for a given range', function (): void {
    createUsersForMetricsTesting([['balance' => 1, 'created_at' => now()]]);

    Metric::register('registered_users', fn () => Users::make());

    $this->artisan('metrics:show', ['key' => 'registered_users', '--range' => 'TODAY'])
        ->assertSuccessful();
});

it('fails for an unknown metric key', function (): void {
    $this->artisan('metrics:show', ['key' => 'missing'])
        ->assertFailed()
        ->expectsOutputToContain('No metric is registered');
});
