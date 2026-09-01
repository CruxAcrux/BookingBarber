<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class TimeSlot
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public function label(): string
    {
        return $this->start->format('H:i');
    }
}
