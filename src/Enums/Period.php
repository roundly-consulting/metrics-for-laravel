<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Enums;

use RoundlyConsulting\Metrics\Exceptions\InvalidRangeException;
use RoundlyConsulting\Metrics\Ranges\Custom;
use RoundlyConsulting\Metrics\Ranges\Days;
use RoundlyConsulting\Metrics\Ranges\LastMonth;
use RoundlyConsulting\Metrics\Ranges\LastQuarter;
use RoundlyConsulting\Metrics\Ranges\LastWeek;
use RoundlyConsulting\Metrics\Ranges\LastYear;
use RoundlyConsulting\Metrics\Ranges\MonthToDate;
use RoundlyConsulting\Metrics\Ranges\QuarterToDate;
use RoundlyConsulting\Metrics\Ranges\Range;
use RoundlyConsulting\Metrics\Ranges\ThisMonth;
use RoundlyConsulting\Metrics\Ranges\ThisQuarter;
use RoundlyConsulting\Metrics\Ranges\ThisWeek;
use RoundlyConsulting\Metrics\Ranges\ThisYear;
use RoundlyConsulting\Metrics\Ranges\Today;
use RoundlyConsulting\Metrics\Ranges\WeekToDate;
use RoundlyConsulting\Metrics\Ranges\YearToDate;
use RoundlyConsulting\Metrics\Ranges\Yesterday;

enum Period: string
{
    case Days7 = '7';
    case Days14 = '14';
    case Days30 = '30';
    case Days60 = '60';
    case Days90 = '90';
    case Days365 = '365';
    case Yesterday = 'YESTERDAY';
    case Today = 'TODAY';
    case WeekToDate = 'WTD';
    case MonthToDate = 'MTD';
    case QuarterToDate = 'QTD';
    case YearToDate = 'YTD';
    case ThisWeek = 'THIS_WEEK';
    case LastWeek = 'LAST_WEEK';
    case ThisMonth = 'THIS_MONTH';
    case LastMonth = 'LAST_MONTH';
    case ThisQuarter = 'THIS_QUARTER';
    case LastQuarter = 'LAST_QUARTER';
    case ThisYear = 'THIS_YEAR';
    case LastYear = 'LAST_YEAR';
    case Custom = 'CUSTOM';
    case All = 'ALL';

    /**
     * The translatable label shown in range pickers.
     */
    public function label(): string
    {
        return match ($this) {
            self::Days7 => __('7 Days'),
            self::Days14 => __('14 Days'),
            self::Days30 => __('30 Days'),
            self::Days60 => __('60 Days'),
            self::Days90 => __('90 Days'),
            self::Days365 => __('365 Days'),
            self::Yesterday => __('Yesterday'),
            self::Today => __('Today'),
            self::WeekToDate => __('Week To Date'),
            self::MonthToDate => __('Month To Date'),
            self::QuarterToDate => __('Quarter To Date'),
            self::YearToDate => __('Year To Date'),
            self::ThisWeek => __('This Week'),
            self::LastWeek => __('Last Week'),
            self::ThisMonth => __('This Month'),
            self::LastMonth => __('Last Month'),
            self::ThisQuarter => __('This Quarter'),
            self::LastQuarter => __('Last Quarter'),
            self::ThisYear => __('This Year'),
            self::LastYear => __('Last Year'),
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
            self::Days7, self::Days14, self::Days30, self::Days60, self::Days90, self::Days365 => new Days((int) $this->value),
            self::Yesterday => new Yesterday,
            self::Today => new Today,
            self::WeekToDate => new WeekToDate,
            self::MonthToDate => new MonthToDate,
            self::QuarterToDate => new QuarterToDate,
            self::YearToDate => new YearToDate,
            self::ThisWeek => new ThisWeek,
            self::LastWeek => new LastWeek,
            self::ThisMonth => new ThisMonth,
            self::LastMonth => new LastMonth,
            self::ThisQuarter => new ThisQuarter,
            self::LastQuarter => new LastQuarter,
            self::ThisYear => new ThisYear,
            self::LastYear => new LastYear,
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
