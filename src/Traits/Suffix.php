<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

trait Suffix
{
    protected string $suffix = '';

    public function suffix(string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }
}
