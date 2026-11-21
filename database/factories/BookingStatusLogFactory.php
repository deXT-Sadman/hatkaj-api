<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingStatusLog>
 */
class BookingStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'from_status' => BookingStatus::Pending,
            'to_status' => BookingStatus::Confirmed,
        ];
    }
}
