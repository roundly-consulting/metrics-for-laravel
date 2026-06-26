<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Name
{
    protected string $name = '';

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
