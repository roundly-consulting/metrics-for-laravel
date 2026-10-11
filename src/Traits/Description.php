<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Description
{
    protected string $description = '';

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
