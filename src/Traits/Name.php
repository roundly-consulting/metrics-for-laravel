<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Name
{
    protected string $name = '';

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
