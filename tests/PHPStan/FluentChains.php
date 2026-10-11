<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\PHPStan;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Testing\MetricsFake;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Progress\Progress;
use RoundlyConsulting\Metrics\Types\Progress\ProgressResult;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Trend\Trend;
use RoundlyConsulting\Metrics\Types\Trend\TrendResult;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

use function PHPStan\Testing\assertType;

/**
 * Static-analysis fixture (never executed): the fluent chains a consumer writes must keep the
 * concrete metric type at level 7, so the next call in the chain resolves. Analysed by the
 * phpstan workflow via `tests/PHPStan` in phpstan.neon.dist.
 *
 * @param  Builder<Model>  $query
 */
function fluentChains(Builder $query): void
{
    assertType(PendingTrend::class, Metrics::trend()->range('7d'));
    assertType(PendingTrend::class, Metrics::trend()->timezone('Europe/Bratislava'));
    assertType(PendingTrend::class, Metrics::trend()->name('Signups')->description('d')->prefix('p')->suffix('s'));
    assertType(PendingTrend::class, Metrics::trend()->precision(2));
    assertType(PendingTrend::class, Metrics::trend()->daily());
    assertType(PendingTrend::class, Metrics::trend()->range('7d')->daily()->count($query, 'id'));

    assertType(PendingValue::class, Metrics::value()->range('30d')->compareTo('60d'));
    assertType(PendingValue::class, Metrics::value()->withChangeAgainstPreviousPeriod());

    assertType(PendingProgress::class, Metrics::progress()->range('30d')->target(100.0)->shouldBeAvoided());

    assertType(PendingPartition::class, Metrics::partition()->range('30d'));

    // A metric class a host writes keeps its own type through the inherited setters.
    assertType(AnalysedSignups::class, (new AnalysedSignups)->range('7d')->daily()->name('Signups'));
    assertType(AnalysedQuota::class, (new AnalysedQuota)->target(10.0)->range('30d'));

    // The fake keeps its assertion surface through the manager's fluent registry.
    $fake = Metrics::fake();
    assertType(MetricsFake::class, $fake->register('signups', AnalysedSignups::class)->unregister('quota'));
}

final class AnalysedSignups extends Trend
{
    protected function calculate(): TrendResult
    {
        return new TrendResult([]);
    }
}

final class AnalysedQuota extends Progress
{
    protected function calculate(): ProgressResult
    {
        return new ProgressResult(1.0, target: 10.0, progress: 10.0, avoid: false);
    }
}
