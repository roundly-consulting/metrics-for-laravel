<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Metrics\Facades\Metric;
use RoundlyConsulting\Metrics\Tests\Metrics\Value\Users;
use RoundlyConsulting\Metrics\Types\Fake\FakeMetric;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

it('stubs a registered metric with a canned scalar result', function (): void {
    Metric::fake(['active-users' => 1200]);

    expect(Metric::get('active-users')->toArray()['result']['value'])->toBe(1200.0);

    Metric::assertResolved('active-users');
});

it('records resolutions for assertions', function (): void {
    $fake = Metric::fake(['a' => 1, 'b' => 2]);

    Metric::get('a');
    Metric::get('a');

    $fake->assertResolved('a');
    $fake->assertResolvedTimes('a', 2);
    $fake->assertNotResolved('b');
});

it('asserts nothing was resolved', function (): void {
    Metric::fake(['a' => 1])->assertNothingResolved();
});

it('accepts array, result, and metric canned values', function (): void {
    Metric::fake([
        'array' => ['value' => 5.0, 'extra' => true],
        'result' => new ValueResult(7.0),
        'metric' => Users::make(),
        'string' => 'hello',
    ]);

    expect(Metric::get('array')->toArray()['result'])->toBe(['value' => 5.0, 'extra' => true])
        ->and(Metric::get('result')->toArray()['result']['value'])->toBe(7.0)
        ->and(Metric::get('metric'))->toBeInstanceOf(Users::class)
        ->and(Metric::get('string')->toArray()['result'])->toBe(['value' => 'hello'])
        ->and(Metric::has('array'))->toBeTrue();
});

it('still resolves metrics registered on the fake', function (): void {
    $fake = Metric::fake();
    $fake->register('real', fn () => Users::make());

    expect(Metric::get('real'))->toBeInstanceOf(Users::class);

    $fake->assertResolved('real');
});

it('defaults to an empty fake result', function (): void {
    expect((new FakeMetric)->toArray()['result'])->toBe([]);
});

it('fails assertResolved when the metric was never resolved', function (): void {
    Metric::fake(['a' => 1])->assertResolved('a');
})->throws(ExpectationFailedException::class);

it('fails assertNotResolved when the metric was resolved', function (): void {
    $fake = Metric::fake(['a' => 1]);
    Metric::get('a');

    $fake->assertNotResolved('a');
})->throws(ExpectationFailedException::class);
