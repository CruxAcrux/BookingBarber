<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'user_id' => User::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(30),
            'status' => BookingStatus::Pending,
        ];
    }

    public function from(\DateTimeInterface $start, int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $start,
            'end_time' => (clone $start)->modify("+{$minutes} minutes"),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
