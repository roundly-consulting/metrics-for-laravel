<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Metrics\MetricsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            MetricsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('balance')->default(0);
            $table->string('type')->default('user');
            $table->timestamps();
        });

        Carbon::setTestNow('2023-03-10 10:00:00');
    }
}
