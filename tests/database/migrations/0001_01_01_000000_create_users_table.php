<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned table every metric in the suite aggregates over. It belongs to the
 * fixture, not to the package — metrics ships no migrations at all, because it reads a
 * host's tables and writes none of its own.
 *
 * No `down()`: forward-only is the standard, and the real-engine reset drops every table
 * and re-migrates rather than rolling back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            // Decimal, not integer. The fixture this replaces declared `balance` an
            // integer while the suite inserts 150.12346 and 180.5231442 and asserts the
            // 4-dp rounding of them — so fractional balances are the point of that test.
            // sqlite's INTEGER affinity stores a value it cannot losslessly convert as
            // REAL, so the column type never mattered and the mismatch was invisible.
            // Postgres refuses outright: `invalid input syntax for type integer:
            // "150.12346"`. The schema was simply wrong about its own data.
            $table->decimal('balance', 20, 10)->default(0);
            $table->string('type')->default('user');
            // Nullable, unlike `type`: a partition's NULL group is a real group of rows.
            $table->string('plan')->nullable();
            $table->timestamps();
        });
    }
};
