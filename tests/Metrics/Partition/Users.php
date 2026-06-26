<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Partition;

use RoundlyConsulting\Metrics\Tests\Models\User;
use RoundlyConsulting\Metrics\Types\Partition\Partition;
use RoundlyConsulting\Metrics\Types\Result;

class Users extends Partition
{
    public function __construct(protected string $column, protected string $method = 'count', protected string $valueColumn = 'balance')
    {
        parent::__construct();
    }

    protected function calculate(): Result
    {
        return $this->{$this->method}(User::query(), $this->column, $this->valueColumn);
    }
}
