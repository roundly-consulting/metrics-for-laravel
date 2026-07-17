<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Metrics\MetricsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider metrics hard-requires, in registration order.
     *
     * The list is exactly one entry, and that is measured rather than an oversight:
     * metrics `require`s enums-for-laravel and package-toolkit-for-laravel, but neither
     * ships a `laravel.providers` entry — the toolkit is the base class this provider
     * extends and enums is a trait/helper library. There is nothing for a host to
     * auto-discover, so nothing for the suite to mirror.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [MetricsServiceProvider::class];
    }

    /**
     * Metrics ships **no migrations** — it reads a host's tables and writes none of its
     * own. The only source here is the host-owned `users` fixture the suite aggregates.
     *
     * It has to be a migration rather than the `Schema::create()` in `getEnvironmentSetUp()`
     * it replaces. On sqlite `:memory:` the imperative create was fine (the database dies
     * with the connection), but on a real engine the table survives teardown and the second
     * test dies on `relation "users" already exists`. Registering it as a migration source
     * hands it to the base case, which drops every table and re-migrates between tests.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [__DIR__.'/database/migrations'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Every range in the suite is relative to a fixed clock. Set after parent::setUp()
        // rather than in the before-boot window: it is not config, and the metrics resolve
        // ranges at call time.
        Carbon::setTestNow('2023-03-10 10:00:00');
    }
}
