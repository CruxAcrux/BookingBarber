<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Service;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SlotAvailability
{
    private int $intervalMinutes;

    public function __construct(?int $intervalMinutes = null)
    {
        $this->intervalMinutes = $intervalMinutes ?? config('booking.slot_interval_minutes');

        if ($this->intervalMinutes < 1) {
            throw new InvalidArgumentException('The slot interval must be at least one minute.');
        }
    }

    /**
     * Every start time a barber could take this service at on the given date.
     *
     * @return Collection<int, TimeSlot>
     */
    public function slotsFor(Barber $barber, Service $service, CarbonInterface $date): Collection
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        $shifts = $barber->workingHours()
            ->where('day_of_week', $day->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($shifts->isEmpty()) {
            return collect();
        }

        $taken = $this->blockingBookings($barber, $day);

        return $shifts
            ->flatMap(fn (WorkingHour $shift) => $this->candidatesWithin($shift, $day, $service))
            ->reject(fn (TimeSlot $slot) => $this->clashes($slot, $taken))
            ->values();
    }

    /**
     * Whether one specific start time is bookable.
     *
     * This deliberately asks the same question the customer's slot picker does,
     * so a time the form never offered can't be pushed through by hand.
     */
    public function isAvailable(Barber $barber, Service $service, CarbonInterface $start): bool
    {
        $start = CarbonImmutable::instance($start);

        return $this->slotsFor($barber, $service, $start)
            ->contains(fn (TimeSlot $slot) => $slot->start->equalTo($start));
    }

    /**
     * Walk the shift on the interval grid, keeping starts that leave room for the
     * full service before the shift ends.
     *
     * @return Collection<int, TimeSlot>
     */
    private function candidatesWithin(WorkingHour $shift, CarbonImmutable $day, Service $service): Collection
    {
        $opens = $this->at($day, $shift->start_time);
        $closes = $this->at($day, $shift->end_time);

        $slots = collect();

        for ($start = $opens; $start->addMinutes($service->duration_minutes)->lessThanOrEqualTo($closes); $start = $start->addMinutes($this->intervalMinutes)) {
            $slots->push(new TimeSlot($start, $start->addMinutes($service->duration_minutes)));
        }

        return $slots;
    }

    /**
     * Bookings that already hold time on this date. Fetched once so the overlap
     * check below stays in memory instead of hitting the database per slot.
     *
     * @return Collection<int, Booking>
     */
    private function blockingBookings(Barber $barber, CarbonImmutable $day): Collection
    {
        return $barber->bookings()
            ->blocking()
            ->overlapping($day, $day->addDay())
            ->get();
    }

    /**
     * @param  Collection<int, Booking>  $taken
     */
    private function clashes(TimeSlot $slot, Collection $taken): bool
    {
        return $taken->contains(
            fn (Booking $booking) => $booking->start_time < $slot->end && $booking->end_time > $slot->start
        );
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = explode(':', $time);

        return $day->setTime((int) $hour, (int) $minute);
    }
}
