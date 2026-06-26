<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Enums;

use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\Days;
use RoundlyConsulting\Metrics\Ranges\MonthToDate;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Ranges\Today;
use RoundlyConsulting\Metrics\Ranges\YearToDate;
use RoundlyConsulting\Metrics\Ranges\Yesterday;

enum Period: string
{
    case Days30 = '30';
    case Days60 = '60';
    case Days90 = '90';
    case Days365 = '365';
    case Yesterday = 'YESTERDAY';
    case Today = 'TODAY';
    case MonthToDate = 'MTD';
    case YearToDate = 'YTD';
    case Custom = 'CUSTOM';
    case All = 'ALL';

    /**
     * The translatable label shown in range pickers.
     */
    public function label(): string
    {
        return match ($this) {
            self::Days30 => __('30 Days'),
            self::Days60 => __('60 Days'),
            self::Days90 => __('90 Days'),
            self::Days365 => __('365 Days'),
            self::Yesterday => __('Yesterday'),
            self::Today => __('Today'),
            self::MonthToDate => __('Month To Date'),
            self::YearToDate => __('Year To Date'),
            self::Custom => __('Custom'),
            self::All => __('All'),
        };
    }

    /**
     * Resolve the concrete date range for this period.
     *
     * @throws InvalidRangeException when the period has no bounded range (e.g. "ALL").
     */
    public function toRange(?string $start = null, ?string $end = null): Range
    {
        return match ($this) {
            self::Days30, self::Days60, self::Days90, self::Days365 => new Days((int) $this->value),
            self::Yesterday => new Yesterday,
            self::Today => new Today,
            self::MonthToDate => new MonthToDate,
            self::YearToDate => new YearToDate,
            self::Custom => new Custom(start: $start, end: $end),
            self::All => throw InvalidRangeException::for($this->value),
        };
    }

    /**
     * The full catalogue of selectable periods keyed by their string value.
     *
     * @return array<array-key, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
