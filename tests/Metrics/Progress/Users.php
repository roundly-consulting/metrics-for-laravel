<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Progress;

use RoundlyConsulting\Metrics\Metrics\Progress\Progress;
use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Tests\Models\User;

class Users extends Progress
{
    public function __construct(protected string $method = 'count', protected ?string $column = null)
    {
        parent::__construct();
    }

    protected function calculate(): Result
    {
        return $this->{$this->method}(User::query(), $this->column);
    }
}
