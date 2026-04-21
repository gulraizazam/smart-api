<?php

declare(strict_types=1);

namespace App\Services\Dashboard\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Inclusive date window for dashboard metrics. Boundaries snap to start/
 * end of day so range checks are stable across timezones.
 */
final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {
        if ($to->startOfDay()->lt($from->startOfDay())) {
            throw new InvalidArgumentException('DateRange: to must be on or after from');
        }
    }

    public static function fromStrings(string $from, string $to): self
    {
        return new self(
            CarbonImmutable::parse($from)->startOfDay(),
            CarbonImmutable::parse($to)->endOfDay(),
        );
    }

    public function startString(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function endString(): string
    {
        return $this->to->format('Y-m-d');
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * The equivalent-length window immediately preceding this one. Used by
     * widgets that show prior-period comparison columns (Branch
     * Leaderboard's "vs prev", New-vs-Returning's retention rate).
     */
    public function previousPeriod(): self
    {
        $days = $this->days();

        return new self(
            $this->from->subDays($days),
            $this->to->subDays($days),
        );
    }
}
