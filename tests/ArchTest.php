<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Exceptions\MetricsException;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Ranges\BaseRange;
use RoundlyConsulting\Metrics\Types\Partition\Partition;
use RoundlyConsulting\Metrics\Types\Progress\Progress;
use RoundlyConsulting\Metrics\Types\Trend\Trend;
use RoundlyConsulting\Metrics\Types\Value\Value;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Metrics shipped with a single hand-written rule (`dd`/`dump`/`ray`), which
 * `noDebuggingLeftovers` now replaces; everything else here is a new guard.
 *
 * Two of the seven presets do not apply and are deliberately **not** registered rather
 * than added for symmetry:
 *
 *   - `swappableModelsAreNotFinal` — metrics ships **no Eloquent model at all**. It reads a
 *     host's tables and writes none of its own. The row spec scored it `Swap? 4`, but those
 *     four are `metrics.trend_drivers` — a map of driver name to `QueryExpression`
 *     implementation. They are handler bindings, not swappable models: there is no
 *     `*_model` key, nothing extends Model, and `toHonourModelSwap` has no row to create.
 *     The seam is real and important, and it is tested by execution (ConfigTest's
 *     `reads trend drivers from config`) rather than by a model-swap assertion that has
 *     nothing to hold.
 *   - `modelsResolveThroughSeam` — same root cause: no Eloquent model and no `*_model` key,
 *     so the late-static-binding ban and the stray-literal scan both have nothing to read.
 *     It would be green on the first run and green forever. jwt, enums and package-toolkit
 *     rejected it for the same reason.
 */
ArchPresets::strictTypes('RoundlyConsulting\Metrics');

/**
 * The exemptions are every abstract base in the package, and they are the package's entire
 * public surface — a host writes metrics BY extending these:
 *
 *   - Metrics, the root every metric type extends;
 *   - Value / Trend / Partition / Progress, the four metric types a host subclasses;
 *   - BaseRange, which all 20-odd shipped ranges extend and a host extends to add its own;
 *   - MetricsException, the base every metrics error extends so a host can catch uniformly.
 *
 * Enumerated explicitly rather than waved through: `finalByDefault` does not skip abstract
 * classes, so each one has to be named, and naming them is what makes a NEW un-final
 * concrete class fail here instead of hiding behind a blanket exemption.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Metrics')
    ->ignoring([
        Metrics::class,
        Value::class,
        Trend::class,
        Partition::class,
        Progress::class,
        BaseRange::class,
        MetricsException::class,
    ]);

/**
 * Metrics does no cryptography. The ban matters here for one specific reason: the result
 * cache builds keys from a metric's identity, and a hand-rolled hash for that cache key
 * would be exactly the kind of local primitive that belongs in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Metrics');

/**
 * The Dependency Policy as a test. No `alsoAllow`: metrics' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
