<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Prefix
{
    protected string $prefix = '';

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }
}
