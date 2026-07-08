<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Enums;

use RoundlyConsulting\Enums\Helpers;
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
    use Helpers;

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
     *
     * Overrides the trait's value-based headline because the backed values are
     * wire tokens (7, WTD, MTD, …) that would headline poorly.
     */
    public function readable(): string
    {
        return (string) __(match ($this) {
            self::Days7 => '7 Days',
            self::Days14 => '14 Days',
            self::Days30 => '30 Days',
            self::Days60 => '60 Days',
            self::Days90 => '90 Days',
            self::Days365 => '365 Days',
            self::Yesterday => 'Yesterday',
            self::Today => 'Today',
            self::WeekToDate => 'Week To Date',
            self::MonthToDate => 'Month To Date',
            self::QuarterToDate => 'Quarter To Date',
            self::YearToDate => 'Year To Date',
            self::ThisWeek => 'This Week',
            self::LastWeek => 'Last Week',
            self::ThisMonth => 'This Month',
            self::LastMonth => 'Last Month',
            self::ThisQuarter => 'This Quarter',
            self::LastQuarter => 'Last Quarter',
            self::ThisYear => 'This Year',
            self::LastYear => 'Last Year',
            self::Custom => 'Custom',
            self::All => 'All',
        });
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
}
