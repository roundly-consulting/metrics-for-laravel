<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Description
{
    protected string $description = '';

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
