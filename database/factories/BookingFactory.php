<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('01#########'),
            'address' => fake()->address(),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+30 days')->setTime(fake()->numberBetween(9, 17), 0),
            'status' => BookingStatus::Pending,
            'payment_method' => PaymentMethod::CashOnService,
            'base_price' => '100.00',
            'service_fee' => '5.00',
            'total_amount' => '105.00',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function withStatus(BookingStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
