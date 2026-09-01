<?php

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Services\SlotAvailability;
use Carbon\CarbonImmutable;

const MONDAY = '2026-09-07';
const TUESDAY = '2026-09-08';

function slotsFor(Barber $barber, Service $service, string $date = MONDAY, int $interval = 15): array
{
    return (new SlotAvailability($interval))
        ->slotsFor($barber, $service, CarbonImmutable::parse($date))
        ->map(fn ($slot) => $slot->start->format('H:i'))
        ->all();
}

function bookingFor(Barber $barber, string $from, int $minutes, string $date = MONDAY): Booking
{
    return Booking::factory()
        ->for($barber)
        ->from(CarbonImmutable::parse("{$date} {$from}"), $minutes)
        ->confirmed()
        ->create();
}

beforeEach(function () {
    $this->barber = Barber::factory()->create();
    $this->hour = Service::factory()->lasting(60)->create();
});

it('offers nothing when the barber does not work that day', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();

    expect(slotsFor($this->barber, $this->hour, TUESDAY))->toBeEmpty();
});

it('offers nothing when the barber has no working hours at all', function () {
    expect(slotsFor($this->barber, $this->hour))->toBeEmpty();
});

it('fills the whole day when there are no bookings', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();

    $slots = slotsFor($this->barber, $this->hour);

    expect($slots)->toHaveCount(29)
        ->and($slots[0])->toBe('09:00')
        ->and(end($slots))->toBe('16:00');
});

it('offers the very first slot of the working day', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();

    expect(slotsFor($this->barber, $this->hour))->toContain('09:00');
});

it('offers a slot that ends exactly at closing time but not one that runs past it', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();

    expect(slotsFor($this->barber, $this->hour))
        ->toContain('16:00')
        ->not->toContain('16:15');
});

it('removes only the slots that clash with an existing booking', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    bookingFor($this->barber, '10:00', 60);

    $slots = slotsFor($this->barber, $this->hour);

    expect($slots)->toHaveCount(22)
        ->not->toContain('09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45');
});

it('allows a booking to start the moment the previous one ends', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    bookingFor($this->barber, '10:00', 60);

    expect(slotsFor($this->barber, $this->hour))
        ->toContain('09:00')
        ->toContain('11:00');
});

it('offers nothing when the day is fully booked', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '10:00:00')->create();
    bookingFor($this->barber, '09:00', 60);

    expect(slotsFor($this->barber, $this->hour))->toBeEmpty();
});

it('does not offer a gap that is shorter than the service', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '12:00:00')->create();
    bookingFor($this->barber, '10:00', 60);

    $ninetyMinutes = Service::factory()->lasting(90)->create();

    expect(slotsFor($this->barber, $ninetyMinutes))->toBeEmpty();
});

it('offers a gap that exactly fits the service', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '12:00:00')->create();
    bookingFor($this->barber, '10:00', 60);

    expect(slotsFor($this->barber, $this->hour))
        ->toBe(['09:00', '11:00']);
});

it('skips the lunch break when the day is split into two shifts', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '12:00:00')->create();
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('13:00:00', '18:00:00')->create();

    $slots = slotsFor($this->barber, $this->hour);

    expect($slots)->toContain('11:00', '13:00')
        ->not->toContain('12:00', '12:15')
        ->and($slots)->toHaveCount(26);
});

it('frees the slot again once a booking is cancelled', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    Booking::factory()->for($this->barber)
        ->from(CarbonImmutable::parse(MONDAY.' 10:00'), 60)
        ->cancelled()
        ->create();

    expect(slotsFor($this->barber, $this->hour))->toContain('10:00');
});

it('still blocks a slot held by a pending booking', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    Booking::factory()->for($this->barber)
        ->from(CarbonImmutable::parse(MONDAY.' 10:00'), 60)
        ->create();

    expect(slotsFor($this->barber, $this->hour))->not->toContain('10:00');
});

it('ignores bookings belonging to another barber', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    bookingFor(Barber::factory()->create(), '10:00', 60);

    expect(slotsFor($this->barber, $this->hour))->toContain('10:00');
});

it('ignores bookings on a different day', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    bookingFor($this->barber, '10:00', 60, TUESDAY);

    expect(slotsFor($this->barber, $this->hour))->toContain('10:00');
});

it('places slots on the configured interval grid', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '12:00:00')->create();

    expect(slotsFor($this->barber, $this->hour, MONDAY, 30))
        ->toBe(['09:00', '09:30', '10:00', '10:30', '11:00']);
});

it('handles a booking that starts before opening time', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    bookingFor($this->barber, '08:30', 60);

    expect(slotsFor($this->barber, $this->hour))
        ->not->toContain('09:00')
        ->toContain('09:30');
});

it('reports whether one specific start time can be booked', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();
    bookingFor($this->barber, '10:00', 60);

    $availability = new SlotAvailability(15);

    expect($availability->isAvailable($this->barber, $this->hour, CarbonImmutable::parse(MONDAY.' 11:00')))->toBeTrue()
        ->and($availability->isAvailable($this->barber, $this->hour, CarbonImmutable::parse(MONDAY.' 10:00')))->toBeFalse()
        ->and($availability->isAvailable($this->barber, $this->hour, CarbonImmutable::parse(MONDAY.' 16:15')))->toBeFalse()
        ->and($availability->isAvailable($this->barber, $this->hour, CarbonImmutable::parse(MONDAY.' 09:07')))->toBeFalse()
        ->and($availability->isAvailable($this->barber, $this->hour, CarbonImmutable::parse(TUESDAY.' 11:00')))->toBeFalse();
});

it('returns slots carrying the end time derived from the service duration', function () {
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '17:00:00')->create();

    $slot = (new SlotAvailability(15))
        ->slotsFor($this->barber, $this->hour, CarbonImmutable::parse(MONDAY))
        ->first();

    expect($slot->start->format('Y-m-d H:i'))->toBe('2026-09-07 09:00')
        ->and($slot->end->format('Y-m-d H:i'))->toBe('2026-09-07 10:00');
});

it('resolves the interval from config when none is given', function () {
    config(['booking.slot_interval_minutes' => 60]);
    WorkingHour::factory()->for($this->barber)->onDay(1)->between('09:00:00', '12:00:00')->create();

    $slots = app(SlotAvailability::class)
        ->slotsFor($this->barber, $this->hour, CarbonImmutable::parse(MONDAY))
        ->map(fn ($slot) => $slot->label())
        ->all();

    expect($slots)->toBe(['09:00', '10:00', '11:00']);
});

it('refuses an interval that would never advance', function () {
    new SlotAvailability(0);
})->throws(InvalidArgumentException::class);
