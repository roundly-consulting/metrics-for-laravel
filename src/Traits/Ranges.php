<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Traits;

use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\Days;
use RoundlyConsulting\Metrics\Ranges\MonthToDate;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Ranges\Today;
use RoundlyConsulting\Metrics\Ranges\YearToDate;
use RoundlyConsulting\Metrics\Ranges\Yesterday;

trait Ranges
{
    protected string $range = 'ALL';

    protected ?string $customRangeStart = null;

    protected ?string $customRangeEnd = null;

    /**
     * @return array<array-key, string>
     */
    public function ranges(): array
    {
        return array_map(
            static fn (string|array $label): string => is_array($label) ? '' : $label,
            [
                '30' => __('30 Days'),
                '60' => __('60 Days'),
                '90' => __('90 Days'),
                '365' => __('365 Days'),
                'YESTERDAY' => __('Yesterday'),
                'TODAY' => __('Today'),
                'MTD' => __('Month To Date'),
                'YTD' => __('Year To Date'),
                'CUSTOM' => __('Custom'),
                'ALL' => __('All'),
            ],
        );
    }

    public function range(string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null): self
    {
        $this->range = $range;

        if ($range === 'CUSTOM') {
            $this->customRangeStart = $customRangeStart;
            $this->customRangeEnd = $customRangeEnd;
        }

        return $this;
    }

    protected function getRange(): Range
    {
        return match ($this->range) {
            '30', '60', '90', '365' => new Days((int) $this->range),
            'YESTERDAY' => new Yesterday,
            'TODAY' => new Today,
            'MTD' => new MonthToDate,
            'YTD' => new YearToDate,
            'CUSTOM' => new Custom(
                start: $this->customRangeStart,
                end: $this->customRangeEnd,
            ),
            default => throw InvalidRangeException::for($this->range),
        };
    }
}
