<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Value;

use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Value\Value;

class Users extends Value
{
    public function __construct(protected string $method = 'count', protected ?string $column = null)
    {
        parent::__construct();
    }

    protected function setup(): void {}

    protected function calculate(): Result
    {
        return $this->{$this->method}(User::query(), $this->column);
    }
}
