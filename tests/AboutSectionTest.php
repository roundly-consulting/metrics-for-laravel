<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * Metrics carries no credentials, so it is worth being precise about what the risk is
 * here: it is **business intelligence**. A metric's class name describes what a host
 * measures (Revenue, ChurnedAccounts, FailedPayments), and a computed value is the number
 * itself. The provider reports registered metrics *by count* — this is the test that holds
 * it to that, and the reason the count is the only safe thing to render.
 */
it('renders the metrics section without leaking the metrics it registers', function (): void {
    Metric::register('users', Users::class);

    expect('metrics')->toLeakNoSecrets(
        secrets: [
            // A registered metric's class and key name what the host measures; neither
            // renders. The count is the whole contract.
            Users::class,
            'users',
        ],
        mustRender: [
            'Registered metrics',
            // The positive proof the line reports rather than silently rendering empty.
            '1',
            'Result cache',
            'Default range',
        ],
    );
});

/**
 * The other branch of the cache line. Without this, the assertion above could pass against
 * a section that only ever renders one state of the flag.
 */
it('reports the result cache as enabled when it is on', function (): void {
    config()->set('metrics.cache.enabled', true);

    expect('metrics')->toLeakNoSecrets(
        secrets: [Users::class],
        mustRender: ['Result cache', 'ENABLED'],
    );
});

/**
 * `.env` hands the flag over as a string — `METRICS_CACHE_ENABLED=1` is `'1'`, not `true` —
 * and caching treats it as on, so the section must too.
 */
it('reports the result cache as enabled for an env-style string flag', function (): void {
    config()->set('metrics.cache.enabled', '1');

    expect('metrics')->toLeakNoSecrets(
        secrets: [Users::class],
        mustRender: ['Result cache', 'ENABLED'],
    );
});
