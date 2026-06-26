<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Value;

use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Value\Value;
use RoundlyConsulting\Metrics\Tests\Models\User;

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
