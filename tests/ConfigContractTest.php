<?php

declare(strict_types=1);

/**
 * The config contract metrics never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied, alerts
 *    #24's thrice-documented `escalation` key.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../config/metrics.php')->toSatisfyConfigContract(__DIR__.'/../src', [
        // `trend_drivers` is a MAP, not a group of keys. Its leaves are driver *names*
        // (mysql, mariadb, pgsql, sqlite) — data the code looks up by the live driver at
        // runtime via `$expressions[$driver]`, never a `config('metrics.trend_drivers.pgsql')`
        // read. The reverse direction is right to notice nothing reads them individually and
        // wrong about what that means, so each is allowed explicitly rather than the parent
        // being excluded wholesale: the map itself IS read, and `allowUnread` is rot-proof —
        // a stale entry that silences nothing is itself a failure, so deleting a driver from
        // the map fails here until this list is updated to match.
        'allowUnread' => [
            'metrics.trend_drivers.mysql',
            'metrics.trend_drivers.mariadb',
            'metrics.trend_drivers.pgsql',
            'metrics.trend_drivers.sqlite',
        ],

        // The two cache keys `.env` sets as strings are read through the toolkit's
        // validating accessors — `Config::using(…)->boolean('metrics.cache.enabled')` and
        // `Config::using(…)->integer('metrics.cache.ttl', …)` — not a bare `config()`
        // call, so the scraper needs telling that those literals are reads. Named exactly,
        // not by a `metrics.` prefix: every other key must still prove itself through
        // `config()`, and a literal that vanishes from src still fails the reverse check.
        'extraReadPrefixes' => [
            'metrics.cache.enabled',
            'metrics.cache.ttl',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but MetricsServiceProvider::contributesToAbout() reads
        // config('metrics.default_range') and the `metrics.cache.enabled` flag for real
        // inside the closure it renders from. Excluding it would discard readers and
        // weaken the reverse direction for nothing.
    ]);
});
