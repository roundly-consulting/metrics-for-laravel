<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use Illuminate\Support\Str;

trait Humanize
{
    public function humanize(string $text): string
    {
        return Str::headline($text);
    }
}
