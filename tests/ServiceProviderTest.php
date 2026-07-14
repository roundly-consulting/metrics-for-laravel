<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

it('binds the metrics manager and its container alias', function (): void {
    expect(app(MetricsManager::class))->toBeInstanceOf(MetricsManager::class)
        ->and(app('metrics'))->toBe(app(MetricsManager::class));
});

it('registers the package commands', function (): void {
    expect(Artisan::all())
        ->toHaveKey('metrics:list')
        ->toHaveKey('metrics:show');
});

it('contributes a section to the about command', function (): void {
    app(MetricsManager::class)->register('registered_users', Users::class);

    $this->artisan('about', ['--only' => 'metrics'])
        ->expectsOutputToContain('Registered metrics')
        ->expectsOutputToContain('OFF')
        ->assertSuccessful();
});

it('reports enabled result caching to the about command', function (): void {
    config()->set('metrics.cache.enabled', true);

    $this->artisan('about', ['--only' => 'metrics'])
        ->expectsOutputToContain('ENABLED')
        ->assertSuccessful();
});
