<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Fake\FakeMetric;
use RoundlyConsulting\Metrics\Types\Fake\FakeResult;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

it('stubs a registered metric with a canned scalar result', function (): void {
    Metrics::fake(['active-users' => 1200]);

    expect(Metrics::get('active-users')->toArray()['result']['value'])->toBe(1200.0);

    Metrics::assertResolved('active-users');
});

it('records resolutions for assertions', function (): void {
    $fake = Metrics::fake(['a' => 1, 'b' => 2]);

    Metrics::get('a');
    Metrics::get('a');

    $fake->assertResolved('a');
    $fake->assertResolvedTimes('a', 2);
    $fake->assertNotResolved('b');
});

it('asserts nothing was resolved', function (): void {
    Metrics::fake(['a' => 1])->assertNothingResolved();
});

it('accepts array, result, and metric canned values', function (): void {
    Metrics::fake([
        'array' => ['value' => 5.0, 'extra' => true],
        'result' => new ValueResult(7.0),
        'metric' => Users::make(),
        'string' => 'hello',
    ]);

    expect(Metrics::get('array')->toArray()['result'])->toBe(['value' => 5.0, 'extra' => true])
        ->and(Metrics::get('result')->toArray()['result']['value'])->toBe(7.0)
        ->and(Metrics::get('metric'))->toBeInstanceOf(Users::class)
        ->and(Metrics::get('string')->toArray()['result'])->toBe(['value' => 'hello'])
        ->and(Metrics::has('array'))->toBeTrue();
});

it('still resolves metrics registered on the fake', function (): void {
    $fake = Metrics::fake();
    $fake->register('real', fn () => Users::make());

    expect(Metrics::get('real'))->toBeInstanceOf(Users::class);

    $fake->assertResolved('real');
});

it('defaults to an empty fake result', function (): void {
    expect((new FakeMetric)->toArray()['result'])->toBe([]);
});

it('fails assertResolved when the metric was never resolved', function (): void {
    Metrics::fake(['a' => 1])->assertResolved('a');
})->throws(ExpectationFailedException::class);

it('fails assertNotResolved when the metric was resolved', function (): void {
    $fake = Metrics::fake(['a' => 1]);
    Metrics::get('a');

    $fake->assertNotResolved('a');
})->throws(ExpectationFailedException::class);

it('keeps the metrics registered before faking', function (): void {
    createUsersForMetricsTesting([100, 100]);

    Metrics::register('users', Users::class)->register('revenue', Users::class);

    Metrics::fake(['revenue' => 5000]);

    expect(Metrics::get('users')->result()->value())->toBe(2.0)
        ->and(Metrics::get('revenue')->toArray()['result']['value'])->toBe(5000.0)
        ->and(Metrics::keys())->toBe(['users', 'revenue'])
        ->and(Metrics::all())->toHaveKeys(['users', 'revenue']);
});

it('lists canned keys that were never registered', function (): void {
    Metrics::fake(['canned' => 1]);

    expect(Metrics::keys())->toBe(['canned'])
        ->and(Metrics::all()['canned']->key())->toBe('canned');

    Metrics::assertResolved('canned');
});

it('hands the fake to injected managers', function (): void {
    $fake = Metrics::fake();

    expect(app(MetricsManager::class))->toBe($fake)
        ->and(app('metrics'))->toBe($fake);
});

it('unregisters a canned key', function (): void {
    Metrics::fake(['a' => 1])->unregister('a');

    expect(Metrics::has('a'))->toBeFalse();
});

it('records forgotten metrics without touching the cache', function (): void {
    config()->set('metrics.cache.enabled', true);
    createUsersForMetricsTesting([100]);

    Metrics::register('users', Users::class);
    Metrics::get('users')->result();

    $fake = Metrics::fake(['canned' => 1]);

    Metrics::forget('users');
    Metrics::forget('canned');
    Metrics::forget(Users::make());

    createUsersForMetricsTesting([100]);

    expect(Metrics::get('users')->result()->value())->toBe(1.0);

    $fake->assertForgotten('users');
    $fake->assertForgotten('canned');
    $fake->assertForgotten(Users::class);
    $fake->assertNotForgotten('other');
    $fake->assertCacheNotFlushed();
});

it('refuses to forget an unknown key on the fake', function (): void {
    Metrics::fake()->forget('nope');
})->throws(UnknownMetricException::class);

it('records a cache flush without touching the cache', function (): void {
    config()->set('metrics.cache.enabled', true);
    createUsersForMetricsTesting([100]);

    Metrics::value()->count(User::query())->cacheKey('k')->result();

    $fake = Metrics::fake();
    Metrics::flushCache();

    createUsersForMetricsTesting([100]);

    expect(Metrics::value()->count(User::query())->cacheKey('k')->result()->value())->toBe(1.0);

    $fake->assertCacheFlushed();
    $fake->assertNothingForgotten();
});

it('never caches a canned result', function (): void {
    config()->set('metrics.cache.enabled', true);

    Metrics::fake(['a' => 1]);
    Metrics::get('a')->toArray();

    Metrics::fake(['a' => 2]);

    expect(Metrics::get('a')->toArray()['result']['value'])->toBe(2.0);
});

it('fails assertForgotten when the metric was not forgotten', function (): void {
    Metrics::fake(['a' => 1])->assertForgotten('a');
})->throws(ExpectationFailedException::class);

it('fails assertNotForgotten when the metric was forgotten', function (): void {
    $fake = Metrics::fake(['a' => 1]);
    Metrics::forget('a');

    $fake->assertNotForgotten('a');
})->throws(ExpectationFailedException::class);

it('fails assertNothingForgotten when a metric was forgotten', function (): void {
    $fake = Metrics::fake(['a' => 1]);
    Metrics::forget('a');

    $fake->assertNothingForgotten();
})->throws(ExpectationFailedException::class);

it('fails assertCacheFlushed when the cache was not flushed', function (): void {
    Metrics::fake()->assertCacheFlushed();
})->throws(ExpectationFailedException::class);

it('fails assertCacheNotFlushed when the cache was flushed', function (): void {
    $fake = Metrics::fake();
    Metrics::flushCache();

    $fake->assertCacheNotFlushed();
})->throws(ExpectationFailedException::class);

it('fails assertResolvedTimes on a different count', function (): void {
    $fake = Metrics::fake(['a' => 1]);
    Metrics::get('a');

    $fake->assertResolvedTimes('a', 2);
})->throws(ExpectationFailedException::class);

it('fails assertNothingResolved when a metric was resolved', function (): void {
    $fake = Metrics::fake(['a' => 1]);
    Metrics::get('a');

    $fake->assertNothingResolved();
})->throws(ExpectationFailedException::class);

it('rebuilds a fake result from its array form', function (): void {
    expect(FakeResult::fromArray(['value' => 3])->toArray())->toBe(['value' => 3]);
});

it('hands out a fresh copy of a canned metric on every get', function (): void {
    Metrics::fake(['a' => 1]);

    Metrics::get('a')->range('TODAY');

    expect(Metrics::get('a')->toArray()['range']['current'])->toBe('ALL')
        ->and(Metrics::get('a'))->not->toBe(Metrics::get('a'));
});

it('never caches a canned metric instance either', function (): void {
    config()->set('metrics.cache.enabled', true);

    Metrics::fake(['users' => Users::make()]);

    createUsersForMetricsTesting([['balance' => 1]]);
    $first = Metrics::get('users')->result()->value();

    createUsersForMetricsTesting([['balance' => 1]]);

    expect($first)->toBe(1.0)
        ->and(Metrics::get('users')->result()->value())->toBe(2.0);
});
