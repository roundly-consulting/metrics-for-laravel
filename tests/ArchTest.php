<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\MetricsManager;
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
 * Exactly one exemption, and it is a seam the package itself consumes: `MetricsFake
 * extends MetricsManager`, so `final` here is a PHP fatal rather than a style question.
 * `Metric::fake()` is the documented entry point host apps call, and it returns that
 * subclass. Named explicitly so the decision is visible and rot-checked — `shops` finalled
 * its `ShopManager`, but nothing extends that one.
 *
 * The seven abstract bases this list used to name are gone, and their removal is the point
 * rather than tidying. `finalByDefault` skips abstract classes on its own (`abstract final`
 * is a fatal, so flagging one was a false positive by construction), so every one of them
 * silenced nothing it needed to — while `Metrics::class` silenced `MetricsManager` by
 * string prefix, which is precisely why this preset ran green over a non-final class for
 * the whole life of the package.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Metrics', [
    MetricsManager::class,
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
